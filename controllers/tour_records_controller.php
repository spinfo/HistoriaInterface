<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/abstract_controller.php');
require_once(dirname(__FILE__) . '/../route_params.php');
require_once(dirname(__FILE__) . '/../user_service.php');
require_once(dirname(__FILE__) . '/../views/view.php');
require_once(dirname(__FILE__) . '/../view_helper.php');
require_once(dirname(__FILE__) . '/../models/tour_records.php');
require_once(dirname(__FILE__) . '/../models/tour_record.php');
require_once(dirname(__FILE__) . '/../models/tours.php');
require_once(dirname(__FILE__) . '/../file_service.php');
require_once(dirname(__FILE__) . '/../publishing_check.php');

class TourRecordsController extends AbstractController {


    public static function index() {
        // anybody may view active tour records and the list functions are
        // robust, so no checking is done here
        $area_id = self::determine_area_id();
        $records = TourRecords::instance()->list_active_by_area($area_id);
        $areas = Areas::instance()->list_simple();
        $publishable_tours = Tours::instance()->list_by_area($are_id);
        $view = new View(ViewHelper::index_tour_records_view(), array(
            'records' => $records,
            'areas_list' => $areas,
            'current_area_id' => $area_id,
            'publishable_tours' => $publishable_tours
        ));
        self::wrap_in_page_view($view)->render();
    }

    public static function view() {
        $record_id = RouteParams::get_id_value();
        $record = TourRecords::instance()->get($record_id);
        if(!empty($record)) {
            $view = new View(ViewHelper::tour_record_view(), array(
                'record' => $record,
                'area' => Areas::instance()->get($record->area_id),
                'show_download_url' => true
            ));
        } else {
            $view = self::create_not_found_view(
                "Keine veröffentlichte Tour für Record id: '$id'");
        }
        self::wrap_in_page_view($view)->render();
    }

    public static function new() {
        $error_view = self::filter_if_user_may_not_publish();
        if(is_null($error_view)) {
            $tour_id = RouteParams::get_tour_id_value();
            $tour = Tours::instance()->get($tour_id, true, true);
            if(!empty($tour)) {
                // setup the tour and the tour record for file creation
                Tours::instance()->set_related_objects_on($tour);
                $record = new TourRecord();
                $record->content = self::create_tour_content($tour);

                // create the files to get an accurate download size
                $response = FileService::create_files($record, $tour);
                if($response->ok) {
                    self::set_tour_record_values($record, $tour, $response);
                    FileService::remove_files_created($response);
                    // check if the publishing would be successfull and include
                    // failures in the view
                    $failures = PublishingCheck::run($tour);
                    $view = new View(ViewHelper::new_tour_record_view(), array(
                        'record' => $record,
                        'area' => $tour->area,
                        'show_download_url' => false,
                        'publishing_check_failures' => $failures,
                    ));
                } else {
                    MessageService::instance()->add_all($response->messages);
                    $msg = "Tour Datei konnte nicht erstellt werden.";
                    $view = self::create_internal_error_view($msg);
                }
            } else {
                $msg = "Keine Tour mit id: '$tour_id'";
                $view = self::create_not_found_view($msg);
            }
        } else {
            $view = $error_view;
        }
        self::wrap_in_page_view($view)->render();
    }

    public static function create() {
        $error_view = self::filter_if_user_may_not_publish();
        if(is_null($error_view)) {
            $tour_id = RouteParams::get_tour_id_value();
            $tour = Tours::instance()->get($tour_id, true, true);
            if(!empty($tour)) {
                // setup the tour and the tour record for file creation
                Tours::instance()->set_related_objects_on($tour);
                $record = new TourRecord();
                $record->content = self::create_tour_content($tour);
                $record->is_active = true;

                // check if the publishing would be successful, if not, redirect
                // back to route new
                $failures = PublishingCheck::run($tour);
                if(!empty($failures)) {
                    self::redirect(RouteParams::new_tour_record($tour_id));
                }

                // create the files
                $response = FileService::create_files($record, $tour);
                MessageService::instance()->add_all($response->messages);
                if($response->ok) {
                    self::set_tour_record_values($record, $tour, $response);
                    // actually do the insert
                    $new_id = TourRecords::instance()->insert($record);
                    if($new_id > 0) {
                        MessageService::instance()->add_success(
                            "Tour veröffentlicht.");
                        self::update_publish_list();
                        self::redirect(RouteParams::view_tour_record($new_id));
                    } else {
                        // remove the created file and render an error
                        unlink($response->get_last_file_created());
                        $msg = "Tour Record konnte nicht gespeichert werden.";
                        $view = self::create_internal_error_view($msg);
                    }
                } else {
                    $msg = "Tour Datei konnte nicht erstellt werden.";
                    $view = self::create_internal_error_view($msg);
                }
            } else {
                $msg = "Keine Tour mit id: '$tour_id'";
                $view = self::create_not_found_view($msg);
            }
        } else {
            $view = $error_view;
        }
        self::wrap_in_page_view($view)->render();
    }

    public static function deactivate() {
        $error_view = self::filter_if_user_may_not_publish();
        if(is_null($error_view)) {
            $id = RouteParams::get_id_value();
            $record = TourRecords::instance()->get($id);
            if(!empty($record)) {
                $result = TourRecords::instance()->set_inactive($record);
                if($result) {
                    self::update_publish_list();
                    MessageService::instance()->add_success("Tour deaktiviert");
                    $area_id = self::determine_area_id();
                    self::redirect(RouteParams::index_tour_records($area_id));
                } else {
                    $msg = "Tour konnte nicht deaktiviert werden.";
                    $view = self::create_internal_error_view($msg);
                }
            } else {
                $msg = "No tour record with id '$id'";
                $view = self::create_not_found_view($msg);
            }
        } else {
            $view = $error_view;
        }
        self::wrap_in_page_view($view)->render();
    }

    public static function delete() {

    }

    public static function destroy() {

    }

    /**
     * @return View|null    An error view if the user may not publish tours
     *                      else null
     */
    private static function filter_if_user_may_not_publish() {
        if(!UserService::instance()->user_may_publish_tours()) {
            return self::create_access_denied_view();
        }
        return null;
    }

    /**
     * Create the tours content field that will be read by the client, i.e.
     * render the yaml for the tour report. Expects a valid tour as input with
     * all related objects set on the tour.
     *
     * @return String   The tour's content at the current moment.
     */
    private static function create_tour_content($tour) {
        $template_file = ViewHelper::tour_report_yaml_template();
        $view = new View($template_file, array('tour' => $tour));
        return $view->get_include_contents();
    }

    /**
     * Set a tour records' values (without content and is_active field) based
     * on the tour provided and the current logged in user.
     *
     * @return null
     */
    private static function set_tour_record_values($record, $tour,
        $fileservice_response)
    {
        $record->tour_id = $tour->id;
        $record->area_id = $tour->area_id;
        $record->user_id = UserService::instance()->user_id();
        $record->name = $tour->name;

        $file = $fileservice_response->get_last_file_created();
        $record->download_size = filesize($file);
        $record->media_url = FileService::get_upload_url($file);
    }

    // TODO: This belongs to an api endpoint, no file should be written
    private static function update_publish_list() {
        $records = TourRecords::instance()->list_active();
        $str = "";
        foreach ($records as $record) {
            $area = Areas::instance()->get($record->area_id);
            $str .= "---" . PHP_EOL;
            $str .= "id: $record->id" . PHP_EOL;
            $str .= "version: $record->published_at" . PHP_EOL;
            $str .= "name: '$record->name'" . PHP_EOL;
            $str .= "tourId: $record->tour_id" . PHP_EOL;
            $str .= "areaId: $record->area_id" . PHP_EOL;
            $str .= "areaName: '$area->name'" . PHP_EOL;
            $str .= "mediaUrl: '$record->media_url'" . PHP_EOL;
            $str .= "downloadSize: $record->download_size" . PHP_EOL;
            $str .= "..." . PHP_EOL;
        }
        FileService::write_as_publish_list($str);
    }

}

?>