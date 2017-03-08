<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/abstract_collection.php');
require_once(dirname(__FILE__) . '/place.php');
require_once(dirname(__FILE__) . '/coordinates.php');
require_once(dirname(__FILE__) . '/../db.php');


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
        $where = $this->user_service->access_conditions();

        $current_area_id = $this->user_service->get_current_area_id();
        if($current_area_id == DB::BAD_ID) {
            throw new \Exception(
                "bad current area id. This should never happen.");
        } else {
            $where['area_id'] = $current_area_id;
        }

        $rows = DB::list($this->select_sql(), $where, $offset, $limit);

        $places = array();
        foreach($rows as $count => $row) {
            $place = $this->instance_from_array($row);
            $places[] = $place;
        }
        return $places;
    }

    public function get($id) {
        $where = array("p.id" => $id);
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
        if($id == DB::BAD_ID) {
            throw new DB_Exception("Error saving place.");
        }
        return $this->get($id);
    }

    protected function db_insert($place) {
        DB::start_transaction();

        $place->coordinate = Coordinates::instance()->save($place->coordinate);
        $place->coordinate_id = $place->coordinate->id;

        if(!$place->is_valid()) {
            $this->rollback_insert($place);
            return DB::BAD_ID;
        }

        $place_values = array(
            'user_id' => $place->user_id,
            'coordinate_id' => $place->coordinate_id,
            'area_id' => $place->area_id,
            'name' => $place->name
        );
        $place_id = DB::insert($this->table, $place_values);
        if($place_id == DB::BAD_ID) {
            $this->rollback_insert($place);
            throw new DB_Exception(
                "Could not insert place: ". var_export($place_values, true));
        } else {
            DB::commit_transaction();
        }
        $place->id = $place_id;
        return $place_id;
    }

    private function rollback_insert($place) {
        $place->id = DB::BAD_ID;
        $place->coordinate_id = DB::BAD_ID;
        if(!empty($place->coordinate)) {
            $place->coordinate->id = DB::BAD_ID;
        }
        DB::rollback_transaction();
    }

    // TODO: Some error handling, as transaction...
    protected function db_update($place) {
        DB::start_transaction();

        $coord_save = Coordinates::instance()->update($place->coordinate);

        if(!$coord_save || !$place->is_valid()) {
            debug_log("Failed to update invalid place.");
            DB::rollback_transaction();
            return null;
        }
        $place->coordinate_id = $place->coordinate->id;

        $place_values = array(
            'user_id' => $place->user_id,
            'coordinate_id' => $place->coordinate_id,
            'area_id' => $place->area_id,
            'name' => $place->name
        );
        $result = DB::update($this->table, $place->id, $place_values);
        if(!$result) {
            DB::rollback_transaction();
        } else {
            DB::commit_transaction();
        }
        return $result;
    }

    /**
     * @return Place|null The deleted object if successful, else null.
     */
    protected function db_delete($place) {
        DB::start_transaction();

        try {
            Coordinates::instance()->delete($place->coordinate);
        } catch (DB_Exception $e) {
            DB::rollback_transaction();
            // rethrow to indicate the mistaken delete
            throw $e;
        }

        $row_count = DB::delete($this->table, $place->id);
        if($row_count != 1) {
            DB::rollback_transaction();
            // reset the value that might have been destroyed on coordinate delete
            $place->coordinate->id = $place->coordinate_id;
            throw new DB_Exception("Error deleting place: " . $place->id . "\n");
        } else {
            DB::commit_transaction();
            $place->id = null;
            $place->coordinate_id = DB::BAD_ID;
            return $place;
        }
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

?>