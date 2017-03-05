<?php
namespace SmartHistoryTourManager;

include_once(dirname(__FILE__) . '/../db.php');

include_once(dirname(__FILE__) . '/../user_rights_service.php');

abstract class AbstractModel {

    public $id = -1;

    public $created_at;

    public $updated_at;

    abstract function is_valid();

    public $messages = array();

    protected function do_check($condition, $message) {
        if($condition) {
            unset($this->messages[$message]);
        } else {
            $this->messages[$message] = true;
        }
        return $condition;
    }

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

    protected $db;

    static function instance() {
        if (static::$instance == null) {
            static::$instance = new static;
            static::$instance->user_rights_service =
                UserRightsService::instance();
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
        $db_row = (object) $db_row;
        $model->id = intval($db_row->id);
        $model->created_at = new \DateTime($db_row->created_at);
        $model->updated_at = new \DateTime($db_row->updated_at);
    }
}

class Coordinate extends AbstractModel {

    public $lat = 0.0;

    public $lon = 0.0;

    public function is_valid() {
        $this->do_check(!is_null($this->lat), 'latitude is null');
        $this->do_check(!is_null($this->lon), 'longitude is null');

        $condition = ($this->lat >= -90.0) && ($this->lat <= 90.0);
        $this->do_check($condition,
            "latitude not between -90 and 90: $this->lat");

        $condition = ($this->lon >= -180.0) && ($this->lon <= 180.0);
        $this->do_check($condition,
            "longitude not between -180 and 180: $this->lon");

        return (empty($this->messages));
    }

}

class Place extends AbstractModel {

    public $user_id = -1;

    public $area_id = -1;

    public $coordinate_id = -1;

    public $coordinate; // Coordinate object

    public $name = "";

    public function is_valid() {
        $this->do_check(($this->user_id > 0), 'user_id <= 0');
        $this->do_check(($this->area_id > 0), 'area_id <= 0');
        $this->do_check(($this->coordinate_id > 0), 'coordinate_id <= 0');

        $coord_present = $this->do_check(
            ($this->coordinate instanceof Coordinate),
            'coordinate not present or of incorrect class');
        if($coord_present && !$this->coordinate->is_valid()) {
            foreach($this->coordinate->messages as $key => $val) {
                $this->messages["coordinate: $key"] = true;
            }
        }

        $this->do_check(!empty($this->name), 'name is empty');

        return empty($this->messages);
    }

}


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


final class Places extends AbstractCollection {

    protected static $instance = null;

    public $table;

    protected function __construct() {
        $this->table = DB::table_name('places');
    }

    private function select_sql() {
        $coordinates_table = Coordinates::instance()->table;
        return "SELECT p.id, p.user_id, p.area_id, p.coordinate_id, p.name, c.lat, c.lon, p.created_at, p.updated_at
            FROM $this->table AS p
            JOIN $coordinates_table AS c
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
        // place validity is checked in the insert/update functions (because
        // coordinate might have to be created first.)
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
        $place->coordinate = Coordinates::instance()->save($place->coordinate);
        $place->coordinate_id = $place->coordinate->id;

        if(!$place->is_valid()) {
            return null;
        }

        $place_values = array(
            'user_id' => $place->user_id,
            'coordinate_id' => $place->coordinate_id,
            'area_id' => $place->area_id,
            'name' => $place->name
        );
        $place_id = DB::insert($this->table, $place_values);
        if($place_id == DB::BAD_ID) {
            throw new \Exception(
                "Could not insert place: ". var_export($place_values, true));
        }
        $place->id = $place_id;
        return $place_id;
    }

    // TODO: Some error handling, as transaction...
    private function db_update($place) {
        Coordinates::instance()->save($place->coordinate);

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
        Coordinates::instance()->delete($place->coordinate);

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
            $place->coordinate = new Coordinate();
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

        $place->coordinate = new Coordinate();
        $place->coordinate->id = $place->coordinate_id;

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
        if(empty($place->coordinate)) {
            $place->coordinate = new Coordinate();
        }
        $place->coordinate->lat = floatval($obj->lat);
        $place->coordinate->lon = floatval($obj->lon);
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