<?php
namespace SmartHistoryTourManager;

// redirect all debug logging to stdout
require_once(dirname(__FILE__) . '/../logging.php');
Logging::set_output(Logging::TO_STDOUT);

// Make the test helper and load the wordpress envrionment, so that tests and
// tested functions are able to call the wordpress api (especially the wpdb
// database connection).
require_once(dirname(__FILE__) . '/test_helper.php');
$helper = new TestHelper();
require_once($helper->config->wp_load_script);

// a global variable to collect all test cases in
global $shtm_test_cases;

// require each unit test, causing it to run and add itself to the
// shtm_test_cases variable
$unit_test_dir = dirname(__FILE__) . '/unit_tests';
require_once($unit_test_dir . '/places_test.php');
require_once($unit_test_dir . '/areas_test.php');
require_once($unit_test_dir . '/mapstops_test.php');
require_once($unit_test_dir . '/tours_test.php');

// require each api test in the same manner
$api_test_dir = dirname(__FILE__) . '/api_tests';
require_once($api_test_dir . '/place_api_test.php');
require_once($api_test_dir . '/area_api_test.php');
require_once($api_test_dir . '/tour_api_test.php');

// output reports for all test cases
echo "---" . PHP_EOL;
foreach($shtm_test_cases as $test_case) {
    $test_case->report();
}

?>