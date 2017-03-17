<?php
namespace SmartHistoryTourManager;

/**
 * Depending on the arguments, this runs:
 *  ARG: [none]         all tests
 *  ARG: 'unit'         all unit tests
 *  ARG: 'api'          all api tests
 *  ARG: 'test-name'    the test by that name, e.g. 'places' or 'place_api'
 *
 * EXAMPLE: Running
 *
 *  'php run_tests.php unit area_api'
 *
 * will run all unit tests, followed by the area api test.
 */

// PARSE ARGUMENTS
const UNIT_TESTS = array('places', 'areas', 'mapstops', 'tours');
const API_TESTS = array('place_api', 'area_api', 'tour_api');

// remove file name from args
array_shift($argv);

$to_run = array();
if(empty($argv)) {
    $to_run = array_merge(UNIT_TESTS, API_TESTS);
} else {
    foreach ($argv as $arg) {
        if($arg == 'unit') {
            $to_run = array_merge($to_run, UNIT_TESTS);
        } else if($arg == 'api') {
            $to_run = array_merge($to_run, API_TESTS);
        } else if(in_array($arg, UNIT_TESTS) || in_array($arg, API_TESTS)) {
            array_push($to_run, $arg);
        } else {
            echo "ERROR: unknown test: $arg" . PHP_EOL;
            exit(-1);
        }
    }
}
$to_run = array_unique($to_run);


// PREPARE THE TEST ENVIRONMENT
// redirect all debug logging to stdout
require_once(dirname(__FILE__) . '/../logging.php');
Logging::set_output(Logging::TO_STDOUT);

// Make the test helper and load the wordpress envrionment, so that tests and
// tested functions are able to call the wordpress api (especially the wpdb
// database connection).
require_once(dirname(__FILE__) . '/test_helper.php');
$helper = new TestHelper();
require_once($helper->config->wp_load_script);

require_once(dirname(__FILE__) . '/test_case.php');

// RUN THE TESTS
$unit_test_dir = dirname(__FILE__) . '/unit_tests';
$api_test_dir = dirname(__FILE__) . '/api_tests';

// a global variable to collect all test cases in
global $shtm_test_cases;

// simply require each test file, causing it to run and add it's tests to the
// shtm_test_cases variable (this is done in each test file individually).
foreach ($to_run as $test_name) {
    echo "************* RUNNNING TEST: $test_name *************" . PHP_EOL;
    if(in_array($test_name, UNIT_TESTS)) {
        require_once("${unit_test_dir}/${test_name}_test.php");
    } elseif (in_array($test_name, API_TESTS)) {
        require_once("${api_test_dir}/${test_name}_test.php");
    } else {
        echo "No test file for: $test_name.";
    }
    echo "************* END OF TEST: $test_name *************" . PHP_EOL;
}

// output a summary report for all test cases
echo "SUMMARY: " . PHP_EOL;
foreach($shtm_test_cases as $test_case) {
    $test_case->report();
}

?>