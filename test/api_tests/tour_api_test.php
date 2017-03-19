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



// TEST EDIT
function test_edit_with_bad_id($con, $id, $name) {
    $con->test_fetch($con->helper->tc_url('tour', 'edit', $id), null, 404,
        "Should have status 404 on tour edit with wrong id ($name).");
    $con->test_error_message('existiert nicht', $name);
}

function test_no_edit_access($con, $id, $name) {
    $con->test_fetch($con->helper->tc_url('tour', 'edit', $id), null, 403,
        "Should have status 403 on tour edit ($name).");
    $con->test_error_message('Berechtigung', $name);
}

function test_tour_edit($con, $id, $tour, $name) {
    $con->test_fetch($con->helper->tc_url('tour', 'edit', $id), null, 200,
        "Should have status 200 on tour edit ($name).");
    $con->test_simple_page("tour edit ($name)");

    // page should contain the areas name
    $area = Areas::instance()->get($tour->area_id);

    $con->test_page_contains($area->name, $name);
    $con->ensure_xpath(
        "//input[@name='shtm_tour[name]' and @value='$tour->name']", 1,
        "Should contain the tour's name.");
}

// test bad ids
$bad_id = Tours::instance()->last_id() + 1;
test_edit_with_bad_id($admin_test, $bad_id, 'admin');
test_edit_with_bad_id($contributor_test, $bad_id, 'contributor');

// admin should be able to edit all tours
test_tour_edit($admin_test, $t_id_admin, $tour, 'admin - own tour');
test_tour_edit($admin_test, $t_id_contributor, $tour, 'admin - other tour');

// contributor should only be able to edit her own tour
test_tour_edit($contributor_test, $t_id_contributor, $tour,
    'contributor - own tour');
test_no_edit_access($contributor_test, $t_id_admin, 'contributor - admin tour');


// TEST EDIT TRACK
function test_tour_edit_track($con, $id, $name) {
    $con->test_fetch($con->helper->tc_url('tour', 'edit_track', $id), null, 200,
        "Should have status 200 on edit_track ($name)");

    // check that leaflet scripts/styles are included
    $con->ensure_xpath("//link[@id='shtm-leaflet-style-css']", 1,
        "Should contain the leaflet style.");
    $con->ensure_xpath("//link[@id='shtm-leaflet-draw-style-css']", 1,
        "Should contain the leaflet draw style.");
    $con->ensure_xpath("//script[contains(@src, 'leaflet')]", 2,
        "Should contain two leaflet script.");
}
test_tour_edit_track($admin_test, $t_id_admin, 'admin');
test_tour_edit_track($contributor_test, $t_id_contributor, 'contributor');


// invalidate logins
$admin_test->invalidate_login();
$contributor_test->invalidate_login();

// report results
$admin_test->report();
$contributor_test->report();

?>