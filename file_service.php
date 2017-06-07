<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/message_service.php');
require_once(dirname(__FILE__) . '/post_service.php');
require_once(dirname(__FILE__) . '/views/view.php');
require_once(dirname(__FILE__) . '/view_helper.php');
require_once(dirname(__FILE__) . '/models/tour_records.php');
require_once(dirname(__FILE__) . '/models/tours.php');
require_once(dirname(__FILE__) . '/logging.php');

/**
 * A class used to handle the keeping of record files for tours.
 *
 * NOTE: Uses a lot of wordpress internal functions to determine file paths.
 */
class FileServiceResponse {

    // a boolean to indicate if everything went okay
    public $ok = false;

    // All files created are collected in this array
    // NOTE: These may not exist anymore
    public $files_created = array();

    // An array of MessageService Messages that may be shown to the user
    public $messages = array();

    public function add_file_created($path) {
        array_push($this->files_created, $path);
    }

    public function get_last_file_created() {
        $n = count($this->files_created);
        if($n <= 0) {
            return null;
        } else {
            return $this->files_created[$n - 1];
        }
    }

    public function add_error($msg_str) {
        $this->ok = false;
        $msg = new Message($msg_str, MessageService::ERROR);
        array_push($this->messages, $msg);
    }

    public function add_warning($msg_str) {
        $msg = new Message($msg_str, MessageService::WARNING);
        array_push($this->messages, $msg);
    }

}


class FileService {

    const TOUR_PREFIX = 'shtm-tour-';

    // path of the tour folder (for all tour files) below the wordpress upload
    // directory
    const TOUR_FOLDER_PATH = '/smart-history-tours';

    /**
     * Create files for the tour. Expects a valid tour as input with all
     * related objects set on the tour.
     *
     * @return FileServiceResponse  Always a response object
     */
    public static function create_files($record, $tour) {
        $response = new FileServiceResponse();

        // check that the record has an associated timestamp and content
        if(empty($record->published_at)) {
            $msg = "Cannot create tour files without publish timestamp.";
            return self::handle_error($response, $msg);
        }
        if(empty($record->content)) {
            $msg = "Cannot create tour files without tour record content.";
            return self::handle_error($response, $msg);
        }

        // Get the files of mediaitems linked to the tour as first entries to
        // zip up
        $files_to_zip = self::get_files_for_tour($response, $tour);
        if(!$response->ok) {
            $msg = 'Failed to determine files for tour.';
            return self::handle_error($response, $msg);
        }

        // if a tour content file already exists, it should be save to remove,
        // but make sure to log it as this should not be the case normally
        $path = self::get_temporary_path_for_record($record, $tour->id, '.yaml');
        if(file_exists($path)) {
            debug_log("Removing previously created tour content at: $path");
            unlink($path);
        }

        // write the record content to disk
        $handle = fopen($path, 'w');
        $result = fwrite($handle, $record->content);
        $result_close = fclose($handle);

        if(!$result || !$result_close) {
            $msg = "Error creating the content file at: $path";
            return self::handle_error($response, $msg);
        } else {
            $response->add_file_created($path);
            array_push($files_to_zip, $path);
        }

        // create the zip archive from all files collected so far
        $path = self::get_temporary_path_for_record($record, $tour->id, '.zip');
        if(file_exists($path)) {
            debug_log("Removing previously created tour zip at: $path");
            unlink($path);
        }
        $response = self::create_zip_archive($response, $files_to_zip, $path);
        $zip_file = $response->get_last_file_created();
        if(!$response->ok || !file_exists($zip_file)) {
            $msg = 'Error packing the zip file.';
            return self::handle_error($response, $msg);
        }

        // get or create the tour folder
        $tour_folder = self::get_or_create_tour_folder($response);
        if(!$tour_folder || !$response->ok) {
            return self::handle_error($response, 'Failed to get tour folder.');
        }

        // copy the zip file to its destination
        $destination = $tour_folder . '/' . basename($zip_file);
        $result = copy($zip_file, $destination);
        if(!$result) {
            $msg = "Error copying the tour package to: $destination";
            $response->add_file_created($destination);
            return self::handle_error($response, $msg);
        } else {
            // remove the files previously created
            $response = self::remove_files_created($response);
            if(!$response->ok) {
                $msg = 'Critical error in removing created files';
                return $response;
            }
        }
        // all went well, return with the zip as last file created
        $response->add_file_created($destination);
        $response->ok = true;
        return $response;
    }

    /**
     * Returns the upload url if the file exists below the tour folder.
     *
     * @return String|null  The url on success else false.
     */
    public static function get_upload_url($path) {
        // get the tour folder name using a new response
        $response = new FileServiceResponse();
        $base_path = self::get_or_create_tour_folder($response);
        if(!$response->ok) {
            return null;
        }

        $base_url = wp_get_upload_dir()['baseurl'];
        $base_name = basename($path);
        if(file_exists("$base_path/$base_name")) {
            return ($base_url . self::TOUR_FOLDER_PATH . '/' . $base_name) ;
        } else {
            return null;
        }
    }

    // TODO: This belongs to an api endpoint, no file should be written
    public static function write_as_publish_list($str) {
        // get the tour folder name using a new response
        $response = new FileServiceResponse();
        $base_path = self::get_or_create_tour_folder($response);
        if(!$response->ok) {
            return null;
        }
        // simply write everything to file
        $path = $base_path . '/tours.yaml';
        $handle = fopen($path, 'w');
        $result = fwrite($handle, $str);
        debug_log("Wrote $result bytes to $path");
        return fclose($handle);
    }

    // return an array of mediaitems that belong to posts of a tour's mapstops
    private static function get_files_for_tour($response, $tour) {
        $result = array();
        foreach($tour->mapstops as $mapstop) {
            foreach($mapstop->post_ids as $post_id) {

                $media = PostService::get_post_media($post_id);
                foreach($media as $medium) {
                    $path = get_attached_file($medium->ID);
                    if(!file_exists($path)) {
                        $msg = "File does not exist: $medium->guid";
                        $msg .= " (mapstop: $mapstop->id, page: $post_id)";
                        $response->add_error($msg);
                        return $result;
                    }
                    array_push($result, $path);
                }
            }
        }
        $response->ok = true;
        return $result;
    }

    /**
     * Get the tour folder below the wordpress upload dir or create it if it is
     * not present. Set errors on the response object if anything bad happens.
     *
     * @return String|false  The path to the folder or false on error.
     */
    private static function get_or_create_tour_folder($response) {
        $upload_dir = self::get_wp_upload_base_dir();
        if(!is_dir($upload_dir)) {
            $response->add_error('Failed to determine the base upload dir.');
            return false;
        }
        // the tour folder is directly below the upload folder
        $tour_folder = $upload_dir . self::TOUR_FOLDER_PATH;
        $result = wp_mkdir_p($tour_folder);
        if(!$result) {
            $msg = "Failed to get/create the tour folder at: $path";
            $response->add_error($msg);
            return false;
        }
        $response->ok = true;
        return $tour_folder;
    }

    private static function create_zip_archive($response, $files, $path) {
        $zip = new \ZipArchive();
        $result = $zip->open($path,
            (\ZipArchive::CREATE | \ZipArchive::OVERWRITE));
        if(!$result) {
            $msg = "Failed to open zip archive (code: '$result') at: '$path'.";
            return self::handle_error($response, $msg);
        }
        // add all files to the zip archive setting the relative path to
        // their basename
        foreach ($files as $file) {
            $result = $zip->addFile($file, basename($file));
            if(!$result) {
                $msg = "Failed to add file to zip archive: $file";
                return self::handle_error($response, $msg);
            }
        }
        // Create the archive by calling close()
        $result = $zip->close();
        if(!$result) {
            $msg = "Failed to close zip archive on path: $path.";
            return self::handle_error($response, $msg);
        }
        // Set the created archive path and return ok
        $response->add_file_created($path);
        $response->ok = true;
        return $response;
    }

    /**
     * Set the error on the response object, clean up all files created and
     * return the response object.
     *
     * @return FileServiceResponse
     */
    private static function handle_error($response, $msg_str) {
        $response->add_error($msg_str);
        $response = self::remove_files_created($response);
        return $response;
    }

    /**
     * Remove all files that are listed as created. (Only files below /tmp or
     * below the wp-content folder can be deleted this way).
     *
     * @return FileServiceResponse
     */
    public static function remove_files_created($response) {
        // We need to determine the wordpress upload dir and the tmp folder
        // to check the created file paths
        $ul_dir = self::get_wp_upload_base_dir();
        $tmp_dir = sys_get_temp_dir();
        if(!is_dir($ul_dir) || !is_dir($tmp_dir)) {
            $msg = 'Failed to get viable directories for deletion on error.';
            $response->add_error($msg);
            return $response;
        }
        // remove the created files, check paths to make sure that we do not
        // delete anywhere where we are not supposed to
        $might_still_exist = array();
        foreach($response->files_created as $path) {
            $in_ul_dir = (substr($path, 0, strlen($ul_dir)) === $ul_dir);
            $in_tmp_dir = (substr($path, 0, strlen($tmp_dir)) === $tmp_dir);
            if($in_ul_dir || $in_tmp_dir) {
                $result = unlink($path);
                if(!$result) {
                    array_push($might_still_exist, $path);
                    $response->add_warning("Failed to remove: $path");
                }
            } else {
                array_push($might_still_exist, $path);
                $response->add_warning("Invalid path to delete: $path");
            }
        }
        $response->files_created = $might_still_exist;
        return $response;
    }

    // a records file name depends on the published_at-field (always present)
    // and the tour id, which might not be present on the record and thus must
    // be given as a param
    private static function get_temporary_path_for_record($record, $tour_id, $suffix) {
        $path = sys_get_temp_dir() . '/' . self::TOUR_PREFIX;
        $path .= $tour_id . '-' . $record->published_at . $suffix;
        return $path;
    }

    // return the base dir where wp uploads are stored
    private static function get_wp_upload_base_dir() {
        return wp_get_upload_dir()['basedir'];
    }


}

?>