<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/abstract_collection.php');
require_once(dirname(__FILE__) . '/../db.php');

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

        $values = array(
            'lat' => $coordinate->lat,
            'lon' => $coordinate->lon
        );

        $id;
        if (empty($coordinate->id) || $coordinate->id == DB::BAD_ID) {
            $id = $this->db_insert($values);
            $coordinate->id = $id;
        } else {
            DB::update($this->table, $coordinate->id, $values);
            $id = $coordinate->id;
        }
        if($id == DB::BAD_ID) {
            throw new \Exception("Error saving area.");
        }
        return $this->get($id);
    }

    public function delete($coordinate) {
        $row_count = DB::delete($this->table, $coordinate->id);
        if($row_count != 1) {
            throw new \Exception(
                "Error deleting coordinate: " . $coordinate->id . "\n");
        }
        $coordinate->id = null;
        return $coordinate;
    }

    private function db_insert($coordinate_values) {
        $coord_id = DB::insert($this->table, $coordinate_values);
        if($coord_id == DB::BAD_ID) {
            throw new \Exception(
                "Could not insert coordinate: ". var_export($coordinate_values, true));
        }
        return $coord_id;
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