<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/../wp_test_connection.php');
require_once(dirname(__FILE__) . '/../../models/areas.php');

// AREAS TESTS
$admin_con = new WPTestConnection('Areas API Test (admin)',
    'test-admin', 'test-admin', $helper->config->wp_url);
$contrib_con = new WPTestConnection('Areas API Test (contributor)',
    'test-contributor', 'test-contributor', $helper->config->wp_url);

global $shtm_test_cases;
if(empty($shtm_test_cases)) {
    $shtm_test_cases = array();
}
$shtm_test_cases[] = $admin_con;
$shtm_test_cases[] = $contrib_con;

// TEST SETTING CURRENT AREA
function test_set_current_area($test_con, $name) {
    $helper = $test_con->helper;
    // NOTE: This assumes a fresh install of the plugin. TODO: Find a better way to do this once area create is tested
    $id = 1;
    $url = $helper->tc_url('area', 'set_current_area', $id, 'page=shtm_tour_creator');
    $test_con->test_fetch($url, null, 200,
        "Should have status 200 on setting current area for $name");
    $test_con->ensure_xpath("//option[@value='$id' and @selected]", 1,
        "Should have marked the right area as selected for $name.");

    $bad_id = $helper->db_highest_id($helper->config->areas_table) + 1;
    $url = $helper->tc_url('area', 'set_current_area', $bad_id, 'page=shtm_tour_creator');

    $test_con->test_fetch($url, null, 200,
        "Should still have status 200 on setting current area with bad input");
    $test_con->ensure_xpath("//option[@value='$id' and @selected]", 1,
        "Should still have marked the valid area on bad input as selected for $name.");
}

test_set_current_area($admin_con, "admin");
test_set_current_area($contrib_con, "contributor");


// TODO: Remove this after the new route is tested
$a_admin = $helper->make_area();
$a_id_admin = Areas::instance()->insert($a_admin);


// TEST EDIT & UPDATE
function test_edit($con, $area, $name, $do_fetch = true) {
    if($do_fetch) {
        $url = $con->helper->tc_url('area', 'edit', $area->id);
        $con->test_fetch($url, null, 200,
            "Should have status 200 on area edit ($name).");
    }

    // test for the presence of correct inputs
    $con->test_input_field('shtm_area[name]', $area->name, $name);
    $con->test_input_field('shtm_area[c1_lat]',
        $con->helper->coord_value_string($area->coordinate1->lat), $name);
    $con->test_input_field('shtm_area[c1_lon]',
        $con->helper->coord_value_string($area->coordinate1->lon), $name);
    $con->test_input_field('shtm_area[c2_lat]',
        $con->helper->coord_value_string($area->coordinate2->lat), $name);
    $con->test_input_field('shtm_area[c2_lon]',
        $con->helper->coord_value_string($area->coordinate2->lon), $name);
}

function test_update($con, $area, $name) {
    $other = $con->helper->make_area();

    $post = array(
        'shtm_area[name]' => $other->name,
        'shtm_area[c1_lat]' => $other->coordinate1->lat,
        'shtm_area[c1_lon]' => $other->coordinate1->lon,
        'shtm_area[c2_lat]' => $other->coordinate2->lat,
        'shtm_area[c2_lon]' => $other->coordinate2->lon
    );

    $url = $con->helper->tc_url('area', 'update', $area->id);
    $con->test_fetch($url, $post, 200,
        "Should have status 200 on area update ($name).");

    $con->test_redirect_params('area', 'edit', $id);
    test_edit($con, $other, $name, false);

    return $post;
}

// Test edit and update for admin, re-fetch area to get the updated model
test_edit($admin_con, $a_admin, 'Admin visits own area edit.');
$post = test_update($admin_con, $a_admin, 'Admin updates own area.');
$a_admin = Areas::instance()->get($a_admin->id);

// Test the 404s
$url = $helper->tc_url('area', 'edit', $a_id_admin + 1);
$admin_con->test_not_found($url, null, "Admin edits invalid area id");
$url = $helper->tc_url('area', 'update', $a_id_admin + 1);
$admin_con->test_not_found($url, $post, "Admin updates invalid area id");

// Test the 4032
$url = $helper->tc_url('area', 'edit', $a_id_admin);
$contrib_con->test_no_access($url, null, "Contributor edits admin's area");
$url = $helper->tc_url('area', 'update', $a_id_admin);
$contrib_con->test_no_access($url, $post, "Contributor updates admin's area");



// TEST INDEX
function test_index($con, $for_admin, $name) {
    $con->test_fetch($con->helper->tc_url('area', 'index'), null, 200,
        "Should have status 200 on area index ($name).");

    $areas = Areas::instance()->list_simple();
    $con->assert(!empty($areas), "Should test with area(s) present");
    foreach($areas as $area) {
        $con->ensure_xpath("//td[text()='$area->id']", 1,
            "Should show area id ($name).");
        $con->ensure_xpath("//td[text()='$area->name']", 1,
            "Should show area name ($name).");
        $con->ensure_xpath("//td/a[contains(@href, 'area_id=$area->id')]", null,
            "Should link to the area's tours  ($name).");
    }

    // test admin fields
    $n = $for_admin ? count($areas) : 0;
    $con->ensure_xpath("//a[text()='Bearbeiten']", $n,
        "Should show $n edit links ($name).");
    $con->ensure_xpath("//a[text()='Löschen']", $n,
        "Should show $n delete links ($name).");

    $n = $for_admin ? 1 : 0;
    $con->ensure_xpath("//a[text()='Gebiet hinzufügen']", $n,
        "Should show $n links to add area ($name).");
}



test_index($admin_con, true, 'Admin visits area index');
test_index($contrib_con, false, 'contributor visits area index');




// CLEANUP
Areas::instance()->delete($a_admin);

// invalidate logins
$admin_con->invalidate_login();
$contrib_con->invalidate_login();

// report results
$admin_con->report();
$contrib_con->report();

?>