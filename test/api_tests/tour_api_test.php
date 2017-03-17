<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/../../models/areas.php');
require_once(dirname(__FILE__) . '/../../models/tours.php');
require_once(dirname(__FILE__) . '/../wp_test_connection.php');

// setup the test cases
$admin_test = new WPTestConnection('Tours API Test (admin)',
    'test-admin', 'test-admin', $helper->config->wp_url);
$contributor_test = new WPTestConnection('Tours API Test (contributor)',
    'test-contributor', 'test-contributor', $helper->config->wp_url);

// add the test cases to the global test variables
global $shtm_test_cases;
if(empty($shtm_test_cases)) {
    $shtm_test_cases = array();
}
$shtm_test_cases[] = $admin_test;
$shtm_test_cases[] = $contributor_test;

// TOUR NEW
function test_tour_new($con, $name) {
    $helper = $con->helper;
    $name = "tour new ($name)";

    $con->test_fetch($helper->tc_url('tour', 'new'), null, 200,
        "Should have status 200 on $name");

    $con->test_simple_page($name);
    $con->test_page_heading('Tour erstellen', $name);

    $con->ensure_xpath("//input[@name='shtm_tour[name]' and @value='']", 1,
        "Should have an empty form field for the name on $name");

    // test the area selection
    $con->ensure_xpath("//select[@name='shtm_tour[area_id]']", 1,
        "Should contain a selection for the tour's area id on $name");
    $areas = Areas::instance()->list_simple();
    foreach ($areas as $area) {
        $xpath = "//option[@value='$area->id' and contains(text(), '$area->name')]";
        $con->ensure_xpath($xpath, 1,
            "Should contain an option for area: '$area->id' on $name");
    }
}
test_tour_new($admin_test, 'admin');
test_tour_new($contributor_test, 'contributor');


// TOUR CREATE
function test_tour_create($con, $post, $name) {
    $name = "tour create ($name)";

    $con->test_fetch($con->helper->tc_url('tour', 'create'), $post, 200,
        "Should have status 200 on $name.");

    $con->test_success_message("Tour erstellt!", $name);

    // should redirect to the edit page of the tour
    $con->test_redirect_param('shtm_c', 'tour');
    $con->test_redirect_param('shtm_a', 'edit');
    $id = $con->test_redirect_param('shtm_id');

    // test that the id exists in the database
    $id = intval($id);
    $con->assert(DB::valid_id(Tours::instance()->table, $id),
        "Returned id should be valid");

    // return the id param we were redirected to
    return $id;
}

function test_bad_create($con, $post, $name) {
    $name = "bad tour create ($name)";

    $id_before = Tours::instance()->last_id();

    $con->test_fetch($con->helper->tc_url('tour', 'create'), $post, 200,
        "Should have status 200 on $name.");

    $con->test_error_message("Nicht gespeichert", $name);

    // should have redirect back to new page
    $con->test_redirect_param('shtm_c', 'tour');
    $con->test_redirect_param('shtm_a', 'new');

    $id_after = Tours::instance()->last_id();
    $con->assert($id_before == $id_after, "Should not have created any tour.");
}

$tour = $helper->make_tour();
$post = array(
    'shtm_tour[name]' => $tour->name,
    'shtm_tour[area_id]' => $tour->area_id,
);
// test the normal create and save the returned id values for later tests
$t_id_admin = test_tour_create($admin_test, $post, 'admin');
$t_id_contributor =
    test_tour_create($contributor_test, $post, 'contributor');

$bad_post = array_merge($post, array('shtm_tour[name]' => ''));
test_bad_create($admin_test, $bad_post, 'admin - bad tour name');
test_bad_create($contributor_test, $bad_post, 'contributor - bad tour name');

$bad_id = Areas::instance()->last_id() + 1;
$bad_post = array_merge($post, array('shtm_tour[area_id]' => $bad_id));
test_bad_create($admin_test, $bad_post, 'admin - bad area_id');
test_bad_create($contributor_test, $bad_post, 'contributor - bad area_id');


// invalidate logins
$admin_test->invalidate_login();
$contributor_test->invalidate_login();

// report results
$admin_test->report();
$contributor_test->report();

?>