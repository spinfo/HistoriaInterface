<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/../../models/mapstops.php');
require_once(dirname(__FILE__) . '/../../models/mapstop.php');
require_once(dirname(__FILE__) . '/../../models/tours.php');
require_once(dirname(__FILE__) . '/../../models/places.php');

require_once(dirname(__FILE__) . '/../../logging.php');

class MapstopsTest extends TestCase {

    public function test_create() {
        // create a mapstop and manually set the tour id, so we can check for
        // the correct setting of the mapstop.position value for an empty tour
        $mapstop1 = $this->helper->make_mapstop();
        $mapstop1->tour_id = $this->tour->id;

        $mapstop2 = $this->helper->make_mapstop();
        $mapstop2->tour_id = $this->tour->id;

        // Test a normal create and a create without posts assigned
        $this->test_single_good_create($mapstop1, "normal mapstop");
        $this->mapstops[] = $mapstop1;

        $mapstop2->post_ids = array();
        $this->test_single_good_create($mapstop2, "mapstop without post ids");
        $this->mapstops[] = $mapstop2;

        // test that position values were correctly assigned to the tour and
        // to the mapstops
        $tour = Tours::instance()->get($this->tour->id, true);
        $expected_ids = array($mapstop1->id, $mapstop2->id);
        $this->assert($tour->mapstop_ids === $expected_ids,
            "mapstops' ids should match the tour's mapstop_ids.");
    }

    private function test_single_good_create($mapstop, $test_name) {
        $id_before = $this->helper->db_highest_id($this->table);
        $id = Mapstops::instance()->insert($mapstop);
        $id_after = $this->helper->db_highest_id($this->table);

        $this->assert(is_int($id) && $id > 0,
            "Should return id value on mapstop insert ($test_name).");
        $this->assert($id_before < $id_after,
            "There should be a new mapstop in the mapstops table ($test_name).");
        $this->assert($id == $id_after,
            "The reported id should be the right id ($test_name).");
        $this->assert($id == $mapstop->id,
            "The right id should be set on the mapstop object ($test_name).");
    }

    public function test_bad_create() {
        // create a new one for this, will not be persisted
        $mapstop = $this->helper->make_mapstop();

        $val = $mapstop->name;
        $mapstop->name = "";

        $this->test_single_bad_create($mapstop, "mapstop without name");
        $mapstop->name = $val;

        $val = $mapstop->description;
        $mapstop->description = "";
        $this->test_single_bad_create($mapstop, "mapstop without description");
        $mapstop->description = $val;

        $val = $mapstop->place_id;
        $mapstop->place_id = 0;
        $this->test_single_bad_create($mapstop, "mapstop with invalid place_id");
        $mapstop->place_id = $val;

        $val = $mapstop->tour_id;
        $mapstop->tour_id = "";
        $this->test_single_bad_create($mapstop, "mapstop with invalid tour_id");
        $mapstop->tour_id = $val;

        $mapstop->post_ids[] = $this->mapstops[0]->post_ids[0];
        $this->test_single_bad_create($mapstop,
            "mapstop with other mapstops's post_id included");
    }

    private function test_single_bad_create($mapstop, $test_name) {
        $id_before = $this->helper->db_highest_id($this->table);
        $id = Mapstops::instance()->insert($mapstop);

        $this->assert_invalid_id($id, $test_name);
        $this->assert($id_before == $this->helper->db_highest_id($this->table),
            "No mapstop should have been added to the table ($test_name).");
    }

    public function test_get() {
        $mapstop = $this->mapstops[0];
        // retrieve the newly created mapstop and test it's values
        $from_db = Mapstops::instance()->get($mapstop->id);

        // check general model validity
        $this->assert_valid_model($from_db, $this->table, "get mapstop");

        // check the mapstop values
        $this->test_mapstop_values($from_db, $mapstop, "get mapstop");
    }

    public function test_update() {
        $mapstop = $this->mapstops[0];

        // make a mapstop with different place_id, keep, tour_id the same
        // (updating a mapstop's tour id is not a real use case)
        $other = $this->helper->make_mapstop(true);
        $other->tour_id = $mapstop->tour_id;

        $mapstop->place_id = $other->place_id;
        $mapstop->name = $other->name;
        $mapstop->description = $other->description;
        $mapstop->post_ids = $other->post_ids;

        $result = Mapstops::instance()->update($mapstop);

        $this->assert($result != false,
            "Should return not false on normal mapstop update.");

        $from_db = Mapstops::instance()->get($mapstop->id);

        $this->test_mapstop_values($from_db, $other, "from_db to other");
        $this->test_mapstop_values($from_db, $mapstop, "from_db to normal");
    }

    public function test_bad_update() {
        $mapstop = $this->mapstops[0];

        // make a clone to test the bad update doesn't change anything
        $clone = clone $mapstop;

        $val = $mapstop->name;
        $mapstop->name = "";
        $this->test_single_bad_update($mapstop, $clone, "mapstop without name");
        $mapstop->name = $val;

        $val = $mapstop->description;
        $mapstop->description = "";
        $this->test_single_bad_update($mapstop, $clone,
            "mapstop without description");
        $mapstop->description = $val;

        $val = $mapstop->place_id;
        $mapstop->place_id = 0;
        $this->test_single_bad_update($mapstop, $clone,
            "mapstop with invalid place_id");
        $mapstop->place_id = $val;

        // check that a valid but changed tour_id is ignored on update
        $val = $mapstop->tour_id;
        $mapstop->tour_id = Tours::instance()->first_id();
        $this->assert($val != $mapstop->tour_id, "Should test different tours");
        Mapstops::instance()->update($mapstop);
        $from_db = Mapstops::instance()->get($mapstop->id);
        $this->assert($val === $from_db->tour_id,
            "Should not be able to update a mapstop's tour.");
        $mapstop->tour_id = $val;

        // Create another mapstop to test that we cannot update with the other's
        // post_id assigned
        $other = $this->helper->make_mapstop();
        // set tour id to include this mapstop in the tests for pos in the tour
        $other->tour_id = $this->tour->id;
        Mapstops::instance()->insert($other);
        $val = $mapstop->post_ids;
        array_push($mapstop->post_ids, $other->post_ids[0]);
        $this->test_single_bad_update($mapstop, $clone,
            "mapstop with other mapstops's post_id included");
        $mapstop->post_ids = $val;

        $this->mapstops[] = $other;
    }


    private function test_single_bad_update($mapstop, $good_clone, $test_name) {
        $result = Mapstops::instance()->update($mapstop);

        $this->assert($result == false,
            "Should return false on bad update ($test_name).");

        $from_db = Mapstops::instance()->get($mapstop->id);
        $this->test_mapstop_values($from_db, $good_clone,
            "Should not update values on bad update - $test_name");
    }

    private function test_delete() {
        $ids = array_map(function($m) { return $m->id; }, $this->mapstops);
        $tours_ids = Tours::instance()->get($this->tour->id, true)->mapstop_ids;
        $this->assert($ids === $tours_ids,
            "Before deletion test, our ids and the tour's ids should match");

        // test delete for each mapstop we created
        foreach($this->mapstops as $mapstop) {
            // save the id
            $id = $mapstop->id;
            // do the delete
            $result = Mapstops::instance()->delete($mapstop);

            $this->assert(!DB::valid_id($this->table, $id),
                "Mapstop should no longer be in table.");
            $this->assert($result instanceof Mapstop,
                "Should return a mapstop on normal delete.");
            $this->assert($result->id == DB::BAD_ID,
                "Deleted mapstop should have bad id value set.");
            $this->assert(is_null($this->post_ids),
                "Post ids should be null on deleted mapstop.");

            // test that the joined posts are no longer there
            $select = "SELECT * FROM $this->join_table";
            $post_ids = DB::list($select, array('mapstop_id' => $id), 0, 1);
            $this->assert(empty($post_ids),
                "Post ids should be deleted on mapstop delete.");

            // test that the tour's ids and our id's still match
            array_shift($ids);
            $tours_ids = Tours::instance()->get($this->tour->id, true)->mapstop_ids;
            $this->assert($ids === $tours_ids,
                "After a delete our ids and the tour's ids should match.");
        }
    }

    public function do_test() {
        $this->setup();

        $this->test_create();
        $this->test_bad_create();
        $this->test_get();
        $this->test_update();
        $this->test_bad_update();
        $this->test_delete();

        // cleanup, delete all the posts that the helper created as well as the
        // mapstops's tour.
        $this->helper->delete_wp_posts_created();
        Tours::instance()->delete($this->tour);
    }

    private function setup() {
        // create a tour for the mapstops
        $tour_id = Tours::instance()->insert($this->helper->make_tour());
        $this->tour = Tours::instance()->get($tour_id, true, true);

        $this->name = "Mapstops Unit Test";
        $this->table = Mapstops::instance()->table;
        $this->join_table = Mapstops::instance()->join_posts_table;
        $this->mapstops = array();
    }

    private function test_mapstop_values($got, $expected, $test_name) {
        // check for specific values
        $this->assert($got->tour_id == $expected->tour_id,
            "tour id should match on: $test_name.");
        $this->assert($got->place_id == $expected->place_id,
            "place id should match on: $test_name.");
        $this->assert($got->name == $expected->name,
            "name should match on: $test_name.");
        $this->assert($got->description == $expected->description,
            "description should match on: $test_name.");
        // array equality is sufficient, we do not need identity
        $this->assert($got->post_ids == $expected->post_ids,
            "post ids should match on: $test_name.");
    }
}

// Create test, add it to the global test cases, then run
$mapstops_unit_test = new MapstopsTest();

global $shtm_test_cases;
if(empty($shtm_test_cases)) {
    $shtm_test_cases = array();
}
$shtm_test_cases[] = $mapstops_unit_test;

$mapstops_unit_test->do_test();
$mapstops_unit_test->report();

?>