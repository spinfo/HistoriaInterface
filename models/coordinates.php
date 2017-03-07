<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/abstract_collection.php');
require_once(dirname(__FILE__) . '/../db.php');
require_once(dirname(__FILE__) . '/../logging.php');

final class Coordinates extends AbstractCollection {

    protected static $instance = null;

    public $table;

    protected function __construct() {
        $this->table = DB::table_name('coordinates');
    }

    public function get($id) {
        $sql = "SELECT * FROM $this->table";
        $row = DB::get($sql, array('id' => $id));

        if(is_null($row)) {
            return null;
        } else {
            return $this->instance_from_array($row);
        }
    }

    public function save($coordinate) {
        if(!$coordinate->is_valid()) {
            return null;
        }

        $id;
        if (empty($coordinate->id) || $coordinate->id == DB::BAD_ID) {
            $id = $this->db_insert($coordinate);
        } else {
            $this->db_update($coordinate);
            $id = $coordinate->id;
        }
        if($id == DB::BAD_ID) {
            throw new \Exception("Error saving coordinate.");
        }
        return $this->get($id);
    }

    protected function db_delete($coordinate) {
        $row_count = DB::delete($this->table, $coordinate->id);
        if($row_count != 1) {
            throw new DB_Exception(
                "Error deleting coordinate: " . $coordinate->id . "\n");
        }
        $coordinate->id = DB::BAD_ID;
        return $coordinate;
    }

    protected function db_insert($coordinate) {
        if ($coordinate->id > 0) {
            throw new DB_Exception("Cannot insert with existing id");
        }

        $values = array(
            'lat' => $coordinate->lat,
            'lon' => $coordinate->lon
        );

        $coordinate->id = DB::insert($this->table, $values);
        if($coordinate->id == DB::BAD_ID) {
            throw new DB_Exception(
                "Could not insert coordinate: $coordinate->id");
        }
        return $coordinate->id;
    }

    protected function db_update($coordinate) {
        if(!$coordinate->is_valid()) {
            debug_log("Cannot insert coordinate. Messages:");
            $coordinate->debug_log_messages();
            return null;
        }

        $values = array(
            'lat' => $coordinate->lat,
            'lon' => $coordinate->lon
        );

        $result = DB::update($this->table, $coordinate->id, $values);
        if(!$result) {
            debug_log("Could not update coordinate: $coordinate->id");
        }
        return $coordinate;
    }

    public function instance_from_array($row) {
        $row = (object) $row;
        $coord = new Coordinate();
        $coord->lat = floatval($row->lat);
        $coord->lon = floatval($row->lon);
        $this->set_abstract_model_values($coord, $row);
        return $coord;
    }
}

?>