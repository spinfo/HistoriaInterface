<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/../../models/areas.php');
require_once(dirname(__FILE__) . '/../../models/mapstops.php');
require_once(dirname(__FILE__) . '/../../models/tour.php');
require_once(dirname(__FILE__) . '/../../models/tours.php');

class ToursTest extends TestCase {

    public function test_create() {
        // make a tour with three coordinates
        $tour = $this->helper->make_tour(3);

        $id_before = $this->helper->db_highest_id($this->table);
        $result = Tours::instance()->insert($tour);

        $this->assert($id_before < $this->helper->db_highest_id($this->table),
            "There should be a new tour on simple insert.");
        $this->assert(DB::valid_id($this->table, $result),
            "Should return a valid tour id on simple insert.");

        $n = count($tour->coordinates);
        $this->assert($n == 3, "Tour should have three coordinates on insert.");
        for($i = 0; $i < $n; $i++) {
            $coord = $tour->coordinates[$i];

            $this->assert(DB::valid_id($this->coords_table, $coord->id),
                "Tour coordinate should have a valid id set on insert.");

            $this->assert($tour->coordinate_ids[$i] = $coord->id,
                "Coordinate id should be present on tour insert.");
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

        $this->helper->add_mapstops_to_tour($tour);

        $from_db = Tours::instance()->get($tour->id, true, true);

        // test general model validity
        $this->assert_valid_model($from_db, $this->table, "get tour");

        // test that the returned values match
        $this->check_tour_values($from_db, $tour, "get tour");
    }

    public function test_update() {
        $tour = $this->tours[0];
        $other = $this->helper->make_tour();

        // change tour's values to other's values and check that it worked
        // reset coordinate_ids manually since the other tour doesn't have any
        $coordinate_ids = $tour->coordinate_ids;
        Tours::instance()->update_values($tour, $other);
        $this->check_tour_values($tour, $other, "value update on tour");
        $tour->coordinate_ids = $coordinate_ids;

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

    public function test_update_mapstop_positions() {
        // retrieve the tour with mapstop ids
        $tour = Tours::instance()->get($this->tours[0]->id, true);

        $m_ids = $tour->mapstop_ids;

        $this->assert(count($m_ids) === 3, "Should test three mapstop ids.");

        for($i = 0; $i < 3; $i++) {
            $ids = $m_ids;
            array_push($ids, array_splice($ids, $i, 1)[0]);
            $this->test_single_good_mapstop_position_update($tour, $ids);

            $ids = $m_ids;
            array_unshift($ids, array_splice($ids, $i, 1)[0]);
            $this->test_single_good_mapstop_position_update($tour, $ids);

            $ids = $m_ids;
            array_splice($ids, 1, 0, array_splice($ids, $i, 1));
            $this->test_single_good_mapstop_position_update($tour, $ids);
        }

        // get a new reference
        $tour = Tours::instance()->get($this->tours[0]->id, true);

        // updating positions with a missing id should fail
        $ids = $m_ids;
        array_pop($ids);
        $this->test_single_bad_mapstop_position_update($tour, $ids);

        // updating positions with a surplus id should fail
        $ids = $m_ids;
        $new_mapstop = $this->helper->make_mapstop(true);
        Mapstops::instance()->insert($new_mapstop);
        array_push($ids, $new_mapstop->id);
        $this->test_single_bad_mapstop_position_update($tour, $ids);
        Mapstops::instance()->delete($new_mapstop);

        // updating another tour should invariably fail
        $other = Tours::instance()->get(Tours::instance()->first_id(), true);
        $this->assert($other->id != $tour->id,
            "Should test positions update for different tours");
        $this->test_single_bad_mapstop_position_update($other, $m_ids);
    }

    private function test_single_good_mapstop_position_update($tour, $new_ids) {
        $res = Tours::instance()->update_mapstop_positions($tour->id, $new_ids);
        $this->assert($res === true,
            "Should return true on normal mapstop positions update.");

        // test that ids are returned in the new order
        $from_db = Tours::instance()->get($tour->id, true);
        $this->assert($from_db->mapstop_ids === $new_ids,
            "Mapstop ids from database should be identical to new values");
    }

    private function test_single_bad_mapstop_position_update($tour, $new_ids) {
        $res = Tours::instance()->update_mapstop_positions($tour->id, $new_ids);
        $this->assert($res === false,
            "Should return false on bad mapstop positions update.");

        // test that ids stayed int the tour's order
        $from_db = Tours::instance()->get($tour->id, true);
        $this->assert($from_db->mapstop_ids === $tour->mapstop_ids,
            "Mapstop ids from database should be identical to old values");
    }

    public function test_bad_update() {
        // select a tour to update and get a version from db to test that no
        // value changes occur
        $tour = $this->tours[0];
        $from_db = Tours::instance()->get($tour->id);

        // invalidate by invalidating a coordinate
        $last_coord = $tour->coordinates[count($tour->coordinates) -1];
        $old_lat = $last_coord->lat;
        $last_coord->lat = null;
        $this->test_single_bad_update($tour, $from_db,
            "update tour with bad coordinate");
        $last_coord->lat = $old_lat;

        // invalidate by setting tag_when_end before tag_when_start
        $old_end = $tour->tag_when_end;
        $tour->tag_when_end = $tour->tag_when_start - 1;
        $this->test_single_bad_update($tour, $from_db,
            "update tour with bad tag_when_end");
        $tour->tag_when_end = $old_end;

        // invalidate by setting an empty name
        $old_name = $tour->name;
        $tour->name = '';
        $this->test_single_bad_update($tour, $from_db,
            "update tour with empty name");
        $tour->name = $old_name;
    }

    public function test_single_bad_update($tour, $good_clone, $test_name) {
        // add a coordinate to the tour to check that it doesn't get persisted
        $new_coord = $this->helper->random_coordinate();
        $tour->coordinates[] = $new_coord;

        // attempt the insert and check that no coordinate was added to the
        $c_id_before = $this->helper->db_highest_id($this->coords_table);
        Tours::instance()->update($tour);
        $c_id_after = $this->helper->db_highest_id($this->coords_table);

        $this->assert($c_id_before == $c_id_after,
            "No coordinate should have been created. ($test_name)");

        // test that the new coordinate does not have an id value set
        $this->assert($new_coord->id == DB::BAD_ID,
            "Should have bad id on coordinate ($test_name)");

        // retrieve a copy of the tour from the database and ensure that it's
        // values match those of the good clone
        $from_db = Tours::instance()->get($tour->id);
        $this->check_tour_values($from_db, $good_clone, $test_name);

        // remove the coordinate added to this tour
        array_pop($tour->coordinates);
    }

    public function test_bad_delete() {
        // select a tour and invalidate it by removing a coordinate id
        $tour = $this->tours[0];
        $save_coordinate_id = array_pop($tour->coordinate_ids);

        // a clone to test values on
        $clone = clone $tour;

        // add mapstops to the tour to ensure it has at least one
        $this->helper->add_mapstops_to_tour($tour);

        // take count
        $old_tour_count = DB::count($this->table);
        $old_join_count = DB::count($this->join_table);
        $old_coord_count = DB::count(Coordinates::instance()->table);
        $old_mapstop_count = DB::count(Mapstops::instance()->table);

        // attempt the delete
        $exception_thrown = false;
        try {
            $result = Tours::instance()->delete($tour);
        } catch(DB_Exception $e) {
            $exception_thrown = true;
        }

        // check function spec
        $this->assert($exception_thrown,
            "Should throw an exception on bad tour delete.");
        $this->assert(is_null($result),
            "Should return null on bad tour delete.");
        $this->check_tour_values($tour, $clone, "no change on bad delete");

        // recount
        $new_tour_count = DB::count($this->table);
        $new_join_count = DB::count($this->join_table);
        $new_coord_count = DB::count(Coordinates::instance()->table);
        $new_mapstop_count = DB::count(Mapstops::instance()->table);

        $this->assert($old_tour_count === $new_tour_count,
            "Should not have deleted any tours on bad tour delete.");
        $this->assert($old_join_count === $new_join_count,
            "Should not have deleted any join on bad tour delete.");
        $this->assert($old_coord_count === $new_coord_count,
            "Should not have deleted any coordinates on bad tour delete.");
        $this->assert($old_mapstop_count === $new_mapstop_count,
            "Should not have deleted any mapstops on bad tour delete.");

        // restore the tour's state to before invalidation
        array_push($tour->coordinate_ids, $save_coordinate_id);
    }

    public function test_delete() {
        // test for every tour present (should be only one though)
        foreach ($this->tours as $tour) {
            // a where condition to get counts
            $where = array('tour_id' => $tour->id);

            // take count
            $count_mapstops = DB::count(Mapstops::instance()->table, $where);
            $count_joins = DB::count($this->join_table, $where);
            $n_coords = count($tour->coordinate_ids);
            $old_coords_count = DB::count(Coordinates::instance()->table);

            $this->assert($count_mapstops > 0,
                "Should have positive mapstop count before tour delete.");
            $this->assert($count_joins > 0,
                "Should have positive join count before tour delete.");

            // do the delete
            $result = Tours::instance()->delete($tour);

            $this->assert(!is_null($result),
                "Should return not null on tour delete.");
            $this->assert(!DB::valid_id($this->table, $tour->id),
                "Should have deleted the tour on tour delete");
            $this->assert($tour->id === DB::BAD_ID,
                "Tour should have invalid id after delete");
            $this->assert(count($tour->coordinates) === $n_coords,
                "Should have the right amount of coordinates after delete");
            foreach($tour->coordinates as $c) {
                $this->assert($c->id === DB::BAD_ID,
                    "Coordinate should have invalid id on tour delete.");
            }

            // recount
            $count_mapstops = DB::count(Mapstops::instance()->table, $where);
            $count_joins = DB::count($this->join_table, $where);
            $new_coords_count = DB::count(Coordinates::instance()->table);

            $this->assert($count_mapstops === 0,
                "Should count zero linked mapstops after tour delete.");
            $this->assert($count_joins === 0,
                "Should count zero coordinate joins after tour delete.");
            $this->assert(($old_coords_count - $new_coords_count) === $n_coords,
                "Should have deleted the right no. of coords on tour delete");
        }
    }

    // test that the conversion to and from julian dates works
    public function test_tour_dates() {
        // setup a few times to test using the standard format
        $times = array(
            '-4713-01-01 12:00:00',
            '-1003-06-27 12:00:00',
            '+1548-10-13 17:23:07',
            '+1716-02-02 13:33:33',
            '+2017-08-16 12:00:00',
            '+3333-01-28 11:17:02',
            '+9999-12-31 23:59:59',
        );

        foreach ($times as $time_str) {
            $dt = new \DateTime($time_str, new \DateTimeZone('UTC'));
            $this->test_single_time_conversion($dt, $time_str);
        }

        // test a few other times using direct string input in different formats
        $times = array(
            'd.m.Y H:i:s' => '10.04.2017 13:07:22',
            'd.m.Y H:i'   => '10.04.2017 13:07',
            'd.m.Y'       => '10.04.2017',
            'm.Y'         => '04.2017',
            'Y'           => '2017',
            // 3-digit years
            'd.m.Y H:i:s' => '10.04.123 13:07:22',
            'd.m.Y H:i'   => '10.04.123 13:07',
            'd.m.Y'       => '10.04.123',
            'm.Y'         => '04.123',
            'Y'           => '123',
            // 2-digit years
            'd.m.Y H:i:s' => '10.04.33 13:07:22',
            'd.m.Y H:i'   => '10.04.33 13:07',
            'd.m.Y'       => '10.04.33',
            'm.Y'         => '04.33',
            'Y'           => '33',
            // 1-digit years
            'd.m.Y H:i:s' => '10.04.9 13:07:22',
            'd.m.Y H:i'   => '10.04.9 13:07',
            'd.m.Y'       => '10.04.9',
            'm.Y'         => '04.9',
            'Y'           => '9'
        );

        foreach ($times as $format => $str) {
            $tour = $this->tours[0];
            $tour->set_tag_when_start($str);
            $dt = $tour->get_tag_when_start();

            $this->assert($tour->get_tag_when_start_formatted() === $str,
                "String from output datetime should match input: $str.");
            $this->assert($tour->tag_when_start_format === $format,
                "The format detected should match the input format: $format");
        }
    }

    private function test_single_time_conversion($in, $time_str) {
        $tour = $this->tours[0];

        $tour->set_tag_when_start($in);
        $diff = $in->diff($tour->get_tag_when_start());

        // just sum all interval values to see if there is any diff
        $sum = $diff->y + $diff->m + $diff->d + $diff->h + $diff->i + $diff->s;
        $this->assert($sum === 0,
            "There should be no time difference for input: $time_str");
    }

    public function do_test() {
        $this->setup();

        $this->test_create();
        $this->test_bad_create();
        $this->test_get();
        $this->test_update();
        $this->test_update_mapstop_positions();
        $this->test_bad_update();
        $this->test_bad_delete();
        $this->test_delete();

        $this->test_tour_dates();

        // delete the wordpress posts created for mapstops during these tests
        $this->helper->delete_wp_posts_created();
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
        $this->assert($got->tag_when_start_format == $expected->tag_when_start_format, "tag_when_start_formats should match ($test_name).");
        $this->assert($got->tag_when_end_format == $expected->tag_when_end_format, "tag_when_end_formats should match ($test_name).");
        $this->assert($got->accessibility == $expected->accessibility, "accessibility should match ($test_name).");
        $this->assert($got->author == $expected->author, "author should match ($test_name).");

        if(!empty($expected->coordinate_ids)) {
            // test for array equality (not identity) is sufficient
            $this->assert($got->coordinate_ids == $expected->coordinate_ids,
                "coordinate_ids should match ($test_name).");
        }
        if(!empty($expected->mapstop_ids)) {
            // test for identity as the ids order is important
            $this->assert($got->mapstop_ids === $expected->mapstop_ids,
                "mapstop_ids should match ($test_name).");
        }
    }

}

// Create test, add it to the global test cases, then run
$tours_unit_test = new ToursTest();

global $shtm_test_cases;
if(empty($shtm_test_cases)) {
    $shtm_test_cases = array();
}
$shtm_test_cases[] = $tours_unit_test;

$tours_unit_test->do_test();
$tours_unit_test->report();

?>