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
        // the helper might create wordpress posts that are then saved here
        $this->posts_created = array();
    }

    public function __destruct() {
        // delete all the posts that we might have created for mapstops
        $this->delete_wp_posts_created();
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
        $coordinate->lon = floatval(sprintf("%.6f", $this->random_float(-180, 180)));
        return $coordinate;
    }

    public function random_julian() {
        return floatval(sprintf("%.6f", $this->random_float(0, 2457824)));
    }

    public function db_highest_id($table_name) {
        $sql = "SELECT id FROM $table_name ORDER BY id DESC LIMIT 0,1";
        $result = $this->mysqli->query($sql);
        if(!$result) {
            echo "ERROR: Failed db query: $sql" . PHP_EOL;
            exit(1);
        }
        $result_arr = $result->fetch_array();
        if(empty($result_arr) || !isset($result_arr[0])) {
            echo "ERROR: Retrieved empty result: $sql" . PHP_EOL;
            exit(1);
        }
        return intval($result_arr[0]);
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

    public function make_mapstop($differ = false) {
        $tours = Tours::instance();
        $places = Places::instance();

        $mapstop = new Mapstop();

        // TODO: who tests the tests? Make sure that there are first and last differing ids
        $mapstop->tour_id = $differ ? $tours->first_id() : $tours->last_id();
        $mapstop->place_id = $differ ? $places->first_id() : $places->last_id();

        $mapstop->name = "Mapstop Test Name " . $this->random_str();
        $mapstop->description = "Mapstop Test Desc ". $this->random_str();
        $mapstop->post_ids = array();
        for($i = 0; $i < 3; $i++) {
            $mapstop->post_ids[] = $this->make_wp_post();
        }
        return $mapstop;
    }

    // create a minimal wordpress post that can be linked to a mapstop, return
    // id or panic and fail
    public function make_wp_post() {
        $id = wp_insert_post(array(
            'post_title' => 'Post for mapstop ' . $this->random_str(),
            'post_status' => 'draft',
            'post_author' => $this->get_test_user()->ID
        ));
        if($id == 0 || $id instanceof WP_Error) {
            debug_log("Could not insert new post for mapstop test;");
            exit(1);
        }
        $this->posts_created[] = $id;
        return $id;
    }

    public function make_place() {
        $place = new Place();
        $place->user_id = $this->get_test_user()->ID;
        $place->area_id = Areas::instance()->first_id();
        $place->name = "Place Test name" . $this->random_str();
        $place->coordinate = $this->random_coordinate();
        return $place;
    }

    public function make_tour() {
        $tour = new Tour();
        $tour->area_id = Areas::instance()->first_id();
        $tour->user_id = $this->get_test_user()->ID;
        $tour->name = 'Tour Test Name ' . $this->random_str();
        $tour->intro = 'Tour Test Intro ' . $this->random_str();
        $tour->type = (rand(0,1) == 0) ? 'round-tour' : 'tour';
        $tour->walk_length = rand(0, 2000);
        $tour->duration = rand(0, 120);
        $tour->tag_what = 'tour-test-what-' . $this->random_str();
        $tour->tag_where = 'tour-test-where-' . $this->random_str();
        $tour->tag_when_start = $this->random_julian();
        $tour->tag_when_end = (rand(0,1) == 0) ? null : ($tour->tag_when_start + 30);
        $tour->accessibility = 'test accessibility ' . $this->random_str();

        for($i = 0; $i < 3; $i++) {
            $tour->coordinates[] = $this->random_coordinate();
        }

        return $tour;
    }

    // remove all the worpress posts created by this helper
    public function delete_wp_posts_created() {
        $id = array_pop($this->posts_created);
        while(!is_null($id)) {
            wp_delete_post($id, true);
            $id = array_pop($this->posts_created);
        }
    }
}

?>