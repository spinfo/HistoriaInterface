<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/../wp_test_connection.php');
require_once(dirname(__FILE__) . '/../test_helper.php');

// Create the test cases and add them to the global test cases
$admin_con = new WPTestConnection('Mapstops API Test (admin)',
    'test-admin', 'test-admin', $helper->config->wp_url);
$contrib_con = new WPTestConnection('Mapstops API Test (contributor)',
    'test-contributor', 'test-contributor', $helper->config->wp_url);

global $shtm_test_cases;
if(empty($shtm_test_cases)) {
    $shtm_test_cases = array();
}
$shtm_test_cases[] = $admin_con;
$shtm_test_cases[] = $contrib_con;


// Create a mapstop that we can work with
// TODO: Remove once this is done by testing the 'create' route
$helper = new TestHelper();
$m_admin = $helper->make_mapstop();
$m_id_admin = Mapstops::instance()->insert($m_admin);


// TEST DELETE
function test_delete($con, $mapstop, $name) {
    $url = $con->helper->tc_url('mapstop', 'delete', $mapstop->id);
    $con->test_fetch($url, null, 200,
        "Should have status 200 on mapstop delete ($name).");

    $con->ensure_xpath("//li[contains(., '$mapstop->id')]", 1,
        "Should show id on mapstop delete ($name).");
    $con->ensure_xpath("//li[contains(., '$mapstop->name')]", 1,
        "Should show name on mapstop delete ($name).");
    $con->ensure_xpath("//li[contains(., '$mapstop->description')]", 1,
        "Should show description on mapstop delete ($name).");
}

test_delete($admin_con, $m_admin, "admin for own mapstop");

$url404 = $helper->tc_url('mapstop', 'delete', ($m_id_admin + 1));
$admin_con->test_not_found($url404, null, "admin - delete invalid mapstop id");


// TEST DESTROY
function test_destroy($con, $id, $tour_id, $name) {
    $con->assert(Mapstops::instance()->valid_id($id),
        "Should test to destroy a valid mapstop id ($name).");

    $con->test_fetch($con->helper->tc_url('mapstop', 'destroy', $id), null, 200,
        "Should have status 200 on tour destroy ($name).");

    // should redirect to the tour stops' edit page
    $con->test_redirect_param('shtm_c', 'tour');
    $con->test_redirect_param('shtm_a', 'edit_stops');
    $con->test_redirect_param('shtm_id', $tour_id);

    $con->assert(!Mapstops::instance()->valid_id($id),
        "Should have removed the mapstop from db ($name).");
}
test_destroy($admin_con, $m_id_admin, $m_admin->tour_id, "admin - own mapstop");

$url404 = $helper->tc_url('mapstop', 'destroy', ($m_id_admin + 1));
$admin_con->test_not_found($url404, null, "admin - destroy invalid mapstop id");

// cleanup
$helper->delete_wp_posts_created();


?>