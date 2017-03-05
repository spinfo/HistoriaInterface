<?php
namespace SmartHistoryTourManager;

include_once(dirname(__FILE__) . '/../db.php');

include_once(dirname(__FILE__) . '/../user_rights_service.php');

abstract class AbstractModel {

    public $created_at;

    public $updated_at;

}

/**
 * A wrapper for model collections. Ensures that all model collections are
 * singleton classes and provides convenience methods to access the database
 * facade.
 */
abstract class AbstractCollection {

    // Every child has to implement its own $instance like this:
    // protected static $instance = null;

    // Every child should implement its own $table like this:
    // public $table = DB::table_name('str');

    protected $user_rights_service;

    public $table;

    // TODO: The coordinates table should have it's own collection
    public $coordinates_table;

    protected $db;

    static function instance() {
        if (static::$instance == null) {
            static::$instance = new static;
            static::$instance->user_rights_service =
                UserRightsService::instance();
            static::$instance->coordinates_table =
                DB::table_name("coordinates");
        }
        return static::$instance;
    }

    /**
     * Sets values all models need from a database row, these are:
     *  - timestamps
     *
     * @param Object extending Model
     */
    protected function set_abstract_model_values($model, $db_row) {
        $model->created_at = new \DateTime($db_row->created_at);
        $model->updated_at = new \DateTime($db_row->updated_at);
    }
}

class Place extends AbstractModel {

    public $id = -1;

    public $user_id = -1;

    public $area_id = -1;

    public $coordinate_id = -1;

    public $name = "";

    public $lat = 0.0;

    public $lon = 0.0;

}

final class Places extends AbstractCollection {

    protected static $instance = null;

    public $table;

    protected function __construct() {
        $this->table = DB::table_name('places');
    }

    private function select_sql() {
        return "SELECT p.id, p.user_id, p.area_id, p.coordinate_id, p.name, c.lat, c.lon, p.created_at, p.updated_at
            FROM $this->table AS p
            JOIN $this->coordinates_table AS c
                ON p.coordinate_id = c.id";
    }

    public function list($offset, $limit) {
        $where = $this->user_rights_service->access_conditions();
        $rows = DB::list($this->select_sql(), $where, $offset, $limit);

        $places = array();
        foreach($rows as $count => $row) {
            $place = $this->instance_from_array($row);
            $places[] = $place;
        }
        return $places;
    }

    public function get($id) {
        $where = array('p.id' => $id);
        $row = DB::get($this->select_sql(), $where);

        if(is_null($row)) {
            return null;
        } else {
            $place = $this->instance_from_array($row);
            return $place;
        }
    }

    public function save($place) {
        $id;
        if (empty($place->id) || $place->id == DB::BAD_ID) {
            $id = $this->db_insert($place);
        } else {
            $this->db_update($place);
            $id = $place->id;
        }
        return $this->get($id);
    }

    private function db_insert($place) {
        $coord = array(
            'lat' => $place->lat,
            'lon' => $place->lon
        );
        $coord_id = DB::insert($this->coordinates_table, $coord);
        if($coord_id == DB::BAD_ID) {
            throw new \Exception(
                "Could not insert coordinate: ". var_export($coord, true));
        }

        $place_values = array(
            'user_id' => $place->user_id,
            'coordinate_id' => $coord_id,
            'area_id' => $place->area_id,
            'name' => $place->name
        );
        $place_id = DB::insert($this->table, $place_values);
        if($place_id == DB::BAD_ID) {
            throw new \Exception(
                "Could not insert place: ". var_export($place_values, true));
        }
        return $place_id;
    }

    // TODO: Some error handling, as transaction...
    private function db_update($place) {
        $coord_values = array(
            'lat' => $place->lat,
            'lon' => $place->lon
        );
        DB::update($this->coordinates_table, $place->coordinate_id, $coord_values);
        $place_values = array(
            'user_id' => $place->user_id,
            'coordinate_id' => $place->coordinate_id,
            'area_id' => $place->area_id,
            'name' => $place->name
        );
        DB::update($this->table, $place->id, $place_values);
    }

    /**
     * @return Place|null The deleted object if successful, else null
     */
    public function delete($place) {
        if(empty($place->id) || empty($place->coordinate_id)) {
            error_log("Cannot delete without id.");
            return null;
        }
        $row_count = DB::delete($this->coordinates_table, $place->coordinate_id);
        if($row_count != 1) {
            throw new \Exception(
                "Error deleting coordinate: " . $place->coordinate_id . "\n");
        }

        $row_count = DB::delete($this->table, $place->id);
        if($row_count != 1) {
            throw new \Exception("Error deleting place: " . $place->id . "\n");
        }
        $place->id = null;
        return $place;
    }

    /**
     * Creates a new place object based on the values in the supplied array
     * or simply creates a new place object. (No checking is done here. This
     * will blindly use values in the array.)
     *
     * @param array $array|stdObject
     *
     * @return Place
     */
    public function create($array = null) {
        if(isset($array)) {
            $place = $this->instance_from_array($array);
        } else {
            $place = new Place();
        }
        return $place;
    }

    /**
     * Constructs a new Place from an associative array containing only
     * strings.
     *
     * @param array|stdObject $array
     *
     * @return Place
     */
    private function instance_from_array($array) {
        $place = new Place();

        $obj = (object) $array;

        $place->id = intval($obj->id);
        $place->user_id = intval($obj->user_id);
        $place->coordinate_id = intval($obj->coordinate_id);
        $place->area_id = intval($obj->area_id);

        $this->update_values($place, $obj);
        $this->set_abstract_model_values($place, $obj);

        return $place;
    }

    /**
     * Updates alle those values of a place that have not been created
     * by the database/are not foreign keys. Uses the supplied array for new
     * values.
     *
     * Does not persist to the database.
     *
     * @param array|stdObj $from_array
     */
    public function update_values($place, $from_array) {
        $obj = (object) $from_array;
        $place->name = $obj->name;
        $place->lat = floatval($obj->lat);
        $place->lon = floatval($obj->lon);
    }
}

class Areas extends AbstractCollection {

    protected static $instance = null;

    public $table;

    protected function __construct() {
        $this->table = DB::table_name('areas');
    }

}

?>