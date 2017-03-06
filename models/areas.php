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

    public function save($area) {
        $id;
        if (empty($area->id) || $area->id == DB::BAD_ID) {
            $id = $this->db_insert($area);
        } else {
            $this->db_update($area);
            $id = $area->id;
        }
        if($id == DB::BAD_ID) {
            throw new \Exception("Error saving area.");
        }
        return $this->get($id);
    }

    private function db_insert($area) {
        $area->coordinate1 = Coordinates::instance()->save($area->coordinate1);
        $area->coordinate1_id = $area->coordinate1->id;
        $area->coordinate2 = Coordinates::instance()->save($area->coordinate2);
        $area->coordinate2_id = $area->coordinate2->id;

        if(!$area->is_valid()) {
            return DB::BAD_ID;
        }

        $area_values = array(
            'coordinate1_id' => $area->coordinate1_id,
            'coordinate2_id' => $area->coordinate2_id,
            'name' => $area->name
        );
        $area_id = DB::insert($this->table, $area_values);
        if(empty($area_id) || $area_id == DB::BAD_ID) {
            throw new \Exception(
                "Could not insert place: ". var_export($area_values, true));
        }
        $area->id = $area_id;
        return $area_id;
    }

    private function db_update($area) {
        $id = $area->coordinate1->id;
        $area->coordinate1 = Coordinates::instance()->save($area->coordinate1);
        $area->coordinate1_id = $area->coordinate1->id;
        $area->coordinate2 = Coordinates::instance()->save($area->coordinate2);
        $area->coordinate2_id = $area->coordinate2->id;

        if(!$area->is_valid()) {
            return null;
        }

        $area_values = array(
            'coordinate1_id' => $area->coordinate1_id,
            'coordinate2_id' => $area->coordinate2_id,
            'name' => $area->name
        );
        DB::update($this->table, $area->id, $area_values);
    }

    public function delete($area) {
        Coordinates::instance()->delete($area->coordinate1);
        $area->coordinate1_id = DB::BAD_ID;
        Coordinates::instance()->delete($area->coordinate2);
        $area->coordinate2_id = DB::BAD_ID;

        $row_count = DB::delete($this->table, $area->id);
        if($row_count != 1) {
            throw new \Exception("Error deleting area: " . $area->id . "\n");
        }
        $area->id = null;
        return $area;
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

    public function update_values($area, $array) {
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
}

?>