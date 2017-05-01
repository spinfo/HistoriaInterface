<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/../../models/tours.php');
require_once(dirname(__FILE__) . '/../../models/tour_record.php');
require_once(dirname(__FILE__) . '/../../models/tour_records.php');
require_once(dirname(__FILE__) . '/../test_helper.php');
require_once(dirname(__FILE__) . '/../wp_test_connection.php');

// setup the test cases
$admin_con = new WPTestConnection('Tour Records API Test (admin)',
    'test-admin', 'test-admin', $helper->config->wp_url);
$contrib_con = new WPTestConnection('Tour Records API Test (contributor)',
    'test-contributor', 'test-contributor', $helper->config->wp_url);

// add the test cases to the global test variables (in case more than one test runs)
global $shtm_test_cases;
if(empty($shtm_test_cases)) {
    $shtm_test_cases = array();
}
$shtm_test_cases[] = $admin_con;
$shtm_test_cases[] = $contrib_con;



// TODO: Make this test work again after implementing checks before publishing
// tours. This test currently does not work as it does not construct a valid.
// tour. Doing so is a bit tedious but should be done, to make this run again
return;


// setup a test helper
$helper = new TestHelper();

// a function to test the index page and whether it contains certain records
// or not
function test_record_index($con, $records_contained, $records_excluded, $name) {
    $url = $con->helper->tc_url('tour_record', 'index');
    $con->test_fetch($url, null, 200,
        "Should haves status 200 on tour record index ($name).");

    if(!is_null($records_contained)) {
        foreach ($records_contained as $record) {
            $con->ensure_xpath("//td[text() = '$record->id']", 1,
                "Should show the record's id ($name).");
            $con->ensure_xpath("//td[text() = '$record->name']", null,
                "Should show the record's name entry ($name).");
        }
    }

    if(!is_null($records_excluded)) {
        foreach ($records_excluded as $record) {
            $con->ensure_xpath("//td[text() = '$record->id'", 0,
                "Should not show the record's id ($name).");
        }
    }
}

// a function to check the view template after different operations
// - does not fetch the record view page, expects caller to have fetched a page
//   containing the view template
// - fetches the record's media_url and thus changes the current page in $con
function test_record_view($con, $record, $tour, $name) {
    $con->ensure_xpath("//li[contains(text(), '$record->name')]", 1,
        "Should show the record's tour name ($name).");

    $area_name =  $tour->area->name;
    $con->ensure_xpath("//li[contains(text(), '$area_name')]", 1,
        "Should show the record's area ($name).");

    $size_short = sprintf("%.2f MB", $record->download_size / 1000000);
    $con->ensure_xpath("//li[contains(., '$size_short')]", 1,
        "Should contain size in the right format ($name).");

    $user_name = $con->helper->get_test_user()->user_login;
    $con->ensure_xpath("//li[contains(text(), '$user_name')]", 1,
        "Should contain the name of the publishing user ($name).");

    $url = $con->ensure_xpath("//a[contains(@href, '$record->media_url')]",
        1, "Should contain a link to the tour record's file ($name).");
    $con->test_fetch($record->media_url, null, 200,
        "Should have status 200 on fetching the record's archive ($name).");
}

// a function testing the record create url, returns the record item
// corresponging to the new record
function test_record_create($con, $url, $tour, $n_versions, $name) {
    $records = TourRecords::instance()->list_versions($tour->id);
    $con->assert(count($records) == $n_versions,
        "Should test a tour with $n_versions prior records ($name).");

    $con->test_fetch($url, null, 200,
        "Should have status 200 on tour record create ($name).");

    $records = TourRecords::instance()->list_versions($tour->id);
    $con->assert(count($records) === ($n_versions + 1),
        "Should have created one record ($name).");
    $record = array_pop($records);

    // test that the page we are redirected to is a valid view
    $con->test_redirect_params('tour_record', 'view');
    test_record_view($con, $record, $tour, $name);

    return $record;
}


// TEST CREATE AND VIEW AND INDEX
// setup a tour to publish a tour record for
$tour = $helper->make_tour();
Tours::instance()->insert($tour);
$helper->add_mapstops_to_tour($tour, 2, 2);
$tour = Tours::instance()->get($tour->id, true, true);
Tours::instance()->set_related_objects_on($tour);

// the record create url is a simple get with tour id included
$url = $helper->tc_url('tour_record', 'create', null, $tour->id);
$record = test_record_create($admin_con, $url, $tour, 0,
    'admin publishes tour');

// the contributor should get a 403 on the same url
$contrib_con->test_no_access($url, null, 'contributor tries to publish tour');

// but the contributor should be able to view the record
$contrib_con->test_fetch($helper->tc_url('tour_record', 'view', $record->id),
    null, 200, "Should have status 200 on contributor viewing record.");
test_record_view($contrib_con, $record, $tour, 'contributor visits record');



// TEST INDEX
// the new record should appear in the index page
test_record_index($admin_con, array($record), null, 'admin visits index');
test_record_index($contrib_con, array($record), null,
    'contributor visits index');

// publishing the tour again returns a record with a new id, which should now
// appear on the tour index again
sleep(1); // sadly neccessary for tour publishing timestamp
$new_record = test_record_create($admin_con, $url, $tour, 1,
    'admin publishes tour');
test_record_index($admin_con, array($new_record), array($record),
    'admin visits index after tour is published again');
test_record_index($admin_con, array($new_record), array($record),
    'contributor visits index after tour is published again');


// TEST NEW
function test_record_new($con, $url, $tour, $name) {
    $con->test_fetch($url, null, 200,
        "Should have status 200 on tour record new ($name).");

    $con->ensure_xpath("//li[contains(text(), '$tour->name')]", 1,
        "Should show the record's tour name ($name).");

    $area_name =  $tour->area->name;
    $con->ensure_xpath("//li[contains(text(), '$area_name')]", 1,
        "Should show the record's area ($name).");
    $user_name = $con->helper->get_test_user()->user_login;
    $con->ensure_xpath("//li[contains(text(), '$user_name')]", 1,
        "Should contain the name of the publishing user ($name).");

    $con->ensure_xpath("//li[contains(., 'Download-Größe:')]", 1,
        "Should contain a list item indicating download size ($name).");

    $con->ensure_xpath("//li[contains(., 'Download:')]", 0,
        "Should not contain a list item indicating a download ($name).");;
}

// The admin may view the new page
$url = $helper->tc_url('tour_record', 'new', null, $tour->id);
test_record_new($admin_con, $url, $tour, 'admin visits tour record new');

// The contributor shall not view the new route
$contrib_con->test_no_access($url, null, 'contributor tries record new');




// cleanup: Remove the tour and record created
Tours::instance()->delete($tour);
$helper->delete_wp_posts_created();
TourRecords::instance()->delete($record);
TourRecords::instance()->delete($new_record);


?>