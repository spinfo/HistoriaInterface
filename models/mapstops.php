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
    private function select_sql() {
        $sql = "
            SELECT
                m.id, m.tour_id, m.place_id, m.name, m.description,
                GROUP_CONCAT(join_table.post_id) AS post_ids
            FROM
                $this->table AS m,
                $this->join_posts_table AS join_table
            ";
        return $sql;
    }

    public function db_get($id) {
        $sql = $this->select_sql();
        $sql .= "WHERE m.id = join_table.mapstop_id AND m.id = %d";
        return DB::get_by_query($sql, array($id));
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

        $mapstop->tour_id = intval($array->tour_id);
        $mapstop->place_id = intval($array->place_id);
        $mapstop->name = strval($array->name);
        $mapstop->description = strval($array->description);

        $mapstop->post_ids = array();
        foreach(explode(',', $array->post_ids) as $id_str) {
            $mapstop->post_ids[] = intval($id_str);
        }
    }

    // insert connected post ids, return false on error
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
        if($result != count($mapstop->post_ids)) {
            debug_log("Wrong row count on mapstop-to-post-relation delete.");
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

}

?>