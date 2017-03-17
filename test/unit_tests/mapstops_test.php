<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/../../models/mapstops.php');
require_once(dirname(__FILE__) . '/../../models/mapstop.php');
require_once(dirname(__FILE__) . '/../../models/tours.php');
require_once(dirname(__FILE__) . '/../../models/places.php');

require_once(dirname(__FILE__) . '/../../logging.php');

class MapstopsTest extends TestCase {

    public function test_create() {
        $mapstop = $this->helper->make_mapstop();
        $this->test_single_good_create($mapstop, "normal mapstop");
        $this->mapstops[] = $mapstop;

        $mapstop = $this->helper->make_mapstop();
        $mapstop->post_ids = array();
        $this->test_single_good_create($mapstop, "mapstop without post ids");
        $this->mapstops[] = $mapstop;
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

        // make a clone to test the bad update doesn't change anything
        $clone = clone $mapstop;

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

        // make a mapstop with different place_ and tour_id
        $other = $this->helper->make_mapstop(true);

        $mapstop->place_id = $other->place_id;
        $mapstop->tour_id = $other->tour_id;
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
        // create a new one for this, will not be persisted
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

        $val = $mapstop->tour_id;
        $mapstop->tour_id = "";
        $this->test_single_bad_update($mapstop, $clone,
            "mapstop with invalid tour_id");
        $mapstop->tour_id = $val;

        $other = $this->helper->make_mapstop();
        Mapstops::instance()->insert($other);
        $val = $mapstop->post_ids;
        $mapstop->post_ids[] = $other->post_ids[0];
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

        // delete all the posts that the helper created for the mapstops
        $this->helper->delete_wp_posts_created();
    }

    private function setup() {
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