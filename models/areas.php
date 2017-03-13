<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/abstract_collection.php');
require_once(dirname(__FILE__) . '/area.php');
require_once(dirname(__FILE__) . '/coordinates.php');
require_once(dirname(__FILE__) . '/../db.php');

class Areas extends AbstractCollection {

    protected static $instance = null;

    public $table;

    protected function __construct() {
        $this->table = DB::table_name('areas');
    }

    public function select_sql() {
        $coordinates_table = Coordinates::instance()->table;
        $sql = "
        SELECT a.id,
            a.coordinate1_id,
            a.coordinate2_id,
            a.name,
            c1.lat AS c1_lat,
            c1.lon AS c1_lon,
            c2.lat AS c2_lat,
            c2.lon AS c2_lon,
            a.created_at,
            a.updated_at
        FROM $this->table AS a
        JOIN $coordinates_table AS c1
            ON a.coordinate1_id = c1.id
        JOIN $coordinates_table AS c2
            ON a.coordinate2_id = c2.id";
        return $sql;
    }

    /**
     * Lists all areas in the database without resolving the coordinate id to
     * actual Coordinate objects.
     */
    public function list_simple() {
        $sql = $this->select_sql();
        $rows = DB::list($sql, array(1 => 1), 0, PHP_INT_MAX);

        $areas = array();
        foreach($rows as $count => $row) {
            $area = $this->instance_from_array($row);
            $areas[] = $area;
        }
        return $areas;
    }

    public function get($id) {
        $where = array('a.id' => $id);
        $row = DB::get($this->select_sql(), $where);

        if(is_null($row)) {
            return null;
        } else {
            $area = $this->instance_from_array($row);
            return $area;
        }
    }

    protected function db_insert($area) {
        DB::start_transaction();

        try {
            $area->coordinate1_id = Coordinates::instance()->insert($area->coordinate1);
            $area->coordinate2_id = Coordinates::instance()->insert($area->coordinate2);
        } catch (DB_Exception $e) {
            $this->rollback_insert($area);
            $msg = $e->getMessage();
            debug_log("Failed to insert coordinates for area: $msg");
            return DB::BAD_ID;
        }

        if(!$area->is_valid()) {
            $this->rollback_insert($area);
            debug_log("Failed to insert invalid area. Messages:");
            $area->debug_log_messages();
            return DB::BAD_ID;
        }

        $area_values = array(
            'coordinate1_id' => $area->coordinate1_id,
            'coordinate2_id' => $area->coordinate2_id,
            'name' => $area->name
        );
        $area_id = DB::insert($this->table, $area_values);
        if(empty($area_id) || $area_id == DB::BAD_ID) {
            $this->rollback_insert($area);
            throw new DB_Exception("Could not insert valid place.");
        }

        DB::commit_transaction();
        $area->id = $area_id;
        return $area_id;
    }

    protected function db_update($area) {
        DB::start_transaction();

        $c1_save = Coordinates::instance()->update($area->coordinate1);
        $c2_save = Coordinates::instance()->update($area->coordinate2);

        if(!$c1_save || !$c2_save || !$area->is_valid()) {
            debug_log("Failed to update invalid area.");
            DB::rollback_transaction();
            return false;
        }
        $area->coordinate1_id = $area->coordinate1->id;
        $area->coordinate2_id = $area->coordinate2->id;

        $area_values = array(
            'coordinate1_id' => $area->coordinate1_id,
            'coordinate2_id' => $area->coordinate2_id,
            'name' => $area->name
        );
        $result = DB::update($this->table, $area->id, $area_values);
        if(!$result) {
            DB::rollback_transaction();
        } else {
            DB::commit_transaction();
        }
        return $result;
    }

    protected function db_delete($area) {
        DB::start_transaction();

        try {
            Coordinates::instance()->delete($area->coordinate1);
            Coordinates::instance()->delete($area->coordinate2);
        } catch (DB_Exception $e) {
            debug_log("Aborting area delete: Failed to delete coordinate.");
            $this->rollback_delete($area);
            throw $e;
            return null;
        }

        $row_count = DB::delete_single($this->table, $area->id);
        if($row_count != 1) {
            $this->rollback_delete($area);
            throw new DB_Exception("Error deleting area: $area->id");
        } else {
            $area->coordinate1_id = DB::BAD_ID;
            $area->coordinate2_id = DB::BAD_ID;
            $area->id = null;
            DB::commit_transaction();
            return $area;
        }
    }

    private function instance_from_array($row) {
        $row = (object) $row;

        $area = new Area();

        $area->coordinate1_id = intval($row->coordinate1_id);
        $area->coordinate2_id = intval($row->coordinate2_id);
        $area->coordinate1 = new Coordinate();
        $area->coordinate2 = new Coordinate();
        $area->coordinate1->id = $area->coordinate1_id;
        $area->coordinate2->id = $area->coordinate2_id;

        $this->update_values($area, $row);
        $this->set_abstract_model_values($area, $row);

        return $area;
    }

    private function update_values($area, $array) {
        $array = (object) $array;

        $area->name = strval($array->name);

        if(empty($area->coordinate1)) {
            $area->coordinate1 = new Coordinate();
        }
        $area->coordinate1->lat = floatval($array->c1_lat);
        $area->coordinate1->lon = floatval($array->c1_lon);

        if(empty($area->coordinate2)) {
            $area->coordinate2 = new Coordinate();
        }
        $area->coordinate2->lat = floatval($array->c2_lat);
        $area->coordinate2->lon = floatval($array->c2_lon);
    }

    private function rollback_insert($area) {
        $area->id = DB::BAD_ID;
        $area->coordinate1_id = DB::BAD_ID;
        $area->coordinate2_id = DB::BAD_ID;
        $area->coordinate1->id = DB::BAD_ID;
        $area->coordinate2->id = DB::BAD_ID;
        DB::rollback_transaction();
    }

    private function rollback_delete($area) {
        $area->coordinate1->id = $area->coordinate1_id;
        $area->coordinate2->id = $area->coordinate2_id;
        DB::rollback_transaction();
    }
}

?>