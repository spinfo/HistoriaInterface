<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/../models/areas.php');
require_once(dirname(__FILE__) . '/../models/area.php');
require_once(dirname(__FILE__) . '/../models/coordinate.php');
require_once(dirname(__FILE__) . '/test_helper.php');
require_once(dirname(__FILE__) . '/test_case.php');
require_once(dirname(__FILE__) . '/../db.php');

class AreasTest extends TestCase {

    public $areas = array();

    public function __construct() {
        parent::__construct();
        $this->name = 'Areas Unit Test';
    }

    public function test_create() {
        $area = $this->make_area();

        $id_before = $this->helper->db_highest_id(Areas::instance()->table);
        $result = Areas::instance()->save($area);
        $id_after = $this->helper->db_highest_id(Areas::instance()->table);

        $this->assert($id_before < $id_after,
            "There should be a new area in the areas table.");
        $this->assert($id_after == $result->id,
            "The new area should have the right id.");
        $this->test_area_values($result, $area, "new area");

        // add to the areas array for later use
        $this->areas[] = $area;
    }

    public function test_get() {
        // use the area created during test_create()
        $area = $this->areas[0];

        $result = Areas::instance()->get($area->id);
        $this->assert($area->id == $result->id,
            "Id values should match on area get.");

        $this->test_area_values($result, $area, "retrieved area");
    }

    public function test_list_simple() {
        $n = 3;
        for($i = 0; $i < $n; $i++) {
            $this->areas[] = Areas::instance()->save($this->make_area());
        }
        $result = Areas::instance()->list_simple();

        $this->assert(count($result) >= $n, "Should list at least $n elements.");

        // this will check more than the retrieved instances if more were in
        // the db before creation
        foreach($result as $got) {
            // On the simple list, coordinates are not resolved, so we
            // only check for the presence/validity of ids and name equality
            $this->assert($got->id > 0,
                "id should be retrieved on simple list.");
            $this->assert(!empty($got->name),
                "Names should not be empty in simple list.");
            $this->assert($got->coordinate1_id > 0,
                "Should have a coordinate1_id on simple list.");
            $this->assert(
                !empty(Coordinates::instance()->get($got->coordinate1_id)),
                "Coordinate 1 should be retrievable on simple list.");
            $this->assert($got->coordinate2_id > 0,
                "Should have a coordinate2_id on simple list.");
            $this->assert(
                !empty(Coordinates::instance()->get($got->coordinate2_id)),
                "Coordinate 2 should be retrievable on simple list.");
        }
    }

    public function test_update() {
        // get the area created in test_create()
        $area = $this->areas[0];
        // make a new area and set the old one's values to the new one's
        $new_area = $this->make_area();
        $area->name = $new_area->name;
        $area->coordinate1->lat = $new_area->coordinate1->lat;
        $area->coordinate1->lon = $new_area->coordinate1->lon;
        $area->coordinate2->lat = $new_area->coordinate2->lat;
        $area->coordinate2->lon = $new_area->coordinate2->lon;

        $id_before = $this->helper->db_highest_id(Areas::instance()->table);
        $result = Areas::instance()->save($area);
        $id_after = $this->helper->db_highest_id(Areas::instance()->table);

        $this->assert($id_before == $id_after,
            "There should be no new area created on update.");
        $this->assert($area->id == $result->id,
            "id should not have changed on update.");

        $this->test_area_values($result, $new_area, "updated area");
        $this->test_area_values($area, $new_area, "old object of updated area");
    }

    public function test_transactional_update() {
        // variables for tables to use for convenience
        $table = Areas::instance()->table;
        $coord_table = Coordinates::instance()->table;

        // pick an area to test
        $area = $this->areas[0];

        // save values for later comparison
        $coord2_id = $area->coordinate2_id;
        $area_id = $area->id;
        $old_name = $area->name;
        $old_lat = $area->coordinate1->lat;

        // invalidate id of one coordinate to test that the transaction fails
        $area->coordinate2->id = DB::BAD_ID;

        // change area's and coordinate's values to simulate that an update
        // would be neccessary
        $area->name = "$area->name (test transaction failed if this is saved.)";
        $area->coordinate1->lat =
            $old_lat < 89.0 ? ($old_lat + 1) : ($old_lat -1);

        $result = Areas::instance()->update($area);

        $this->assert($result == false, "Should return false on bad update.");

        $clean_area = Areas::instance()->get($area->id);
        $clean_coord = Coordinates::instance()->get($area->coordinate1->id);

        $this->assert($clean_area->name == $old_name,
            "Should not have updated name on bad update.");
        $this->assert($clean_coord->lat == $old_lat,
            "Should not have updated lat of coord on bad update");

        // restore the previous state
        $area->coordinate2->id = $coord2_id;
        $area->coordinate2_id = $coord2_id;
        $area->name = $old_name;
    }

    public function test_transactional_insert() {
        // variables for tables to use for convenience
        $table = Areas::instance()->table;
        $coord_table = Coordinates::instance()->table;

        $area = $this->make_area();

        // invalidating one coordinate should stop any insert
        $area->coordinate2->lat = null;

        // save id values and attempt the insert
        $last_area = $this->helper->db_highest_id($table);
        $last_coord = $this->helper->db_highest_id($coord_table);
        $exception_thrown = false;
        try {
            $result = Areas::instance()->save($area);
        } catch (DB_Exception $e) {
            $exception_thrown = true;
        }

        $this->assert($exception_thrown,
            "Should throw exception on bad insert.");
        $this->assert(is_null($result) || $result == DB::BAD_ID,
            "Should return null or error value on bad insert.");
        $this->assert($last_area == $this->helper->db_highest_id($table),
            "No area should have been added to table on bad insert.");
        $this->assert($last_coord == $this->helper->db_highest_id($coord_table),
            "No coordinate should have been added to table on bad insert.");

        $this->assert_invalid_id($area->id, "area");
        $this->assert_invalid_id($area->coordinate1->id, "coordinate1");
        $this->assert_invalid_id($area->coordinate1_id, "coordinate1_id");
        $this->assert_invalid_id($area->coordinate2->id, "coordinate2");
        $this->assert_invalid_id($area->coordinate2_id, "coordinate2_id");
    }

    public function test_transactional_delete() {
        // variables for tables to use for convenience
        $table = Areas::instance()->table;
        $coord_table = Coordinates::instance()->table;

        // pick an area to test
        $area = $this->areas[1];

        // save area values for later comparison
        $coord2_id = $area->coordinate2_id;
        $area_id = $area->id;

        // invalidate id of one coordinate to test that the transaction fails
        $area->coordinate2->id = DB::BAD_ID;

        // save id values and attempt the delete
        $last_area = $this->helper->db_highest_id($table);
        $last_coord = $this->helper->db_highest_id($coord_table);
        Areas::instance()->delete($area);

        // TODO: this doesn't work, need to check for the exact row...

        $this->assert(is_null($result) || $result == DB::BAD_ID,
            "Should return null or error value on bad delete.");
        $this->assert($last_area == $this->helper->db_highest_id($table),
            "No area should have been removed on bad delete.");
        $this->assert($last_coord == $this->helper->db_highest_id($coord_table),
            "No coordinate should have been removed on bad delete.");

        // restore the previous state
        $area->coordinate2->id = $coord2_id;
        $area->coordinate2_id = $coord2_id;
        $area->id = $area_id;
    }

    public function test_delete() {
        // all areas created earlier
        $this->assert(count($this->areas) > 0,
            "There should be at least one area to delete in the test case.");

        // delete all remaining areas normally here
        foreach($this->areas as $area) {
            $id = $area->id;
            $coord1_id = $area->coordinate1_id;
            $coord2_id = $area->coordinate2_id;
            $table = Areas::instance()->table;
            $coord_table = Coordinates::instance()->table;

            $this->assert($this->helper->db_has_row($table, $id),
                "area should be present before delete.");
            $this->assert($this->helper->db_has_row($coord_table, $coord1_id),
                "coordinate1 should be present before area delete.");
            $this->assert($this->helper->db_has_row($coord_table, $coord2_id),
                "coordinate2 should be present before area delete.");

            $result = Areas::instance()->delete($area);

            $this->assert(!$this->helper->db_has_row($table, $id),
                "area should not be present after delete");
            $this->assert(!$this->helper->db_has_row($coord_table, $coord1_id),
                "coordinate1 should not be present after area delete.");
            $this->assert(!$this->helper->db_has_row($coord_table, $coord2_id),
                "coordinate2 should not be present after area delete.");

            $this->assert($result->id <= 0,
                "Delete should return area without id.");
            $this->assert($result->coordinate1_id <= 0,
                "Delete should return area without coordinate1_id.");
            $this->assert($result->coordinate2_id <= 0,
                "Delete should return area without coordinate2_id.");
            $this->assert($result->coordinate1->id <= 0,
                "Delete should return area's coordinate1 without id.");
            $this->assert($result->coordinate2->id <= 0,
                "Delete should return area's coordinate2 without id.");
        }
    }

    public function do_test() {
        $this->test_create();
        $this->test_get();
        $this->test_list_simple();
        $this->test_update();
        $this->test_transactional_update();
        $this->test_transactional_insert();
        $this->test_transactional_delete();
        $this->test_delete();
    }

    private function test_coordinate_values($got, $expected, $name) {
        $this->assert(is_float($got->lat), "Lat should be float on $name.");
        $this->assert(is_float($got->lon), "Lon should be float on $name.");
        $this->assert(($got->lat == $expected->lat),
            "Lat values should match on $name.");
        $this->assert(($got->lon == $expected->lon),
            "Lon values should match on $name.");
        if(!$this->assert($got->is_valid(), "$name should report as valid.")) {
            foreach($got->messages as $msg => $bool) {
                $this->log("\t${msg}");
            }
        }
    }

    private function test_area_values($got, $expected, $name) {
        $this->assert(($expected->name == $got->name),
            "$name should have the correct name.");
        $this->assert(($got->coordinate1_id == $got->coordinate1->id),
            "Coordinate 1: id values should be the same in $name and coord.");
        $this->assert(($got->coordinate2_id == $got->coordinate2->id),
            "Coordinate 2: id values should be the same in $name and coord.");
        $this->test_coordinate_values(
            $got->coordinate1, $expected->coordinate1, "coordinate 1 ($name)");
        $this->test_coordinate_values(
            $got->coordinate2, $expected->coordinate2, "coordinate 2 ($name)");

        if(!$this->assert($got->is_valid(), "$name should report as valid.")) {
            foreach($got->messages as $msg => $bool) {
                $this->log("\t${msg}");
            }
        }
    }

    private function make_area() {
        $area = new Area();
        $area->name = 'Area Test Name ' . $this->helper->random_str();
        $area->coordinate1 = $this->helper->random_coordinate();
        $area->coordinate2 = $this->helper->random_coordinate();
        return $area;
    }
}

?>