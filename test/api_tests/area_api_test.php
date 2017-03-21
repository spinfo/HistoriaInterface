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

// invalidate logins
$admin_con->invalidate_login();
$contrib_con->invalidate_login();

// report results
$admin_con->report();
$contrib_con->report();

?>