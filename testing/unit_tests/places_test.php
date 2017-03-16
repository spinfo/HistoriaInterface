<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/../../models/places.php');
require_once(dirname(__FILE__) . '/../../models/place.php');
require_once(dirname(__FILE__) . '/../../models/coordinate.php');
require_once(dirname(__FILE__) . '/../test_helper.php');
require_once(dirname(__FILE__) . '/../test_case.php');
require_once(dirname(__FILE__) . '/../../db.php');
require_once(dirname(__FILE__) . '/../../models/areas.php');

/**
 * A test case for the Places collection.
 *
 * As the basic operations are pretty much covered by the api tests, this
 * only has transaction tests for now, which check that an invalid operation
 * will not result in a write to the database.
 */
class PlacesTest extends TestCase {

    public function test_transactional_create() {
        // invalidate a place and check that neither it nor the coordinate
        // is persisted
        $place = $this->helper->make_place();
        $place->name = null;
        $this->check_invalid_insert($place);

        // invalidate the coordinate and do the same
        $place = $this->helper->make_place();
        $place->coordinate->lat = null;
        $this->check_invalid_insert($place);
    }

    private function check_invalid_insert($place) {
        $last_place = $this->helper->db_highest_id($this->table);
        $last_coord = $this->helper->db_highest_id($this->coord_table);

        $exception_thrown = false;
        try {
            Places::instance()->save($place);
        } catch (DB_Exception $e) {
            $exception_thrown = true;
        }

        $this->assert($last_place == $this->helper->db_highest_id($this->table),
            "Should not have inserted place on bad place insert.");
        $this->assert($last_coord == $this->helper->db_highest_id($this->coord_table),
            "Should not have inserted coordinate on bad place insert.");

        $this->assert_invalid_id($place->id, "place");
        $this->assert_invalid_id($place->coordinate_id, "place's coordinate_id");
        $this->assert_invalid_id($place->coordinate->id, "place's coordinate's id");
    }

    public function test_transactional_update_and_delete() {
        $place = $this->place;

        // invalidate place and test the invalid update
        $id = $place->id;
        $place->id = null;
        $this->check_invalid_update($place, $id, "invalid place");
        $this->check_invalid_delete($place, $id, $place->coordinate->id, "invalid place");
        $place->id = $id;

        // invalidate coordinate and do the same
        $id = $place->coordinate->id;
        $place->coordinate->id = null;
        $this->check_invalid_update($place, $place->id, "invalid coordinate");
        $place->coordinate->id = $id;
    }

    private function check_invalid_update($place, $place_id, $test_name) {
        // save old values
        $name = $place->name;
        $lat = $place->coordinate->lat;

        // update values
        $place->name = "$name (bad update should not save)";
        $place->coordinate->lat = $lat < 89.0 ? ($lat + 1) : ($lat -1);

        // attempt the update
        $result = Places::instance()->update($place);

        $clean_place = Places::instance()->get($place_id);

        $this->assert($result == false,
            "Should return false on bad place update ($test_name).");
        $this->assert($lat == $clean_place->coordinate->lat,
            "Should not have updated lat on bad place update ($test_name).");
        $this->assert($name == $clean_place->name,
            "Should not have updated name on bad place update ($test_name).");

        // restore values
        $place->name = $name;
        $place->coordinate->lat = $lat;
    }

    private function check_invalid_delete($place, $place_id, $coord_id, $test_name) {
        // attempt the delete
        $exception_thrown = false;
        try {
            $result = Places::instance()->delete($place);
        } catch (DB_Exception $e) {
            $exception_thrown = true;
        }

        $this->assert($exception_thrown,
            "Should throw an exception n bad place delete ($test_name)");
        $this->assert(is_null($result),
            "Should return null on bad place delete ($test_name).");
        $this->assert(DB::valid_id($this->table, $place_id),
            "Should not delete place on bad place delete ($test_name).");
        $this->assert(DB::valid_id($this->coord_table, $coord_id),
            "Should not delete coordinate on bad place delete ($test_name).");
    }

    private function setup() {
        $this->name = "Places Unit Test";
        $this->place = Places::instance()->save($this->helper->make_place());
        $this->table = Places::instance()->table;
        $this->coord_table = Coordinates::instance()->table;
    }

    private function cleanup() {
        Places::instance()->delete($this->place);
    }

    public function do_test() {
        $this->setup();

        $this->test_transactional_create();
        $this->test_transactional_update_and_delete();

        $this->cleanup();
    }

}

// Create test, add it to the global test cases, then run
$places_unit_test = new PlacesTest();

global $shtm_test_cases;
if(empty($shtm_test_cases)) {
    $shtm_test_cases = array();
}
$shtm_test_cases[] = $places_unit_test;

$places_unit_test->do_test();
$places_unit_test->report();


?>