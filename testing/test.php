<?php
namespace SmartHistoryTourCreator;

require_once( dirname(__FILE__) . '/mycurl.php');


class TestHelper {

    public $config;

    private $mysqli;

    public function __construct() {
        // Parse the test config from a file into an object
        $this->config = (object) parse_ini_file(dirname(__FILE__) . '/test.config');
        $this->mysqli = $this->setup_mysqli();
    }

    private function setup_mysqli() {
        // A mysql connection to check data
        $mysqli = new \mysqli(
            $this->config->mysql_server, $this->config->mysql_user,
            $this->config->mysql_pass, $this->config->mysql_db);

        if ($mysqli->connect_errno) {
            echo "Failed to connect to MySQL: ("
                . $mysqli->connect_errno . ") " . $mysqli->connect_error;
            exit(1);
        }
        return $mysqli;
    }

    // sets up a url for the tour creator admin page
    public function tc_url($controller, $action, $id = null) {
        $url = $this->config->wp_url . $this->config->tour_creator_prefix;
        $url .= '&shtm_c=' . $controller;
        $url .= '&shtm_a=' . $action;
        if(isset($id)) {
            $url .= '&shtm_id=' . $id;
        }
        return $url;
    }

    public function random_str($length = 10) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $characters_length = strlen($characters);
        $random_string = '';
        for ($i = 0; $i < $length; $i++) {
            $random_string .= $characters[rand(0, $characters_length - 1)];
        }
        return $random_string;
    }

    public function random_float($min = 1, $max = 10) {
        $mul = 0;
        while($mul == 0) {
            $mul = rand($min, $max);
        }
        return (mt_rand() / mt_getrandmax()) * $mul;
    }


    public function db_highest_id($table_name) {
        $sql = "SELECT id FROM $table_name ORDER BY id DESC LIMIT 0,1";
        $result = $this->mysqli->query($sql);
        if(!$result) {
            echo "ERROR: Failed db query: $sql\n";
            exit(1);
        }
        $result_arr = $result->fetch_array();
        if(empty($result_arr) || !isset($result_arr[0])) {
            echo "ERROR: Retrieved empty result: $sql\n";
            exit(1);
        }
        return $result_arr[0];
    }

}


/**
 * A class to use for connections to a wordpress page. Handles the login and
 * basic testing.
 */
class WPTestConnection {

    private static $login_path = '/wp-login.php';

    private $user;

    private $pass;

    private $wp_url;

    private $cookie_dir;

    private $mycurl;

    // A DomXPath representation of the last result
    private $result;

    public $tests_passed;
    public $tests_failed;

    public function __construct($user, $pass, $wp_url, $cookie_dir = '/tmp') {
        $this->user = $user;
        $this->pass = $pass;
        $this->wp_url = $wp_url;

        // setup the curl tool to use for the connection
        $this->mycurl = new mycurl($this->wp_url);

        // setup the cookie file to be specific to the user we will log in as
        $this->cookie_file = $cookie_dir . '/cookie-wptestconnection-' . $user;
        $this->mycurl->setCookieFileLocation($this->cookie_file);

        // login the user
        $this->login();

        // setup some numbers to count failed and passed tests
        $this->tests_passed = 0;
        $this->tests_failed = 0;
    }

    public function __destruct() {
        // remove the cookie file on destruction to ensure a fresh login on
        // object creation
        unlink($this->cookie_file);

    }

    private function login() {
        $url = $this->wp_url . self::$login_path;
        $post_data = array(
            'log' => $this->user,
            'pwd' => $this->pass,
            'wp-submit' => 'Log+In'
        );

        $this->mycurl->setPost($post_data);
        $this->mycurl->createCurl($url);

        if ($this->mycurl->getHttpStatus() < 400) {
            $this->log_ok("Status ok for user login: " . $this->user);
        } else {
            $this->log_error("Could not log in");
        }
    }

    private function log($msg = '') {
        echo $msg . ' - ' . $this->mycurl->_url . ' (' . $this->mycurl->getHttpStatus() . ")\n";
    }

    private function log_ok($msg = '') {
        $this->log('OK: ' . $msg);
    }

    private function log_error($msg = '') {
        $this->log('ERROR: ' . $msg);
    }

    public function note_pass($msg) {
        $this->tests_passed += 1;
        $this->log_ok($msg);
    }

    public function note_fail($msg) {
        $this->tests_failed += 1;
        $this->log_error($msg);
    }

    public function report() {
        $total = $this->tests_passed + $this->tests_failed;
        echo "Passed $this->tests_passed/$total ($this->user)\n";
    }

    // retrieves the given url and checks if the http status matches the
    // expected status
    public function test_fetch($url, $post, $expected_status, $msg) {
        // unset the last page loaded
        $this->result = null;

        // set or unset post parameters as needed (if post is null this will
        // be a GET request)
        if(isset($post)) {
            $this->mycurl->setPost($post);
        } else {
            $this->mycurl->removePost();
        }

        // setting _url is not strictly necessary, but makes for easier logging
        // later
        $this->mycurl->_url = $url;
        $this->mycurl->createCurl($url);
        if($this->mycurl->getHttpStatus() == $expected_status) {
            $this->note_pass($msg);
        } else {
            $this->note_fail($msg);
        }

        // parse the result into a DomXPath object to examine it later
        $this->setup_dom_xpath_result($this->mycurl->__tostring());
    }

    private function setup_dom_xpath_result($html_str) {
        // (errors/warnings while loading the dom are suppresed)
        libxml_use_internal_errors(true);
        $doc = new \DomDocument;
        $doc->loadHTML($html_str);
        $this->result = new \DomXPath($doc);
    }

    /**
     * Tests for the existence of xpath nodes in a result retrieved earlier.
     * Fails if the node amount retrieved does not match the expected count.
     * (Disregards node count if set to null.)
     */
    public function ensure_xpath($xpath, $expected_node_count, $msg) {
        if(!isset($this->result)) {
            $this->note_fail("No document to test: " . $msg);
            return;
        }

        $nodes = $this->result->query($xpath);

        if(is_null($expected_node_count)) {
            if($nodes->length <= 0) {
                $this->note_fail($msg . " (No nodes found for: '$xpath'.)");
                return;
            }
        } else {
            if($nodes->length != $expected_node_count) {
                $this->note_fail($msg . " (Expected $expected_node_count node(s), got $nodes->length on '$xpath'.)");
                return;
            }
        }

        $this->note_pass($msg);
    }

}




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
// make the test helper
$helper = new TestHelper();
// get instances of test connections
$admin_test = new WPTestConnection('test-admin', 'test-admin',
    $helper->config->wp_url);
$contributor_test = new WPTestConnection('test-contributor', 'test-contributor',
    $helper->config->wp_url);


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



// Report totals for all tests done
$admin_test->report();
$contributor_test->report();


?>