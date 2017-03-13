<?php
namespace SmartHistoryTourManager;

// make the test helper and load the wordpress envrionment
require_once(dirname(__FILE__) . '/test_helper.php');
$helper = new TestHelper();
require_once($helper->config->wp_load_script);
// hook to log all queries
// add_filter('query', 'SmartHistoryTourManager\debug_log_query');

require_once(dirname(__FILE__) . '/mycurl.php');
require_once(dirname(__FILE__) . '/test_case.php');
require_once(dirname(__FILE__) . '/wp_test_connection.php');
require_once(dirname(__FILE__) . '/areas_test.php');
require_once(dirname(__FILE__) . '/places_test.php');
require_once(dirname(__FILE__) . '/mapstops_test.php');
require_once(dirname(__FILE__) . '/tours_test.php');
require_once(dirname(__FILE__) . '/../logging.php');

// redirect debug log to std out
Logging::set_output(Logging::TO_STDOUT);

// performs tests common for normal pages retrieved by a simple GET
function test_simple_page($test_connection, $page_type) {
    $test_connection->ensure_xpath("//div[contains(@class, 'shtm_message')]", 0,
        "Should not show any message on ${page_type}.");
}

// test for the presence of an h1-heading on the page retrieved last by the
// given test connection
function test_page_heading($test_connection, $heading, $page_type) {
    $test_connection->ensure_xpath("//h1[text()='${heading}']", 1,
        "Should have the right heading on ${page_type}.");
}

// test for the presence of form fields needed for place creation
function test_place_form_fields($test_connection, $page_type) {
    $test_connection->ensure_xpath("//label[@for='shtm_name']", 1,
        "Should have name label on $page_type.");
    $test_connection->ensure_xpath("//label[@for='shtm_lat']", 1,
        "Should have latitude label on $page_type.");
    $test_connection->ensure_xpath("//label[@for='shtm_lon']", 1,
        "Should have longitude label on $page_type.");
    $test_connection->ensure_xpath("//input[@name='shtm_place[name]']", 1,
        "Should have name input on $page_type.");
    $test_connection->ensure_xpath("//input[@name='shtm_place[lat]']", 1,
        "Should have latitute input on $page_type.");
    $test_connection->ensure_xpath("//input[@name='shtm_place[lon]']", 1,
        "Should have longitude input on $page_type.");
}

// test for the presence of the right values in places form field
function test_place_form_fields_values($test_connection, $name, $lat, $lon, $page_type) {
    $test_connection->ensure_xpath("//input[@name='shtm_place[name]' and @value='$name']", 1,
        "Should have the right name on $page_type.");
    $test_connection->ensure_xpath("//input[@name='shtm_place[lat]' and @value='$lat']", 1,
        "Should have the right latitude on $page_type.");
    $test_connection->ensure_xpath("//input[@name='shtm_place[lon]' and @value='$lon']", 1,
        "Should have the right longitude on $page_type.");
}

// test for the presence of an error message containing the specified text
function test_error_message($test_connection, $contained_text, $page_type) {
    $test_connection->ensure_xpath(
        "//div[contains(@class, 'shtm_message_error') and contains(., '$contained_text')]", 1,
        "Should show error message with text '$contained_text' on $page_type."
    );
}

function test_success_message($test_connection, $contained_text, $page_type) {
    $test_connection->ensure_xpath(
        "//div[contains(@class, 'shtm_message_success') and contains(., '$contained_text')]", 1,
        "Should show success message with text '$contained_text' on $page_type."
    );
}


// ACTUAL TESTING

// get instances of test connections
$admin_test = new WPTestConnection('Places API Test (admin)',
    'test-admin', 'test-admin', $helper->config->wp_url);
$contributor_test = new WPTestConnection('Places API Test (contributor)',
    'test-contributor', 'test-contributor', $helper->config->wp_url);

$test_cases = array();
$test_cases[] = $admin_test;
$test_cases[] = $contributor_test;


// PLACE CREATE
$name = 'test name ' . $helper->random_str();
$lat = $helper->random_float(-90, 90);
$lon = $helper->random_float(-180, 180);
$post = array(
    'shtm_place[name]' => $name,
    'shtm_place[lat]' => $lat,
    'shtm_place[lon]' => $lon
);
// Coordinate values have a precision of exactly six digits after the decimal
// point
$lat_repr = sprintf("%.6f", $lat);
$lon_repr = sprintf("%.6f", $lon);

// Create by fetching the create page
$admin_test->test_fetch($helper->tc_url('place', 'create'), $post, 200,
    "Should have status 200 on place creation.");

// Test for the success message
test_success_message($admin_test, "Ort erstellt", "place create");
// Test for the redirect to place edit
test_page_heading($admin_test, "Ort bearbeiten", "place create");

// test a bad create with incomplete post
$bad_post = array(
    'shtm_place[name]' => $name,
    'shtm_place[lat]' => $lat
);
$admin_test->test_fetch($helper->tc_url('place', 'create'), $bad_post, 400,
    "Should have status 400 on bad place creation.");
$admin_test->ensure_xpath("//div[contains(@class, 'shtm_message_error')]", null,
    "Should show error message on bad place creation.");

// PLACE EDIT
$id = $helper->db_highest_id($helper->config->places_table);
$admin_test->test_fetch($helper->tc_url('place', 'edit', $id), null, 200,
    "Should have status 200 on place edit.");
test_simple_page($admin_test, "place edit");
test_page_heading($admin_test, "Ort bearbeiten", "place edit");

// Test for the presence of the right form fields
test_place_form_fields($admin_test, "place edit");

// Test for the right values in the form fields
test_place_form_fields_values($admin_test,
    $name, $lat_repr, $lon_repr, "place edit");

// PLACE UPDATE
$new_name = 'update test name ' . $helper->random_str();
$new_lat = $helper->random_float(-90, 90);
$new_lon = $helper->random_float(-180, 180);
$update_post = array(
    'shtm_place[name]' => $new_name,
    'shtm_place[lat]' => $new_lat,
    'shtm_place[lon]' => $new_lon
);
$new_lat_repr = sprintf("%.6f", $new_lat);
$new_lon_repr = sprintf("%.6f", $new_lon);

$admin_test->test_fetch($helper->tc_url('place', 'update', $id), $update_post, 200,
    "Should have status 200 on update post");
// test that we have been redirected to place edit and the form is present
test_page_heading($admin_test, "Ort bearbeiten", "place update");
test_place_form_fields($admin_test, "place update");
test_place_form_fields_values($admin_test,
    $new_name, $new_lat_repr, $new_lon_repr, "place update");


// PLACE NEW
$admin_test->test_fetch($helper->tc_url('place', 'new'), null, 200,
    "Should have status 200 on place new.");
test_simple_page($admin_test, "place new");
test_page_heading($admin_test, "Neuer Ort", "place new");
test_place_form_fields($admin_test, "place new");

$empty_lonlat = sprintf("%.6f", 0.0);
test_place_form_fields_values($admin_test,
    "", $empty_lonlat, $empty_lonlat, "place new");


// PLACES - INDEX
$admin_test->test_fetch($helper->tc_url('place', 'index'), null, 200,
    "Should have status 200 on place index.");
test_simple_page($admin_test, "place index");
test_page_heading($admin_test, "Orte", "place index");

$admin_test->ensure_xpath("//table[@id='shtm_place_index']", 1,
    "Should show the places table on place index");
$admin_test->ensure_xpath("//a[text()='Neuen Ort anlegen']", 1,
    "Should have a link to create new place.");
$admin_test->ensure_xpath("//a[text()='Löschen']", null,
    "Should show at least one Link to delete on place index.");
$admin_test->ensure_xpath("//a[text()='Bearbeiten']", null,
    "Should show at least one Link to edit on place index.");


// PLACES DELETE
$admin_test->test_fetch($helper->tc_url('place', 'delete', $id), null, 200,
    "Should have status 200 on place delete.");
$admin_test->ensure_xpath("//div[@id='shtm_delete_place']", 1,
    "Should have a delete button on delete place.");

// error page should be shown for an invalid id
$invalid_id = $helper->db_highest_id($helper->config->places_table) + 1;
$admin_test->test_fetch($helper->tc_url('place', 'delete', $invalid_id), null, 404,
    "Should have status 404 on delete place with invalid id.");
test_error_message($admin_test, "Kein Ort", "delete place with invalid id");

// error page should be shown to the wrong user
$contributor_test->test_fetch($helper->tc_url('place', 'delete', $id), null, 403,
    "Should have status 403 on place delete by wrong user.");
test_error_message($contributor_test, "Berechtigung", "delete place by wrong user");
$contributor_test->ensure_xpath("//div[@id='shtm_delete_place']", 0,
    "Should have no delete button on delete place by wrong user.");


// PLACES DESTROY
// first test to delete by the wrong user
$contributor_test->test_fetch($helper->tc_url('place', 'destroy', $id), null, 403,
    "Should have status 403 on place destroy by wrong user.");
test_error_message($contributor_test, "Berechtigung", "destroy place by wrong user");

// then the right user should actually be able to destroy
$admin_test->test_fetch($helper->tc_url('place', 'destroy', $id), null, 200,
    "Should have status 200 on place destroy.");
$new_highest_id = $helper->db_highest_id($helper->config->places_table);
$msg = "Should have deleted highest id: ${id} > ${new_highest_id}";
if($new_highest_id < $id) {
    $admin_test->note_pass($msg);
} else {
    $admin_test->note_fail($msg);
}
$admin_test->ensure_xpath("//table[@id='shtm_place_index']", 1,
    "Should show place index view after place destruction");
test_success_message($admin_test, "Ort gelöscht", "place destruction");

// invalidate logins for places tests
$admin_test->invalidate_login();
$contributor_test->invalidate_login();


// Run the unit tests for areas
$areas_unit_test = new AreasTest();
$test_cases[] = $areas_unit_test;
$areas_unit_test->do_test();


// AREAS TESTS
$admin_test = new WPTestConnection('Areas API Test (admin)',
    'test-admin', 'test-admin', $helper->config->wp_url);
$contributor_test = new WPTestConnection('Areas API Test (contributor)',
    'test-contributor', 'test-contributor', $helper->config->wp_url);
$test_cases[] = $admin_test;
$test_cases[] = $contributor_test;

// set_current_area route
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

test_set_current_area($admin_test, "admin");
test_set_current_area($contributor_test, "contributor");

// Unit test for places
$places_unit_test = new PlacesTest();
$test_cases[] = $places_unit_test;
$places_unit_test->do_test();

// Unit test for mapstops
$mapstops_unit_test = new MapstopsTest();
$test_cases[] = $mapstops_unit_test;
$mapstops_unit_test->do_test();

// Unit test for tours
$tours_unit_test = new ToursTest();
$test_cases[] = $tours_unit_test;
$tours_unit_test->do_test();


// Report totals for all tests done
echo "---" . PHP_EOL;
foreach($test_cases as $test_case) {
    $test_case->report();
}


define('SHTM_ENV_TEST', true);
?>