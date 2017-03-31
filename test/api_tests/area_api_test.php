<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/../wp_test_connection.php');

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


// TEST INDEX
function test_index($con, $for_admin, $name) {
    $con->test_fetch($con->helper->tc_url('area', 'index'), null, 200,
        "Should have status 200 on tour index ($name).");

    $areas = Areas::instance()->list_simple();
    $con->assert(!empty($areas), "Should test with area(s) present");
    foreach($areas as $area) {
        $con->ensure_xpath("//td[text()='$area->id']", 1,
            "Should show area id ($name).");
        $con->ensure_xpath("//td[text()='$area->name']", 1,
            "Should show area name ($name).");
        $con->ensure_xpath("//td/a[contains(@href, 'area_id=$area->id')]", 1,
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
// invalidate logins
$admin_con->invalidate_login();
$contrib_con->invalidate_login();

// report results
$admin_con->report();
$contrib_con->report();

?>