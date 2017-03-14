<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/../models/areas.php');
require_once(dirname(__FILE__) . '/../models/mapstops.php');
require_once(dirname(__FILE__) . '/../models/tour.php');
require_once(dirname(__FILE__) . '/../models/tours.php');

class ToursTest extends TestCase {

    public function test_create() {
        $tour = $this->helper->make_tour();

        $id_before = $this->helper->db_highest_id($this->table);
        $result = Tours::instance()->insert($tour);

        $this->assert($id_before < $this->helper->db_highest_id($this->table),
            "There should be a new tour on simple insert.");
        $this->assert(DB::valid_id($this->table, $result),
            "Should return a valid tour id on simple insert.");

        foreach($tour->coordinates as $c) {
            $this->assert(DB::valid_id($this->coords_table, $c->id),
                "Tour coordinate should have a valid id set.");
        }

        $this->tours[] = $tour;
    }

    public function test_bad_create() {
        // make tour and invalidate by invalidating the last coordinate
        $tour = $this->helper->make_tour();
        $tour->coordinates[count($tour->coordinates) - 1]->lat = null;

        // attempt the insert
        $t_id_before = $this->helper->db_highest_id($this->table);
        $c_id_before = $this->helper->db_highest_id($this->coords_table);
        Tours::instance()->insert($tour);
        $t_id_after = $this->helper->db_highest_id($this->table);
        $c_id_after = $this->helper->db_highest_id($this->coords_table);

        // check that nothing was inserted and no ids were set
        $this->assert($t_id_before == $t_id_after,
            "No tour should have been added on bad insert.");
        $this->assert($c_id_before == $c_id_after,
            "No coordinate should have been added on bad tour insert.");
        $this->assert($tour->id == DB::BAD_ID,
            "Tour should have bad id on bad insert.");

        foreach($tour->coordinates as $coordinate) {
            $this->assert($coordinate->id == DB::BAD_ID,
                "Coordinate should have bad id on bad tour insert.");
        }
    }

    public function test_get() {
        $tour = $this->tours[0];

        // make mapstops for the tour, the ids of which we can retrieve
        $tour->mapstop_ids = array();
        $mapstop = $this->helper->make_mapstop();
        $mapstop->tour_id = $tour->id;
        Mapstops::instance()->insert($mapstop);
        $tour->mapstop_ids[] = $mapstop->id;

        $from_db = Tours::instance()->get($tour->id, true, true);

        // test general model validity
        $this->assert_valid_model($from_db, $this->table, "get tour");

        // test that the returned values match
        $this->check_tour_values($from_db, $tour, "get tour");

        // clean up the the mapstop and the posts created for it, reset vars
        Mapstops::instance()->delete($mapstop);
        $tour->mapstop_ids = null;
        $this->helper->delete_wp_posts_created();
    }

    public function test_update() {
        $tour = $this->tours[0];
        $other = $this->helper->make_tour();

        // change tour's values to other's values and check that it worked
        Tours::instance()->update_values($tour, $other);
        $this->check_tour_values($tour, $other, "value update on tour");

        // change the tour's coordinates by removing one coordinate and adding
        // one of those of the other's (not persisted, so without id)
        $coord_removed = array_pop($tour->coordinates);
        $id_removed = array_pop($tour->coordinate_ids);
        array_push($tour->coordinates, array_pop($other->coordinates));

        // save highest coordinate id before update to assert insert
        $c_id_before = $this->helper->db_highest_id($this->coords_table);

        // update and check the return value
        $result = Tours::instance()->update($tour);
        $this->assert($result != false,
            "Should not return false on normal tour update");

        // test values on tour retrieved from the database
        $from_db = Tours::instance()->get($tour->id, true, true);
        $this->check_tour_values($from_db, $tour, "normal tour update");

        // test that old coord ids are no longer valid
        $this->assert(!DB::valid_id($this->coords_table, $coord_removed->id),
            "Tour update should have removed old coordinate.");

        // test that the new coordinate has an id and exists in the database
        $new_id = $from_db->coordinate_ids[count($tour->coordinate_ids) - 1];
        $c_id_after = $this->helper->db_highest_id($this->coords_table);
        $this->assert($c_id_after > $c_id_before,
            "Tour update should have created coordinate.");
        $this->assert($new_id == $c_id_after,
            "New coordinate should have the right id on tour update.");
    }

    public function do_test() {
        $this->setup();

        $this->test_create();
        $this->test_bad_create();
        $this->test_get();
        $this->test_update();
    }

    private function setup() {
        $this->name = "Tours Unit Test";
        $this->table = Tours::instance()->table;
        $this->join_table = Tours::instance()->join_coordinates_table;
        $this->coords_table = Coordinates::instance()->table;
        $this->tours = array();
    }

    private function check_tour_values($got, $expected, $test_name) {
        $this->assert($got->user_id == $expected->user_id, "user id should match ($test_name).");
        $this->assert($got->area_id == $expected->area_id, "area id should match ($test_name).");
        $this->assert($got->name == $expected->name, "name should match ($test_name).");
        $this->assert($got->intro == $expected->intro, "intro should match ($test_name).");
        $this->assert($got->type == $expected->type, "type should match ($test_name).");
        $this->assert($got->walk_length == $expected->walk_length, "walk_length should match ($test_name).");
        $this->assert($got->duration == $expected->duration, "duration should match ($test_name).");
        $this->assert($got->tag_what == $expected->tag_what, "tag_what should match ($test_name).");
        $this->assert($got->tag_where == $expected->tag_where, "tag_where should match ($test_name).");
        $this->assert($got->tag_when_start == $expected->tag_when_start, "tag_when_start should match ($test_name).");
        $this->assert($got->tag_when_end == $expected->tag_when_end, "tag_when_end should match ($test_name).");
        $this->assert($got->accessibility == $expected->accessibility, "accessibility should match ($test_name).");

        if(!empty($expected->coordinate_ids)) {
            // test for array equality (not identity) is sufficient
            $this->assert($got->coordinate_ids == $expected->coordinate_ids,
                "coordinate_ids should match ($test_name).");
        }
        if(!empty($expected->mapstop_ids)) {
            // test for array equality (not identity) is sufficient
            $this->assert($got->mapstop_ids == $expected->mapstop_ids,
                "mapstop_ids should match ($test_name).");
        }
    }

}


?>