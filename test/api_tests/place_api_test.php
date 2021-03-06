<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/../wp_test_connection.php');

// test for the presence of form fields needed for place creation
function test_place_form_fields($con, $page_type) {
    $con->ensure_xpath("//input[@name='shtm_place[name]']", 1,
        "Should have name input on $page_type.");
    $con->ensure_xpath("//input[@name='shtm_place[lat]']", 1,
        "Should have latitute input on $page_type.");
    $con->ensure_xpath("//input[@name='shtm_place[lon]']", 1,
        "Should have longitude input on $page_type.");
}

// test for the presence of the right values in places form field
function test_place_form_fields_values($con, $name, $lat, $lon, $page_type) {
    $con->test_input_field('shtm_place[name]', $name, $page_type);
    $con->test_input_field('shtm_place[lat]', $lat, $page_type);
    $con->test_input_field('shtm_place[lon]', $lon, $page_type);
}

// get instances of test connections and add them to the test cases
$admin_con = new WPTestConnection('Places API Test (admin)',
    'test-admin', 'test-admin', $helper->config->wp_url);
$contrib_con = new WPTestConnection('Places API Test (contributor)',
    'test-contributor', 'test-contributor', $helper->config->wp_url);

global $shtm_test_cases;
if(empty($shtm_test_cases)) {
    $shtm_test_cases = array();
}
$shtm_test_cases[] = $admin_con;
$shtm_test_cases[] = $contrib_con;


// TEST PLACE CREATE
$name = 'test name ' . $helper->random_str();
$lat = $helper->random_float(-90, 90);
$lon = $helper->random_float(-180, 180);
$post = array(
    'shtm_place[area_id]' => Areas::instance()->first_id(),
    'shtm_place[name]' => $name,
    'shtm_place[lat]' => $lat,
    'shtm_place[lon]' => $lon
);
// Get the right precision for testing coordinate values
$lat_repr = $helper->coord_value_string($lat);
$lon_repr = $helper->coord_value_string($lon);

// Create by fetching the create page
$admin_con->test_fetch($helper->tc_url('place', 'create'), $post, 200,
    "Should have status 200 on place creation.");

// Test for the success message and determine the id
$admin_con->test_success_message("Ort erstellt", "place create");
$id = $helper->db_highest_id($helper->config->places_table);
// Test for the redirect to place edit
$admin_con->test_redirect_params('place', 'edit', $id);

// test a bad create with incomplete post
$bad_post = array(
    'shtm_place[name]' => $name,
    'shtm_place[lat]' => $lat
);
$admin_con->test_fetch($helper->tc_url('place', 'create'), $bad_post, 400,
    "Should have status 400 on bad place creation.");
$admin_con->ensure_xpath("//div[contains(@class, 'shtm_message_error')]", null,
    "Should show error message on bad place creation.");

// TEST PLACE EDIT
$admin_con->test_fetch($helper->tc_url('place', 'edit', $id), null, 200,
    "Should have status 200 on place edit.");
$admin_con->test_simple_page("place edit");

// Test for the presence of the right form fields and the coordinate
test_place_form_fields($admin_con, "place edit");
$admin_con->test_coordinate($lat_repr, $lon_repr, "place edit");

// Test for the right values in the form fields
test_place_form_fields_values($admin_con,
    $name, $lat_repr, $lon_repr, "place edit");

// TEST PLACE UPDATE
$new_name = 'update test name ' . $helper->random_str();
$new_lat = $helper->random_float(-90, 90);
$new_lon = $helper->random_float(-180, 180);
$update_post = array(
    'shtm_place[name]' => $new_name,
    'shtm_place[lat]' => $new_lat,
    'shtm_place[lon]' => $new_lon
);
$new_lat_repr = $helper->coord_value_string($new_lat);
$new_lon_repr = $helper->coord_value_string($new_lon);

$admin_con->test_fetch($helper->tc_url('place', 'update', $id), $update_post, 200,
    "Should have status 200 on update post");
// test that we have been redirected to place edit and the form is present
test_place_form_fields($admin_con, "place update");
test_place_form_fields_values($admin_con,
    $new_name, $new_lat_repr, $new_lon_repr, "place update");


// TEST PLACE NEW
$admin_con->test_fetch($helper->tc_url('place', 'new'), null, 200,
    "Should have status 200 on place new.");
$admin_con->test_simple_page("place new");
$admin_con->test_page_heading("Neuer Ort", "place new");
test_place_form_fields($admin_con, "place new");

test_place_form_fields($admin_con, "place new");
$admin_con->ensure_xpath(
    "//div[@data-cid='-1' and not(@data-lat='') and not(@data-lon='')]", 1,
    "Should have a coordinate with non-empty values but invalid id.");

// TEST PLACE INDEX
$admin_con->test_fetch($helper->tc_url('place', 'index'), null, 200,
    "Should have status 200 on place index.");
$admin_con->test_simple_page("place index");
$admin_con->test_page_heading("Orte", "place index");

$admin_con->ensure_xpath("//table[@id='shtm_place_index']", 1,
    "Should show the places table on place index");
$admin_con->ensure_xpath("//a[text()='Ort hinzufügen']", 1,
    "Should have a link to create new place.");
$admin_con->ensure_xpath("//a[text()='Löschen']", null,
    "Should show at least one Link to delete on place index.");
$admin_con->ensure_xpath("//a[text()='Bearbeiten']", null,
    "Should show at least one Link to edit on place index.");


// TEST PLACE DELETE
$admin_con->test_fetch($helper->tc_url('place', 'delete', $id), null, 200,
    "Should have status 200 on place delete.");
$admin_con->ensure_xpath("//div[@id='shtm_delete_place']", 1,
    "Should have a delete button on delete place.");
$admin_con->test_coordinate($new_lat_repr, $new_lon_repr, "place delete");

// error page should be shown for an invalid id
$invalid_id = $helper->db_highest_id($helper->config->places_table) + 1;
$admin_con->test_fetch($helper->tc_url('place', 'delete', $invalid_id), null, 404,
    "Should have status 404 on delete place with invalid id.");
$admin_con->test_error_message("Kein Ort", "delete place with invalid id");

// error page should be shown to the wrong user
$contrib_con->test_no_access($helper->tc_url('place', 'delete', $id), null,
    'contributor tries to delete other\'s place');
$contrib_con->ensure_xpath("//div[@id='shtm_delete_place']", 0,
    "Should have no delete button on delete place by wrong user.");


// TEST PLACE DESTROY
// first test to delete by the wrong user
$contrib_con->test_no_access($helper->tc_url('place', 'destroy', $id), null,
    'contributor tries to destroy other\'s place');
$contrib_con->test_error_message("Berechtigung", "destroy place by wrong user");

// then the right user should actually be able to destroy
$admin_con->test_fetch($helper->tc_url('place', 'destroy', $id), null, 200,
    "Should have status 200 on place destroy.");
$new_highest_id = $helper->db_highest_id($helper->config->places_table);
$msg = "Should have deleted highest id: ${id} > ${new_highest_id}";
if($new_highest_id < $id) {
    $admin_con->note_pass($msg);
} else {
    $admin_con->note_fail($msg);
}
$admin_con->ensure_xpath("//table[@id='shtm_place_index']", 1,
    "Should show place index view after place destruction");
$admin_con->test_success_message("Ort gelöscht", "place destruction");

// invalidate logins for places tests
$admin_con->invalidate_login();
$contrib_con->invalidate_login();

// report results
$admin_con->report();
$contrib_con->report();

?>