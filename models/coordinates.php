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

    public function db_get($id) {
        $sql = "SELECT * FROM $this->table";
        return DB::get($sql, array('id' => $id));
    }

    protected function db_delete($coordinate) {
        $row_count = DB::delete_single($this->table, $coordinate->id);
        if($row_count != 1) {
            throw new DB_Exception(
                "Error deleting coordinate: $coordinate->id");
        }
        $coordinate->id = DB::BAD_ID;
        return $coordinate;
    }

    protected function db_insert($coordinate) {
        if(!$coordinate->is_valid()) {
            debug_log("Not inserting invalid coordinate. Messages:");
            $coordinate->debug_log_messages();
            return DB::BAD_ID;
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
            debug_log("Cannot update coordinate. Messages:");
            $coordinate->debug_log_messages();
            return false;
        }

        $values = array(
            'lat' => $coordinate->lat,
            'lon' => $coordinate->lon
        );

        $result = DB::update($this->table, $coordinate->id, $values);
        if(!$result) {
            debug_log("Could not update coordinate: $coordinate->id");
        }
        return $result;
    }

    public function instance_from_array($row) {
        $row = (object) $row;
        $coord = new Coordinate();

        $this->update_values($coord, $row);

        $this->set_abstract_model_values($coord, $row);
        return $coord;
    }

    public function update_values($coord, $array) {
        $array = (object) $array;
        $coord->lat = floatval($array->lat);
        $coord->lon = floatval($array->lon);
    }
}

?>