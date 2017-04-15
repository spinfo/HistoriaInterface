<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/../../db.php');
require_once(dirname(__FILE__) . '/../../models/areas.php');
require_once(dirname(__FILE__) . '/../../models/tour_record.php');
require_once(dirname(__FILE__) . '/../../models/tour_records.php');
require_once(dirname(__FILE__) . '/../test_helper.php');
require_once(dirname(__FILE__) . '/../test_case.php');

class TourRecordsTest extends TestCase {

    public function __construct() {
        parent::__construct();
        $this->name = 'TourRecords Unit Test';
    }

    private function test_create() {
        $record = $this->helper->make_tour_record($this->tour);
        $this->handle_test_insert($record, 'normal');

        // inserting a second record with is_active = true should nonetheless
        // result in a new record with is_active set to true
        $other = $this->helper->make_tour_record($this->tour);
        $other->is_active = false;
        $other_db = $this->handle_test_insert($other, 'set inactive');

        $this->assert($other->is_active === true,
            "Should have is_active set to true after insert");
        $this->assert($other_db->is_active === true,
            "Should have is_active set to true after insert when retrieved.");

        // retrieving the first record again should show that it is now inactive
        // because both share the same tour
        $from_db = TourRecords::instance()->get($record->id);
        $this->assert($from_db->is_active === false,
            "Should have is_active false after other records insert.");

        $this->records[] = $record;
        $this->records[] = $other;
    }

    private function handle_test_insert($record, $name) {
        $count_before = TourRecords::instance()->count();
        $result_id = TourRecords::instance()->insert($record);

        $this->assert(($count_before + 1) == TourRecords::instance()->count(),
            "Should have created a new record ($name).");
        $this->assert(TourRecords::instance()->valid_id($record->id),
            "Should have a valid id set after creation ($name).");
        $this->assert($result_id === $record->id,
            "Should have returned a valid id on insert ($name).");

        $from_db = TourRecords::instance()->get($record->id);
        $this->assert(!empty($from_db),
            "Should be able to retrieve record ($name).");
        $this->test_record_values($from_db, $record, "record insert - $name");

        return $from_db;
    }

    private function test_activation() {
        // Retrieve fresh version from the database
        $one = TourRecords::instance()->get($this->records[0]->id);
        $two = TourRecords::instance()->get($this->records[1]->id);

        $this->assert($one->is_active == false, "Should be inactive at first.");
        $this->assert($two->is_active == true, "Should be active at first.");
        $this->assert($one->tour_id == $two->tour_id,
            "Should test with the same tour id.");

        // After activating the first, the statuses should have switched
        TourRecords::instance()->set_active($one);
        $one = TourRecords::instance()->get($this->records[0]->id);
        $two = TourRecords::instance()->get($this->records[1]->id);
        $this->assert($one->is_active == true, "Should now be active.");
        $this->assert($two->is_active == false, "Should now be inactive.");

        // Deactivating a record does not change the other records' status
        TourRecords::instance()->set_inactive($one);
        $one = TourRecords::instance()->get($this->records[0]->id);
        $two = TourRecords::instance()->get($this->records[1]->id);
        $this->assert($one->is_active == false,
            "Should be inactive when inactivated.");
        $this->assert($two->is_active == false, "Should still be inactive.");

        $this->records[0] = $one;
        $this->records[1] = $two;
    }

    public function test_update() {
        // An update is impossible, but test that an exception is thrown and no
        // values change
        $record = $this->records[0];
        $other = $this->helper->make_tour_record($this->tour);
        $other->id = $record->id;

        $exception_thrown = false;
        try {
            TourRecords::instance()->update($other);
        } catch(\BadMethodCallException $e) {
            $exception_thrown = true;
        }
        $this->assert($exception_thrown, "Should throw exception on update");
        $this->test_record_values(TourRecords::instance()->get($record->id),
            $record, "expect no change after attempted update");
    }

    public function test_delete() {
        foreach ($this->records as $record) {
            $id = $record->id;
            $this->assert(TourRecords::instance()->valid_id($id),
                "Id should be valid before delete.");

            $result = TourRecords::instance()->delete($record);

            $this->assert(!TourRecords::instance()->valid_id($id),
                "Id should be invalid after delete.");
            $this->assert($result->id === DB::BAD_ID,
                "Should have bad id set after delete");
        }
    }

    public function do_test() {
        $this->setup();

        $this->test_create();
        $this->test_activation();
        $this->test_update();
        $this->test_delete();

        $this->cleanup();
    }

    private function setup() {
        $this->records = array();
        $this->tour = $this->helper->make_tour();
        Tours::instance()->insert($this->tour);
    }

    private function cleanup() {
        Tours::instance()->delete($this->tour);
    }


    private function test_record_values($got, $expected, $name) {
        $this->assert($expected->area_id === $got->area_id,
            "Area ids should be equal ($name).");
        $this->assert($expected->tour_id === $got->tour_id,
            "Tour ids should be equal ($name).");
        $this->assert($expected->user_id === $got->user_id,
            "User ids should be equal ($name).");
        $this->assert($expected->name === $got->name,
            "Names should be equal ($name).");
        $this->assert($expected->is_active === $got->is_active,
            "Active status should be equl ($name).");
        $this->assert($expected->content === $got->content,
            "Content should be equal ($name).");
        $this->assert($expected->media_url === $got->media_url,
            "Media url should be equal ($name).");
        $this->assert($expected->download_size === $got->download_size,
            "Download size should be equal ($name).");
    }

}

// Create test, add it to the global test cases, then run
$tour_records_unit_test = new TourRecordsTest();

global $shtm_test_cases;
if(empty($shtm_test_cases)) {
    $shtm_test_cases = array();
}
$shtm_test_cases[] = $tour_records_unit_test;

$tour_records_unit_test->do_test();
$tour_records_unit_test->report();

?>