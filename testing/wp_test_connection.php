<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/test_case.php');

/**
 * A class to use for connections to a wordpress page. Handles the login and
 * basic testing.
 */
class WPTestConnection extends TestCase {

    private static $login_path = '/wp-login.php';

    private $user;

    private $pass;

    private $wp_url;

    private $cookie_dir;

    private $mycurl;

    // A DomXPath representation of the last result
    private $result;

    public function __construct($test_name, $user, $pass, $wp_url, $cookie_dir = '/tmp') {
        $this->name = $test_name;
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

        parent::__construct();
    }

    public function __destruct() {
        $this->invalidate_login();
    }

    // this overwrites the parent log function to log url and http status
    protected function log($msg = '') {
        echo $msg . ' - ' . $this->mycurl->_url . ' (' . $this->mycurl->getHttpStatus() . ')' . PHP_EOL;
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

    // destroy the cookie file to invalidate the session
    public function invalidate_login() {
        if(file_exists($this->cookie_file)) {
            unlink($this->cookie_file);
        }
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

?>