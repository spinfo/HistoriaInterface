<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/test_case.php');

require_once(dirname(__FILE__) . '/../models/places.php');

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

        // do the request and append url/status to the message, note results
        $this->mycurl->createCurl($url);
        $msg .= ' - ' . $url . ' (' . $this->mycurl->getHttpStatus() . ')';
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
     *
     * @return bool Whether the test passed or failed.
     */
    public function ensure_xpath($xpath, $expected_node_count, $msg) {
        if(!isset($this->result)) {
            $this->note_fail("No document to test: " . $msg);
            return false;
        }

        $nodes = $this->result->query($xpath);

        if(is_null($expected_node_count)) {
            if($nodes->length <= 0) {
                $this->note_fail($msg . " (No nodes found for: '$xpath'.)");
                return false;
            }
        } else {
            if($nodes->length != $expected_node_count) {
                $msg .= " (Expected $expected_node_count node(s),";
                $msg .= " got $nodes->length on '$xpath'.)";
                $this->note_fail($msg);
                return false;
            }
        }

        $this->note_pass($msg);
        return true;
    }

    // performs tests common for normal pages retrieved by a simple GET
    public function test_simple_page($page_type) {
        $this->ensure_xpath("//div[contains(@class, 'shtm_message')]", 0,
            "Should not show any message on $page_type.");

        $exp = "//div[@id='shtm_page_wrapper_heading' and contains(., 'Orte')]";
        $this->ensure_xpath($exp, 1,
            "Should have 'Orte' in main header on $page_type");

        $exp = "//div[@id='shtm_page_wrapper_heading' and contains(., 'Touren')]";
        $this->ensure_xpath($exp, 1,
            "Should have 'Touren' in main header on $page_type");

        $this->ensure_xpath('//h1', 1,
            "Should have exactly one top heading on $page_type");
    }

    // test for the presence of an h1-heading on the page retrieved last by the
    // given test connection
    public function test_page_heading($heading, $test_name) {
        $this->ensure_xpath("//h2[text()='${heading}']", 1,
            "Should have the right heading on ${name}.");
    }

    // test for the presence of an error message containing the specified text
    public function test_error_message($text, $test_name) {
        $this->ensure_xpath(
            "//div[contains(@class, 'shtm_message_error') and contains(., '$text')]", 1,
            "Should show error message with text '$text' on $test_name."
        );
    }

    // test for the presence of a success message containing the specified text
    public function test_success_message($text, $test_name) {
        $this->ensure_xpath(
            "//div[contains(@class, 'shtm_message_success') and contains(., '$text')]", 1,
            "Should show success message with text '$text' on $test_name."
        );
    }

    // test that string $text appears anywhere within the content area
    public function test_page_contains($text, $test_name) {
        $this->ensure_xpath(
            "//div[@id='shtm_content' and contains(., '$text')]", 1,
            "Should contain the text '$text' on $test_name."
        );
    }

    // test that there is an input field with the name and value attrs set to
    // the supplied values
    public function test_input_field($name_attr, $value_attr, $test_name) {
        $this->ensure_xpath(
            "//input[@name='$name_attr' and @value='$value_attr']", 1,
            "Should have input tag: '$name_attr' => '$value_attr' ($test_name)."
        );
    }

    // test that there is a textarea with the correct name attribute and content
    public function test_textarea($name, $value, $test_name) {
        $condition = "@name='$name' and ";
        $condition .= (empty($value)) ? 'not(text())' : "text()='$value'";
        $this->ensure_xpath("//textarea[$condition]", 1,
            "Should have textarea: '$name' => '$value' ($test_name)."
        );
    }

    // tests that there is a select box with the specified name having an
    // option with the given value and/or text
    // The option text is checked with whitespace normalized. Other tests use
    // simple xpath string equality.
    // Presence of an attribute 'selected' is only tested if the param is set
    // to true. NOTE: Else the selection attribute is ignored.
    public function test_option($select_name, $option_text,
        $option_value_attr = null, $is_selected = false, $test_name)
    {
        $opt_condition = "normalize-space() = '$option_text'";

        if(!is_null($option_value_attr)) {
            $opt_condition .= " and @value = '$option_value_attr'";
        }
        if($is_selected) {
            $opt_condition .= " and @selected";
        }

        $xpath = "//select[@name = '$select_name']/option[$opt_condition]";

        $this->ensure_xpath($xpath, 1,
            "Should have correct option tag ($test_name).");
    }

    public function test_not_found($url, $post, $name) {
        $this->test_fetch($url, $post, 404,
            "Should have status 404 ($name).");

        $this->test_error_message('existiert nicht', $name);

        $this->ensure_xpath("div//[contains(@class,'shtm_message_success')]", 0,
            "Should not have success message on 404 ($name).");
    }

    // test for the correct status and messages on attempt to access a url not
    // accessible to the user
    public function test_no_access($url, $post, $name) {
        $this->test_fetch($url, $post, 403,
            "Should have status 403 on forbidden access ($name).");

        $this->test_error_message('Berechtigung', "forbidden access ($name).");

        $this->ensure_xpath("div//[contains(@class,'shtm_message_success')]", 0,
            "Should not have success message on 403 ($name).");
    }

    // test for the correct status and the existence of an error message on
    // a bad request
    public function test_bad_request($url, $post, $name) {
        $this->test_fetch($url, $post, 400,
            "Should have status 401 on bad request ($name).");

        $this->test_error_message('', $name);

        $this->ensure_xpath("div//[contains(@class,'shtm_message_success')]", 0,
            "Should not have any success message on 401 ($name).");
    }

    // tests the presence of a coordinate tag with the specified lat/lon
    public function test_coordinate($lat, $lon, $test_name) {
        // format coordinates to database precision
        $lat = $this->helper->coord_value_string($lat);
        $lon = $this->helper->coord_value_string($lon);
        // build an xpath condition and test it
        $condition = "@class='coordinate'";
        $condition .= " and @data-lat='$lat' and @data-lon='$lon'";
        $this->ensure_xpath("//div[$condition]", null,
            "Should have a coordinate with the right lat/lon ($test_name).");
    }

    // test that the mapstop data is included on the page and that the right
    // coordinate data is present as well
    public function test_mapstop_tag($mapstop, $test_name) {
        $condition = "@data-mapstop-id='$mapstop->id'";
        $condition .= " and @data-mapstop-name='$mapstop->name'";
        $condition .= " and @data-mapstop-description='$mapstop->description'";
        $this->ensure_xpath("//div[$condition]", 1,
            "Should have the correct mapstop data ($test_name).");

        $place = Places::instance()->get($mapstop->place_id);
        $this->test_coordinate($place->coordinate->lat, $place->coordinate->lon,
            "coordinate data - $test_name");
    }

    // shorthand for testing the normal redirect params: controller, action, id
    public function test_redirect_params($controller, $action, $id = null) {
        $this->test_redirect_param('shtm_c', $controller);
        $this->test_redirect_param('shtm_a', $action);
        if(!is_null($id)) {
            $this->test_redirect_param('shtm_id', $id);
        }
    }

    /**
     * Test for a redirect by checking if a parameter appears in the effective
     * url, that the connection landed on.
     *
     * @return string|null  The parameter's value or null if no value found.
     */
    public function test_redirect_param($param, $value = null) {
        $url = $this->mycurl->getEffectiveUrl();
        if(empty($url)) {
            $this->note_fail("No last url to check param: '$param'.");
            return null;
        }
        // simply pattern match the url
        $matches = array();
        $pattern = "/${param}\=([^\=\&]+)/";
        preg_match($pattern, $url, $matches);

        // check result for presence
        $msg = "Should have param on redirect:";
        if(empty($matches || !isset($matches[1]))) {
            $this->note_fail("$msg '$param'.");
            return null;
        } else {
            $got = $matches[1];
            // no equality checking wanted, so note success
            if(is_null($value)) {
                $this->note_pass("$msg '$param'");
            } else {
                // do the requested equality check
                if($value == $got) {
                    $this->note_pass("$msg '$param' => '$value'.");
                } else {
                    $this->note_fail("$msg '$param' => '$value' (is: '$got').");
                }
            }
            return $got;
        }
    }
}

/**
 * An interface to php_curl, taken from:
 *      https://secure.php.net/manual/en/book.curl.php#90821
 *
 * Modified:
 *      - set $_url to public
 *      - set $_cookieFileLocation to public
 *      - add function mycurl->removePost()
 *      - added info about last effective url
 */
class mycurl {
     protected $_useragent = 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1';
     public $_url;
     protected $_followlocation;
     protected $_timeout;
     protected $_maxRedirects;
     public $_cookieFileLocation = './cookie.txt';
     protected $_post;
     protected $_postFields;
     protected $_referer ="http://www.google.com";

     protected $_session;
     protected $_webpage;
     protected $_includeHeader;
     protected $_noBody;
     protected $_status;
     protected $_effectiveUrl;
     protected $_binaryTransfer;
     public    $authentication = 0;
     public    $auth_name      = '';
     public    $auth_pass      = '';

     public function useAuth($use){
       $this->authentication = 0;
       if($use == true) $this->authentication = 1;
     }

     public function setName($name){
       $this->auth_name = $name;
     }
     public function setPass($pass){
       $this->auth_pass = $pass;
     }

     public function __construct($url,$followlocation = true,$timeOut = 30,$maxRedirecs = 4,$binaryTransfer = false,$includeHeader = false,$noBody = false)
     {
         $this->_url = $url;
         $this->_followlocation = $followlocation;
         $this->_timeout = $timeOut;
         $this->_maxRedirects = $maxRedirecs;
         $this->_noBody = $noBody;
         $this->_includeHeader = $includeHeader;
         $this->_binaryTransfer = $binaryTransfer;

         $this->_cookieFileLocation = dirname(__FILE__).'/cookie.txt';

     }

     public function setReferer($referer){
       $this->_referer = $referer;
     }

     public function setCookieFileLocation($path)
     {
         $this->_cookieFileLocation = $path;
     }

     public function setPost ($postFields)
     {
        $this->_post = true;
        $this->_postFields = $postFields;
     }

     public function removePost()
     {
        $this->_post = false;
        $this->_postFields = null;
     }

     public function setUserAgent($userAgent)
     {
         $this->_useragent = $userAgent;
     }

     public function createCurl($url = 'nul')
     {
        if($url != 'nul'){
          $this->_url = $url;
        }

         $s = curl_init();

         curl_setopt($s,CURLOPT_URL,$this->_url);
         curl_setopt($s,CURLOPT_HTTPHEADER,array('Expect:'));
         curl_setopt($s,CURLOPT_TIMEOUT,$this->_timeout);
         curl_setopt($s,CURLOPT_MAXREDIRS,$this->_maxRedirects);
         curl_setopt($s,CURLOPT_RETURNTRANSFER,true);
         curl_setopt($s,CURLOPT_FOLLOWLOCATION,$this->_followlocation);
         curl_setopt($s,CURLOPT_COOKIEJAR,$this->_cookieFileLocation);
         curl_setopt($s,CURLOPT_COOKIEFILE,$this->_cookieFileLocation);

         if($this->authentication == 1){
           curl_setopt($s, CURLOPT_USERPWD, $this->auth_name.':'.$this->auth_pass);
         }
         if($this->_post)
         {
             curl_setopt($s,CURLOPT_POST,true);
             curl_setopt($s,CURLOPT_POSTFIELDS,$this->_postFields);

         }

         if($this->_includeHeader)
         {
               curl_setopt($s,CURLOPT_HEADER,true);
         }

         if($this->_noBody)
         {
             curl_setopt($s,CURLOPT_NOBODY,true);
         }
         /*
         if($this->_binary)
         {
             curl_setopt($s,CURLOPT_BINARYTRANSFER,true);
         }
         */
         curl_setopt($s,CURLOPT_USERAGENT,$this->_useragent);
         curl_setopt($s,CURLOPT_REFERER,$this->_referer);

         $this->_webpage = curl_exec($s);
         $this->_status = curl_getinfo($s,CURLINFO_HTTP_CODE);
         $this->_effectiveUrl = curl_getinfo($s, CURLINFO_EFFECTIVE_URL);
         curl_close($s);

     }

   public function getHttpStatus()
   {
       return $this->_status;
   }

   public function getEffectiveUrl()
   {
        return $this->_effectiveUrl;
   }

   public function __tostring(){
      return $this->_webpage;
   }
}

?>