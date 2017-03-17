<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/../../models/areas.php');
require_once(dirname(__FILE__) . '/../wp_test_connection.php');

// setup the tests
$admin_test = new WPTestConnection('Tours API Test (admin)',
    'test-admin', 'test-admin', $helper->config->wp_url);
$contributor_test = new WPTestConnection('Tours API Test (contributor)',
    'test-contributor', 'test-contributor', $helper->config->wp_url);

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
    $helper = $con->helper;
    $name = "tour create ($name)";

    $con->test_fetch($helper->tc_url('tour', 'create'), $post, 200,
        "Should have status 200 on $name.");

    $con->test_success_message("Tour erstellt!", $name);

    $id = $con->test_get_redirect_param('shtm_id');
}
$tour = $helper->make_tour();
$post_create = array(
    'shtm_tour[name]' => $tour->name,
    'shtm_tour[area_id]' => $tour->area_id,
);

test_tour_create($admin_test, $post_create, 'admin');
test_tour_create($contributor_test, $post_create, 'contributor');


// invalidate logins
$admin_test->invalidate_login();
$contributor_test->invalidate_login();

// report results
$admin_test->report();
$contributor_test->report();

?>