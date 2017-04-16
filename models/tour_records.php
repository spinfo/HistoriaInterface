<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/abstract_collection.php');
require_once(dirname(__FILE__) . '/tour_record.php');
require_once(dirname(__FILE__) . '/../db.php');
require_once(dirname(__FILE__) . '/../logging.php');

class TourRecords extends AbstractCollection {

    protected static $instance = null;

    public $table;

    public function __construct() {
        $this->table = DB::table_name('tour_records');
    }

    protected function db_get($id) {
        return DB::get($this->select_sql(), array('id' => $id));
    }

    public function list_active() {
        return $this->do_list(array('is_active' => 1));
    }

    public function list_active_by_area($area_id) {
        return $this->do_list(array('is_active' => 1, 'area_id' => $area_id));
    }

    public function list_versions($tour_id) {
        return $this->do_list(array('tour_id' => $tour_id));
    }

    /**
     * Set a tour record to be the active version in the Database. This sets
     * all other records for the same tour to inactive.
     *
     * @return bool     Whether the operation was successful or not.
     */
    public function set_active($record) {
        DB::start_transaction();
        $result = $this->db_set_all_versions_inactive($record->tour_id);
        if(!(is_int($result) && $result >= 0)) {
            DB::rollback_transaction();
            return false;
        }

        $values = array('is_active' => 1);
        $result = DB::update($this->table, $record->id, $values);
        if(!$result) {
            DB::rollback_transaction();
            return false;
        } else {
            DB::commit_transaction();
            return true;
        }
    }

    /**
     * Set a record as inactive.
     *
     * @return bool     Whether the operation was successful or not.
     */
    public function set_inactive($record) {
        $values = array('is_active' => 0);
        return DB::update($this->table, $record->id, $values);
    }

    protected function db_insert($record) {
        if(!$record->is_valid()) {
            debug_log("Not inserting invalid tour record. Messages:");
            $record->debug_log_messages();
            return DB::BAD_ID;
        }
        DB::start_transaction();

        // a new tour record is always the active version, so inactivate all
        // others
        $result = $this->db_set_all_versions_inactive($record->tour_id);
        if(!(is_int($result) && $result >= 0)) {
            $msg = "Setting the other records inactive failed.";
            return $this->rollback_bad_insert($record, $msg);
        }
        $record->is_active = true;

        // collect the values and do the insert, a new tour is always active
        $values = $this->db_values($record);
        $result_id = DB::insert($this->table, $values);

        if($result_id == DB::BAD_ID) {
            $msg = "Inserting the record failed.";
            return $this->rollback_bad_insert($record, $msg);
        } else {
            // insert was good so set the new id, commit and return
            $record->id = $result_id;
            DB::commit_transaction();
            return $result_id;
        }
    }

    private function rollback_bad_insert($record, $msg) {
        $record->id = DB::BAD_ID;
        debug_log($msg . " Rolling back.");
        DB::rollback_transaction();
        return DB::BAD_ID;
    }

    protected function db_delete($record) {
        // directly do the delete
        $result = DB::delete_single($this->table, $record->id);

        // if successful, void record's id and return it, else throw an
        // exception as expected by the parent implementation
        if($result === 1) {
            $record->id = DB::BAD_ID;
            return $record;
        } else {
            $msg = "Deletion of record failed, affected rows: $result";
            throw new DB_Exception($msg);
        }
    }

    protected function db_update($record) {
        // a published tour is never to be updated, so just throw an exception
        $msg = "Tour records cannot be updated";
        throw new \BadMethodCallException($msg);
    }

    protected function instance_from_array($row) {
        $row = (object) $row;
        $record = new TourRecord();

        $this->update_values($record, $row);

        $this->set_abstract_model_values($record, $row);
        return $record;
    }

    private function update_values($record, $array) {
        $array = (object) $array;

        if(!empty($array->area_id)) {
            $record->area_id = intval($array->area_id);
        }
        if(!empty($array->tour_id)) {
            $record->tour_id = intval($array->tour_id);
        }
        if(!empty($array->user_id)) {
            $record->user_id = intval($array->user_id);
        }

        $record->is_active = boolval($array->is_active);

        $record->name = strval($array->name);
        $record->content = strval($array->content);
        $record->media_url = strval($array->media_url);

        $record->download_size = intval($array->download_size);
        $record->published_at = intval($array->published_at);
    }

    private function db_values($record) {
        $array = array(
            'area_id' => $record->area_id,
            'tour_id' => $record->tour_id,
            'user_id' => $record->user_id,
            'name' => $record->name,
            'content' => $record->content,
            'media_url' => $record->media_url,
            'download_size' => $record->download_size,
            'published_at' => $record->published_at
        );
        // handle mysqls bool type by setting the value explicitly to 1 or 0
        if($record->is_active === true) {
            $array['is_active'] = 1;
        } else {
            $array['is_active'] = 0;
        }
        return $array;
    }

    private function select_sql() {
        return "SELECT * FROM $this->table";
    }

    private function do_list($where) {
        $rows = DB::list($this->select_sql(), $where);

        $result = array();
        foreach ($rows as $row) {
            $result[] = $this->instance_from_array($row);
        }
        return $result;
    }

    /**
     * Set all tour records belonging to the same tour to inactive
     *
     * @return int|false    The number of rows affected or false on error
     */
    private function db_set_all_versions_inactive($tour_id) {
        $sql = "UPDATE $this->table SET is_active = 0 WHERE tour_id = %d";
        return DB::query($sql, $tour_id);
    }


}

?>
