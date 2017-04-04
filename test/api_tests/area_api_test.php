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


// TEST NEW
function test_new_area($con, $name) {
    $url = $con->helper->tc_url('area', 'new');
    $con->test_fetch($url, null, 200,
        "Should have status 200 on area new ($name).");

    // test for the presence of input fields
    $empty_coord_value = $con->helper->coord_value_string(0.0);
    $con->test_input_field('shtm_area[name]', '', $name);
    $con->test_input_field('shtm_area[c1_lat]', $empty_coord_value, $name);
    $con->test_input_field('shtm_area[c1_lon]', $empty_coord_value, $name);
    $con->test_input_field('shtm_area[c2_lat]', $empty_coord_value, $name);
    $con->test_input_field('shtm_area[c2_lon]', $empty_coord_value, $name);
}

test_new_area($admin_con, 'Admin visits area new');

// the contributor should not be able to visit area new
$contrib_con->test_no_access($helper->tc_url('area', 'new'), null,
    'Contributor tries to visit area new');



// TEST CREATE
function make_area_post_data($area) {
    return array(
        'shtm_area[name]' => $area->name,
        'shtm_area[c1_lat]' => $area->coordinate1->lat,
        'shtm_area[c1_lon]' => $area->coordinate1->lon,
        'shtm_area[c2_lat]' => $area->coordinate2->lat,
        'shtm_area[c2_lon]' => $area->coordinate2->lon
    );
}

function test_create_area($con, $name) {
    $area = $con->helper->make_area();

    $post = make_area_post_data($area);
    $id_before = Areas::instance()->last_id();
    $url = $con->helper->tc_url('area', 'create');
    $con->test_fetch($url, $post, 200,
        "Should have status 200 on area create ($name).");

    // Should have redirected to the edit route with a new id
    $con->test_redirect_params('area', 'edit');
    $id = $con->test_redirect_param('shtm_id');
    $con->assert($id > $id_before, "Should give new id on redirect ($name).");

    $from_db = Areas::instance()->get($id);
    $con->assert(!empty($from_db),
        "Should be able to get the new area ($name).");

    // test the edit page we were redirected to for both area version to check
    // that values match
    test_edit_area($con, $area, $name, false);
    test_edit_area($con, $from_db, $name, false);

    return $from_db;
}


// Create a new area by testing the create route
$a_admin = test_create_area($admin_con, 'Admin creates area');

// The contributor should not be able to do this
$post = make_area_post_data($a_admin);
$contrib_con->test_no_access($helper->tc_url('area', 'create'), $post,
    'Contributor tries to create tour.');


// TEST EDIT & UPDATE
function test_edit_area($con, $area, $name, $do_fetch = true) {
    if($do_fetch) {
        $url = $con->helper->tc_url('area', 'edit', $area->id);
        $con->test_fetch($url, null, 200,
            "Should have status 200 on area edit ($name).");
    }

    // test for the presence of correct input values
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

function test_update_area($con, $area, $name) {
    $other = $con->helper->make_area();

    $post = make_area_post_data($other);

    $url = $con->helper->tc_url('area', 'update', $area->id);
    $con->test_fetch($url, $post, 200,
        "Should have status 200 on area update ($name).");

    $con->test_redirect_params('area', 'edit', $id);
    test_edit_area($con, $other, $name, false);

    return $post;
}

// Test edit and update for admin, re-fetch area to get the updated model
test_edit_area($admin_con, $a_admin, 'Admin visits own area edit.');
$post = test_update_area($admin_con, $a_admin, 'Admin updates own area.');
$a_admin = Areas::instance()->get($a_admin->id);

// Test the 404s
$url = $helper->tc_url('area', 'edit', $a_admin->id + 1);
$admin_con->test_not_found($url, null, "Admin edits invalid area id");
$url = $helper->tc_url('area', 'update', $a_admin->id + 1);
$admin_con->test_not_found($url, $post, "Admin updates invalid area id");

// Test the 403s
$url = $helper->tc_url('area', 'edit', $a_admin->id);
$contrib_con->test_no_access($url, null, "Contributor edits admin's area");
$url = $helper->tc_url('area', 'update', $a_admin->id);
$contrib_con->test_no_access($url, $post, "Contributor updates admin's area");



// TEST INDEX
function test_index_areas($con, $for_admin, $name) {
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

test_index_areas($admin_con, true, 'Admin visits area index');
test_index_areas($contrib_con, false, 'contributor visits area index');



// TEST DELETE & DESTROY
function create_tour_for_area($area) {
    $tour = $con->helper->make_tour();
    $tour->area_id = $area->id;
    return Tours::instance()->save($tour);
}

function test_delete_area($con, $area, $name) {
    // Some vars for both test conditions (with and without tour connected)
    $url = $con->helper->tc_url('area', 'delete', $area->id);
    $button_xp = "//button[@type='submit' and text()='Löschen']";
    $name_xp = "//li[contains(@text, $area->name)]";

    // assert that the area has no tour at first
    $tours = Tours::instance()->list_by_area($area->id);
    $con->assert(empty($areas),
        "Should at first test area without tours ($name).");

    // Fetching the delete page for an area without tours should get us the
    // normal delete page with a delete button
    $con->test_fetch($url, null, 200,
        "Should have status 200 on area delete withour tour ($name).");
    $con->ensure_xpath($button_xp, 1, "Should have a delete button ($name).");
    $con->ensure_xpath($name_xp, 1, "Should show the areas name ($name).");

    // The delete page for an area with tours then should not show any buttons
    // but only an info text, detaling that the tour cannot be deleted
    $tour = create_tour_for_area($area);
    $tours = Tours::instance()->list_by_area($area->id);
    $con->assert(count($tours) === 1,
        "Should then test area with a tour ($name).");
    $con->test_fetch($url, null, 200,
        "Should have status 200 on area delete with tour ($name).");
    $con->ensure_xpath($button_xp, 0, "Should not have delete button ($name).");
    $con->ensure_xpath($name_xp, 1, "Should show the areas name ($name).");
    $con->ensure_xpath("//div[contains(@class, 'shtm_message_info')]", 1,
        "Should show infor message on area delete with tour ($name).");
}

function test_destroy_area($con, $area, $name) {
    $url = $con->helper->tc_url('area', 'delete', $area->id);
    $count = Areas::instance()->count();

    // assert that the area has a tour at first
    $tours = Tours::instance()->list_by_area($area->id);
    $con->assert(count($tours) === 1,
        "Should at first test area with a tour ($name).");

    // trying to destroy an area with tours should result in an error
    $con->test_bad_request($url, null, $name);
    $con->assert($count === Areas::instance()->count(),
        "Should not have removed any areas ($name).");

    // now delete the tour and test the actual destroy
    Tours::instance()->delete($tours[0]);
    $tours = Tours::instance()->list_by_area($area->id);
    $con->assert(empty($areas), "Should then test area without tours ($name).");

    $con->test_fetch($url, null, 200,
        "Should have status 200 on tour destroy ($name).");
    $con->test_redirect_params('area', 'index');
    $con->assert((Areas::instance()->count() - $count) === 1,
        "Should have destroyed an area ($name).");
    $con->assert(!Areas::instance()->valid_id($area->id),
        "Area id should now be invalid ($name).");
}



// CLEANUP
Areas::instance()->delete($a_admin);

// invalidate logins
$admin_con->invalidate_login();
$contrib_con->invalidate_login();

// report results
$admin_con->report();
$contrib_con->report();

?>