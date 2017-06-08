<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/abstract_collection.php');
require_once(dirname(__FILE__) . '/tour.php');
require_once(dirname(__FILE__) . '/coordinates.php');
require_once(dirname(__FILE__) . '/mapstops.php');
require_once(dirname(__FILE__) . '/places.php');
require_once(dirname(__FILE__) . '/../db.php');
require_once(dirname(__FILE__) . '/../post_service.php');

class Tours extends AbstractCollection {

    protected static $instance = null;

    public $table;

    public $join_coordinates_table;

    public function __construct() {
        $this->table = DB::table_name('tours');
        $this->join_coordinates_table = DB::table_name('tours_to_coordinates');
    }

    public function list_by_area($area_id) {
        if(empty($area_id) || $area_id == DB::BAD_ID) {
            $area_id = UserService::instance()->get_current_area_id();
            if($area_id == DB::BAD_ID) {
                throw new \Exception(
                    "bad current area id. This should never happen.");
            }
        }
        $where['area_id'] = $area_id;

        $sql = "SELECT * FROM $this->table";
        $rows = DB::list($sql, $where, 0, PHP_INT_MAX);

        $tours = array();
        foreach($rows as $count => $row) {
            $tour = $this->instance_from_array($row);
            $tours[] = $tour;
        }
        return $tours;
    }

    /**
     * Overrides AbstractCollection->get() to give the possibility of querying
     * mapstop and coordinate ids.
     * TODO: There must be some better way...
     */
    public function get($id, $get_mapstop_ids = false, $get_coord_ids = false) {
        $sql = "SELECT * FROM $this->table WHERE id = %d";
        $result = DB::get_by_query($sql, array($id));

        if(empty($result)) {
            return null;
        }

        if($get_mapstop_ids) {
            $result->mapstop_ids = $this->db_get_mapstops_ids($id);
        }

        if($get_coord_ids) {
            $result->coordinate_ids = $this->db_get_coordinate_ids($id);
        }

        return $this->instance_from_array($result);
    }


    protected function db_insert($tour) {
        if(!$tour->is_valid()) {
            debug_log("Not inserting invalid tour. Messages:");
            $tour->debug_log_messages();
            return DB::BAD_ID;
        }
        // gather the values to insert
        $values = $this->db_values($tour);

        DB::start_transaction();
        $tour_id = DB::insert($this->table, $values);
        if($tour_id == DB::BAD_ID) {
            debug_log("Errors while saving tour. Rolling back.");
            $this->rollback_insert($tour);
            return DB::BAD_ID;
        }

        // insert tour's track (coordinates) with join relation
        $tour->coordinate_ids = array();
        if($tour_id != DB::BAD_ID && !empty($tour->coordinates)) {
            foreach($tour->coordinates as $coordinate) {
                $result = Coordinates::instance()->save($coordinate);
                if(empty($result)) {
                    debug_log("Error saving coordinate for tour.");
                    $this->rollback_insert($tour);
                    return DB::BAD_ID;
                } else {
                    $tour->coordinate_ids[] = $result->id;
                }
            }
            $result_coords = $this->db_insert_coordinate_joins($tour, $tour_id);
            if(!$result_coords) {
                debug_log("Error on saving tour's coordinates. Rolling back.");
                $this->rollback_insert($tour);
                return DB::BAD_ID;
            }
        }

        // at this point we may commit the results and return
        DB::commit_transaction();
        $tour->id = $tour_id;
        return $tour->id;
    }

    protected function db_update($tour) {
        // check if the tour is valid and abort if it isn't
        if(!$tour->is_valid()) {
            debug_log("Not updating invalid tour. Messages: ");
            $tour->debug_log_messages();
            return false;
        }

        DB::start_transaction();

        // save all linked coordinate ids to restore them later if neccessary
        $old_coordinate_ids = $tour->coordinate_ids;
        $old_coordinates = $tour->coordinates;

        // update linked coordinates
        $coords_update_ok = $this->db_update_coordinates($tour);
        if($coords_update_ok) {
            // update the tour itself
            $values = $this->db_values($tour);
            $tour_update_ok = DB::update($this->table, $tour->id, $values);
        }

        if($coords_update_ok && $tour_update_ok) {
            // commit the transaction, update coordinate ids and return
            DB::commit_transaction();
            return true;
        } else {
            // reset old id values, rollback and return false
            $tour->coordinate_ids = $old_coordinate_ids;
            $tour->coordinates = $old_coordinates;
            debug_log("Tour update failed. Rolling back.");
            DB::rollback_transaction();
            return false;
        }
    }

    protected function db_delete($tour) {
        // save old coordinate values to reproduce if needed
        $coord_ids = $tour->coordinate_ids;
        $coords = $tour->coordinates;

        // start transaction to easily reverse the delete
        DB::start_transaction();

        // delete coordinates
        $tour->coordinate_ids = array();
        $tour->coordinates = array();
        foreach ($coord_ids as $c_id) {
            $coord = Coordinates::instance()->get($c_id);
            $result = Coordinates::instance()->delete($coord);
            if(empty($result)) {
                $this->rollback_delete($tour, $coords, $coord_ids,
                    "Bad coordinate delete");
                return null;
            } else {
                // add returned invalid coordinate to tour for return
                $tour->coordinates[] = $result;
            }
        }

        // a where condition used for some deletes
        $where_tour = array('tour_id' => $tour->id);

        // delete joins
        $n = count($coord_ids);
        $rows_deleted = DB::delete($this->join_coordinates_table, $where_tour);
        if($n != $rows_deleted) {
            $this->rollback_delete($tour, $coords, $coord_ids,
                "Bad coordinate join delete, got: $rows_deleted, expected: $n");
            return null;
        }
        // delete mapstops
        $rows_deleted = DB::delete(Mapstops::instance()->table, $where_tour);
        if($rows_deleted === false) {
            $this->rollback_delete($tour, $coords, $coord_ids,
                "Bad mapstop delete for tour.");
            return null;
        }

        // delete the tour, invalidate and return
        $result = DB::delete_single($this->table, $tour->id);
        if($result != 1) {
            $this->rollback_delete($tour, $coords, $coord_ids,
                "Bad tour delete.");
            return null;
        }
        DB::commit_transaction();
        $tour->id = DB::BAD_ID;
        $tour->mapstop_ids = array();
        return $tour;
    }

    private function rollback_delete($tour, $old_coords, $old_coord_ids, $msg) {
        $tour->coordinate_ids = $old_coord_ids;
        $tour->coordinates = $old_coords;
        DB::rollback_transaction();
        throw new DB_Exception("Can't delete tour: $msg.");
    }

    protected function instance_from_array($array) {
        $array = (object) $array;

        $tour = new Tour();

        $this->update_values($tour, $array);
        $this->set_abstract_model_values($tour, $array);

        return $tour;
    }

    public function update_values($tour, $array) {
        $array = (object) $array;

        if(!empty($array->area_id)) {
            $tour->area_id = intval($array->area_id);
        }
        if(!empty($array->user_id)) {
            $tour->user_id = intval($array->user_id);
        }
        $tour->name = strval($array->name);
        $tour->intro = strval($array->intro);
        $tour->type = strval($array->type);
        $tour->walk_length = intval($array->walk_length);
        $tour->duration = intval($array->duration);
        $tour->tag_what = strval($array->tag_what);
        $tour->tag_where = strval($array->tag_where);
        if(!is_null($array->tag_when_start)) {
            $tour->tag_when_start = floatval($array->tag_when_start);
            $tour->tag_when_start_format = strval($array->tag_when_start_format);
        }
        // end date is either a float, when explicitly given or null
        if(!is_null($array->tag_when_end)) {
            $tour->tag_when_end = floatval($array->tag_when_end);
            $tour->tag_when_end_format = strval($array->tag_when_end_format);
        } else {
            $tour->tag_when_end = null;
            $tour->tag_when_end_format = '';
        }
        $tour->accessibility = strval($array->accessibility);
        $tour->author = strval($array->author);

        if(!is_null($array->coordinate_ids)) {
            $tour->coordinate_ids = array_map('intval', $array->coordinate_ids);
        }
        if(!is_null($array->mapstop_ids)) {
            $tour->mapstop_ids = array_map('intval', $array->mapstop_ids);
        }
    }

    // TODO: Check that this really updates coordinates and does not always
    // reinsert
    public function update_track($tour, $track_values) {
        if(!empty($track_values)) {
            $coords_to_set = array();
            foreach ($track_values as $c) {
                $new_c = new Coordinate();
                Coordinates::instance()->update_values($new_c, $c);
                $new_c->id = (empty($c->id)) ? DB::BAD_ID : intval($c->id);
                $coords_to_set[] = $new_c;
            }
            $tour->coordinates = $coords_to_set;
        }
    }

     /**
     * Update the tour's mapstop positions to match the order in the supplied
     * array of mapstop ids.
     *
     * The mapstop ids supplied must match the actual tour's mapstop ids in
     * count and value (though obviously not in position).
     *
     * If no mapstop ids are supplied, this will simply "fix" the positions to
     * be a continuous range beginning at 1.
     *
     * @return bool Whether the update was successful or not
     */
    public function update_mapstop_positions($tour_id, $mapstop_ids = null) {
        // if the supplied ids are empty, return immediately
        if(is_array($mapstop_ids) && empty($mapstop_ids)) {
            return true;
        }
        // Get the tour's current ids to have a value to compare against.
        $old_ids = $this->db_get_mapstops_ids($tour_id);
        // if no ids are given as a param, we will simply fix the current order
        if(is_null($mapstop_ids)) {
            $mapstop_ids = $old_ids;
        }
        // Complain and return if the update assumes different ids.
        if(!empty(array_diff($old_ids, $mapstop_ids)) ||
            !empty(array_diff($mapstop_ids, $old_ids))) {
            debug_log("Bad input for tour's mapstop positions.");
            return false;
        }
        // if there are no old ids, no update is necessary
        if(empty($old_ids)) {
            return true;
        }
        // Build a case-when sql that does the update in a single statement.
        $sql = "UPDATE " . Mapstops::instance()->table;
        $sql .= " SET position = CASE id";
        for($i = 0; $i < count($mapstop_ids); $i++) {
            $sql .= " WHEN %d THEN " . ($i + 1);
        }
        $sql .= " ELSE position END WHERE tour_id = %d";
        // we can directly use the mapstop ids as the values to be inserted,
        // appending the tour_id for the where-clause
        array_push($mapstop_ids, $tour_id);
        $result = DB::query($sql, $mapstop_ids);
        // if any integer >= 0 is returned. the query did not error
        return (is_int($result) && $result >= 0);
    }

    // Get objects related to the tour and set them on it.
    public function set_related_objects_on($tour) {
        $tour->coordinates = array();
        foreach ($tour->coordinate_ids as $id) {
            $tour->coordinates[] = Coordinates::instance()->get($id);
        }
        $tour->mapstops = array();
        foreach ($tour->mapstop_ids as $id) {
            $mapstop = Mapstops::instance()->get($id);
            if(!empty($mapstop)) {
                $mapstop->place =
                    Places::instance()->get($mapstop->place_id);
                $tour->mapstops[] = $mapstop;
            }
        }
        $tour->area = Areas::instance()->get($tour->area_id);
    }

    private function db_values($tour) {
        $vals = array(
            'area_id' => intval($tour->area_id),
            'user_id' => intval($tour->user_id),
            'name' => strval($tour->name),
            'intro' => strval($tour->intro),
            'type' => strval($tour->type),
            'walk_length' => intval($tour->walk_length),
            'duration' => intval($tour->duration),
            'tag_what' => strval($tour->tag_what),
            'tag_where' => strval($tour->tag_where),
            'tag_when_start' => floatval($tour->tag_when_start),
            'tag_when_start_format' => strval($tour->tag_when_start_format),
            'accessibility' => strval($tour->accessibility),
            'author' => strval($tour->author)
        );
        if(!is_null($tour->tag_when_end)) {
            $vals['tag_when_end'] = floatval($tour->tag_when_end);
            $vals['tag_when_end_format'] = strval($tour->tag_when_end_format);
        } else {
            $vals['tag_when_end'] = null;
            $vals['tag_when_end_format'] = '';
        }
        return $vals;
    }

    // retrieve ids of all coordinates' ids joined to the tour in the database
    private function db_get_coordinate_ids($tour_id) {
        $result = array();
        $sql = "SELECT coordinate_id FROM $this->join_coordinates_table";
        $id_results = DB::list($sql, array('tour_id' => $tour_id));
        foreach($id_results as $id_result) {
            $result[] = intval($id_result->coordinate_id);
        }
        return $result;
    }

    // retrieve all mapstops' ids joined to the tour in the database in the
    // order given by their "position" value
    private function db_get_mapstops_ids($tour_id) {
        $result = array();
        $sql = "SELECT id FROM " . Mapstops::instance()->table;
        $sql .= " WHERE tour_id = %d ORDER BY position ASC";
        $m_ids = DB::list_by_query($sql, array($tour_id));
        foreach($m_ids as $id_result) {
            $result[] = intval($id_result->id);
        }
        return $result;
    }

    // Update the tour's coordinates based on the objects in it's coordinates
    // array. Return false on any error else true.
    // This will alter the $tour->coordinates and $tour->coordinate_ids fields
    // if successful.
    private function db_update_coordinates($tour) {
        if(empty($tour->coordinates)) {
            return true;
        }
        $new_coordinate_ids = array();
        $new_coordinates = array();
        // update all coordinates and update the tour's coordinate_ids
        foreach($tour->coordinates as $coordinate) {
            $result = Coordinates::instance()->save($coordinate);
            if(is_null($result)) {
                debug_log("Error saving coordinate for tour update.");
                return false;
            }
            $new_coordinate_ids[] = $coordinate->id;
            $new_coordinates[] = $result;
        }

        // get all ids of coordinates linked to this tour
        $old_coordinate_ids = $this->db_get_coordinate_ids($tour->id);

        // determine the coordinates that have to be deleted and delete
        $to_delete = array_diff($old_coordinate_ids, $new_coordinate_ids);
        try {
            foreach ($to_delete as $coord_id) {
                $coord = Coordinates::instance()->get($coord_id);
                // delete the coordinate
                $res_coord = Coordinates::instance()->delete($coord);
                // delete the join relation
                $where = array(
                    'tour_id' => $tour->id,
                    'coordinate_id' => $coord_id
                );
                $res_join = DB::delete($this->join_coordinates_table, $where);
                // check for errors
                if(empty($res_coord) || $res_join != 1) {
                    throw new DB_Exception("Error deleting coord or join row.");
                }
            }
        } catch(DB_Exception $e) {
            debug_log("Error updating tour's coordinates: " . $e->getMessage());
            return false;
        }

        // determine the coordinate ids that need new joins created
        $to_insert = array_diff($new_coordinate_ids, $old_coordinate_ids);
        foreach($to_insert as $coord_id) {
            $join_id = $this->db_insert_single_coordinate_join($tour->id,
                $coord_id);
            if($join_id == DB::BAD_ID) {
                debug_log("Error creating tour to coordinate join.");
                return false;
            }
        }

        // everything went okay, so set the new coordinate values on the tour
        // object and return
        $tour->coordinates = $new_coordinates;
        $tour->coordinate_ids = $new_coordinate_ids;
        return true;
    }

    // Insert a single coordinate join. Return the join's id or DB::BAD_ID on
    // any error.
    private function db_insert_single_coordinate_join($tour_id, $coord_id) {
        try {
            $values = array(
                'coordinate_id' => $coord_id, 'tour_id' => $tour_id);
            $join_id = DB::insert($this->join_coordinates_table, $values);
            return $join_id;
        } catch(DB_Exception $e) {
            debug_log('Error creating tour to coord join: ' . $e->getMessage());
            return DB::BAD_ID;
        }
    }

    // Insert the tours to coordinates join relations, return true on success
    // and false on the first error (do as little as possible).
    // The caller should make sure that this happens within a transaction.
    private function db_insert_coordinate_joins($tour, $tour_id) {
        // iterate over coordinates
        foreach($tour->coordinates as $coord) {
            $join_id = $this->db_insert_single_coordinate_join($tour_id,
                $coord->id);
            if($join_id == DB::BAD_ID) {
                return false;
            }
        }
        return true;
    }

    // reset id values created and rollback the current database transaction
    private function rollback_insert($tour) {
        $tour->id = DB::BAD_ID;
        $tour->coordinate_ids = array();
        foreach($tour->coordinates as $coord) {
            $coord->id = DB::BAD_ID;
        }
        DB::rollback_transaction();
    }

}

?>