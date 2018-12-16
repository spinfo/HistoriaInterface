<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/abstract_collection.php');
require_once(dirname(__FILE__) . '/mapstop.php');
require_once(dirname(__FILE__) . '/../db.php');

class Mapstops extends AbstractCollection {

    protected static $instance = null;

    public $table;

    public $join_posts_table;

    public function __construct() {
        $this->table = DB::table_name('mapstops');
        $this->join_posts_table = DB::table_name('mapstops_to_posts');
    }

    // sql to retrieve a single mapstop with post_ids joined in a single column
    // NOTE: The posts order is equal to their insertion order.
    private function select_sql() {
        $sql = "
            SELECT
                m.id, m.tour_id, m.place_id, m.name, m.description,
                GROUP_CONCAT(m2p.post_id ORDER BY m2p.id ASC) AS post_ids
            FROM
                $this->table AS m,
                $this->join_posts_table AS m2p
            ";
        return $sql;
    }

    public function db_get($id) {
        $sql = $this->select_sql();
        $sql .= "WHERE m.id = m2p.mapstop_id AND m.id = %d";
        $result = DB::get_by_query($sql, array($id));
        return (is_null($result->id)) ? null : $result;
    }

    protected function db_insert($mapstop) {
        if(!$mapstop->is_valid()) {
            debug_log("Not inserting invalid mapstop. Messages:");
            $mapstop->debug_log_messages();
            return DB::BAD_ID;
        }
        // gather the values to insert
        $values = $this->db_values($mapstop);

        // determine the next position for this mapstop
        $position = $this->db_next_position($mapstop->tour_id);
        $values['position'] = $position;

        DB::start_transaction();
        // actually do the insert of the mapstop and it's posts
        $result_id = DB::insert($this->table, $values);
        $result_posts_insert = $this->db_insert_post_ids($mapstop, $result_id);

        // check for any errors
        if($result_id == DB::BAD_ID || !$result_posts_insert) {
            debug_log("Error on inserting mapstop. Rolling back.");
            DB::rollback_transaction();
            $mapstop->id = DB::BAD_ID;
            return DB::BAD_ID;
        }

        // if we get to this point we are in the clear
        DB::commit_transaction();
        $mapstop->id = $result_id;
        return $result_id;
    }

    protected function db_update($mapstop) {
        if(!$mapstop->is_valid()) {
            debug_log("Not uptdating invalid mapstop. Messages:");
            $mapstop->debug_log_messages();
            return false;
        }
        $values = $this->db_values($mapstop);

        // as a mapstop's tour_id may never be updated, remove the key
        unset($values['tour_id']);

        DB::start_transaction();
        // update the mapstop, then simply delete and reinsert the post ids
        // NOTE: This action determines the order of the posts.
        $result = DB::update($this->table, $mapstop->id, $values);
        if($result != false) {
            $result_delete = $this->db_delete_post_ids($mapstop);
            if($result_delete) {
                $result_reinsert = $this->db_insert_post_ids(
                    $mapstop, $mapstop->id);
            }
        }

        // if everything is fine: commit, else reset
        if($result && $result_delete && $result_reinsert) {
            DB::commit_transaction();
            return $result;
        } else {
            debug_log("Error updating mapstop. Rolling back.");
            DB::rollback_transaction();
            return false;
        }
    }

    protected function db_delete($mapstop) {
        DB::start_transaction();

        // do the deletes, the join relation to posts is automatically
        // deleted with a database constraint
        $result = DB::delete_single($this->table, $mapstop->id);

        // re-order the tour's mapstops' positions
        $result_reorder = Tours::instance()->update_mapstop_positions(
            $mapstop->tour_id, null);

        if(($result === 1) && $result_reorder) {
            DB::delete(Scenes::instance()->join_mapstops_table, array('mapstop_id' => $mapstop->id));
            DB::commit_transaction();
            $mapstop->id = DB::BAD_ID;
            $mapstop->post_ids = null;
            return $mapstop;
        } else {
            debug_log("Error deleting mapstop (id: '$id'). Rolling back.");
            DB::rollback_transaction();
            return null;
        }
    }

    protected function instance_from_array($array) {
        $array = (object) $array;

        $mapstop = new Mapstop();

        $this->update_values($mapstop, $array);
        $this->set_abstract_model_values($mapstop, $array);

        return $mapstop;
    }

    protected function db_values($mapstop) {
        $result = array(
            'tour_id' => $mapstop->tour_id,
            'place_id' => $mapstop->place_id,
            'name' => $mapstop->name,
            'description' => $mapstop->description,
        );
        return $result;
    }

    public function update_values($mapstop, $array) {
        $array = (object) $array;

        if(isset($array->tour_id)) {
            $mapstop->tour_id = intval($array->tour_id);
        }
        $mapstop->place_id = intval($array->place_id);
        $mapstop->name = strval($array->name);
        $mapstop->description = strval($array->description);
        $mapstop->type = strval($array->type);

        if(is_array($array->post_ids)) {
            $mapstop->post_ids = array_map('intval', $array->post_ids);
        } else if(is_string($array->post_ids)) {
            $mapstop->post_ids = array();
            foreach(explode(',', $array->post_ids) as $id_str) {
                $mapstop->post_ids[] = intval($id_str);
            }
        }
    }

    // Returns an array of all post ids that are connected to mapstops
    public function get_linked_post_ids() {
        $sql =
            "SELECT GROUP_CONCAT(post_id) AS ids FROM $this->join_posts_table";
        $result = DB::get($sql);
        if(empty($result) || !isset($result->ids) || empty($result->ids)) {
            debug_log("Could not retrieve any post ids joined to mapstops.");
            return array();
        } else {
            $ids = explode(',', $result->ids);
            return array_map('intval', $ids);
        }
    }

    /**
     * Retrieve all places that the mapstop could possibly take. These are all
     * places in the same area, that are not taken by other mapstops in the same
     * tour.
     *
     * NOTE: This includes the place that the mapstop currently occupies.
     */
    public function get_possible_places($mapstop) {
        // get all places in the area
        $tour = Tours::instance()->get($mapstop->tour_id);
        $places = Places::instance()->list_by_area($tour->area_id);
        // get place ids taken by the other mapstops in the tour
        $taken = array();
        $sql = "SELECT place_id FROM $this->table WHERE tour_id = $tour->id";
        $rows = DB::list_by_query($sql);
        foreach ($rows as $row) {
            $taken[] = $row->place_id;
        }
        // only those places are options, that are not used in the same tour
        $places = array_filter($places, function($p) use($mapstop, $taken) {
            return ($p->id == $mapstop->place_id) || !in_array($p->id, $taken);
        });
        return $places;
    }

    // insert connected post ids, return false on error
    // NOTE: This action determines the order of the posts (= insertion order).
    private function db_insert_post_ids($mapstop, $mapstop_id) {
        foreach($mapstop->post_ids as $post_id) {
            $values = array('mapstop_id' => $mapstop_id, 'post_id' => $post_id);
            $result = DB::insert($this->join_posts_table, $values);
            if($result == DB::BAD_ID) {
                debug_log("Error on inserting mapstop-to-post-relation.");
                return false;
            }
        }
        return true;
    }

    // remove connected post ids, return false on any error
    private function db_delete_post_ids($mapstop) {
        $where = array('mapstop_id' => $mapstop->id);
        $result = DB::delete($this->join_posts_table, $where);
        if($result === false) {
            debug_log("Error deleting mapstop to post relation.");
            return false;
        }
        return true;
    }


    /**
     * Returns the next position value a new mapstop should have within the
     * given tour. Position values are always positive and start at 1.
     *
     * @return  int The next biggest position value or false on error.
     */
    private function db_next_position($tour_id) {
        $select = "SELECT MAX(position) AS maxpos FROM $this->table";
        $result = DB::get($select, array('tour_id' => $tour_id));

        if(is_null($result) || !property_exists($result, 'maxpos')) {
            debug_log("Bad position lookup for tour_id: '$tour_id'");
            return false;
        } else {
            // there is no mapstop for the tour yet, so return 1.
            if(is_null($result->maxpos)) {
                return 1;
            }
            // there already is a position for the tour, return increment.
            else {
                return $result->maxpos + 1;
            }
        }
    }

    /**
     * @param $mapstop
     * @return bool
     * @throws DB_Exception
     */
    public function fetch_type_for_mapstop($mapstop) {
        $select = "SELECT type FROM " . Scenes::instance()->join_mapstops_table;
        $result = DB::get($select, array('mapstop_id' => $mapstop->id));

        if(is_null($result) || !property_exists($result, 'type')) {
            debug_log("Bad type lookup for mapstop_id: '$mapstop->id'");
            return false;
        }
        $mapstop->type = $result->type;
        return $mapstop;
    }
}

?>