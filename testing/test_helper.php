<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/../models/coordinate.php');

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
    public function tc_url($controller, $action, $id = null, $back_param_str = null) {
        $url = $this->config->wp_url . $this->config->tour_creator_prefix;
        $url .= '&shtm_c=' . $controller;
        $url .= '&shtm_a=' . $action;
        if(isset($id)) {
            $url .= '&shtm_id=' . $id;
        }
        if(isset($back_param_str)) {
            $url .= '&shtm_back_params=' . urlencode($back_param_str);
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

    public function random_coordinate() {
        $coordinate = new Coordinate();
        // coordinates should have a precision of 6 decimal points
        $coordinate->lat = floatval(sprintf("%.6f", $this->random_float(-90, 90)));
        $coordinate->lon = floatval(sprintf("%.6f", $this->random_float(-90, 90)));
        return $coordinate;
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

    public function db_has_row($table_name, $id) {
        $sql = "SELECT * FROM $table_name WHERE id = $id";
        $result = $this->mysqli->query($sql);
        if(!$result) {
            return false;
        }
        $result_arr = $result->fetch_array();
        if(empty($result_arr) || !isset($result_arr[0])) {
            return false;
        } else {
            return true;
        }
    }

    public function get_test_user() {
        return get_user_by('login', $this->config->test_user_admin_name);
    }
}

?>