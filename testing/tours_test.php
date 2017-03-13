<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/../models/areas.php');
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
        // make tour invalidate the last coordinate
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

    public function do_test() {
        $this->setup();

        $this->test_create();
        $this->test_bad_create();
    }

    private function setup() {
        $this->name = "Tours Unit Test";
        $this->table = Tours::instance()->table;
        $this->join_table = Tours::instance()->join_coordinates_table;
        $this->coords_table = Coordinates::instance()->table;
        $this->tours = array();
    }

}


?>