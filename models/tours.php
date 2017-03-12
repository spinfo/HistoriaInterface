<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/abstract_collection.php');
require_once(dirname(__FILE__) . '/tour.php');
require_once(dirname(__FILE__) . '/mapstops.php');
require_once(dirname(__FILE__) . '/../db.php');

class Tours extends AbstractCollection {

    protected static $instance = null;

    public $table;

    public $join_coordinates_table;

    public function __construct() {
        $this->table = DB::table_name('tours');
        $this->join_coordinates_table = DB::table_name('tours_to_coordinates');
    }

    // retrieves just the values from the tour's table with coordinate's and
    // mapstop ids
    // TODO: This is untested, may change soon anyway, test or remove!
    public function get($id, $get_mapstop_ids = true, $get_coord_ids = true) {
        $sql = "SELECT * FROM $this->table WHERE id = %d";
        $result = DB::get_by_query($sql, array($id));

        if(!empty($result) && $get_mapstop_ids) {
            $result->mapstop_ids = array();
            $sql = "SELECT id FROM " . Mapstops::instance()->table;
            $m_ids = DB::list($sql, array('tour_id' => $id));
            foreach($m_ids as $id_result) {
                $result->mapstop_ids[] = intval($id_result->id);
            }
        }

        if(!empty($result) && $get_coord_ids) {
            $result->coordinate_ids = array();
            $sql = "SELECT id FROM $this->join_coordinates_table";
            $c_ids = DB::list($sql, array('tour_id' => $id));
            foreach($c_ids as $id_result) {
                $result->coordinate_ids[] = intval($id_result->id);
            }
        }

        return $result;
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
        $result_id = DB::insert($this->table, $values);

        // insert tour's track with join relation
        if($result_id != DB::BAD_ID && !empty($tour->coordinates)) {
            $result_coords = $this->db_insert_coordinates($tour, $result_id);
        }

        // check for errors and abort if there are any
        if($result_id == DB::BAD_ID || !$result_coords) {
            debug_log("Errors while saving tour. Rolling back.");
            $tour->id = DB::BAD_ID;
            foreach($tour->coordinates as $coord) {
                $coord->id = DB::BAD_ID;
            }
            DB::rollback_transaction();
            return DB::BAD_ID;
        }

        // at this point we may commit the results and return
        DB::commit_transaction();
        $tour->id = $result_id;
        return $tour->id;
    }

    protected function db_update($tour) {}

    protected function db_delete($tour) {}

    private function db_values($tour) {
        $values = array(
            'area_id' => strval($tour->area_id),
            'user_id' => strval($tour->user_id),
            'name' => strval($tour->name),
            'intro' => strval($tour->intro),
            'type' => strval($tour->type),
            'walk_length' => intval($tour->walk_length),
            'duration' => intval($tour->duration),
            'tag_what' => strval($tour->tag_what),
            'tag_where' => strval($tour->tag_where),
            'tag_when_start' => floatval($tour->tag_when_start),
            'accessibility' => strval($tour->accessibility)
        );
        if(!is_null($tour->tag_when_end)) {
            $values['tag_when_end'] = floatval($tour->tag_when_end);
        }
        return $values;
    }

    // insert the tour's coordinates with join relation, return true on success
    // and false on the first error (do as little as possible)
    private function db_insert_coordinates($tour, $tour_id) {
        foreach($tour->coordinates as $coord) {
            try {
                $coord_id = Coordinates::instance()->insert($coord);
                if($coord_id != DB::BAD_ID) {
                    $values =
                        array( 'coordinate_id' => $coord_id, 'tour_id' => $tour_id);
                    $join_id = DB::insert($this->join_coordinates_table, $values);
                    if($join_id == DB::BAD_ID) {
                        return false;
                    }
                } else {
                    return false;
                }
            } catch(DB_Exception $e) {
                return false;
            }
        }
        return true;
    }

}

?>