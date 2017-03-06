<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/../models/areas.php');
require_once(dirname(__FILE__) . '/../models/area.php');
require_once(dirname(__FILE__) . '/../models/coordinate.php');
require_once(dirname(__FILE__) . '/test_helper.php');
require_once(dirname(__FILE__) . '/test_case.php');

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

    public function test_delete() {
        // all areas created earlier
        $this->assert(count($this->areas) > 0,
            "There should be areas to delete in the test case.");
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