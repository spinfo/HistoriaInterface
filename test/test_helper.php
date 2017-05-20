<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/../models/coordinate.php');
require_once(dirname(__FILE__) . '/../models/places.php');
require_once(dirname(__FILE__) . '/../models/mapstop.php');
require_once(dirname(__FILE__) . '/../models/tour.php');
require_once(dirname(__FILE__) . '/../models/tour_record.php');

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

    // sets up a url for the tour manager admin page
    public function tc_url($controller, $action, $id = null, $tour_id = null, $area_id = null) {
        $url = $this->config->wp_url . $this->config->tour_manager_prefix;
        $url .= '&shtm_c=' . $controller;
        $url .= '&shtm_a=' . $action;
        if(isset($id)) {
            $url .= '&shtm_id=' . $id;
        }
        if(isset($tour_id)) {
            $url .= '&shtm_tour_id=' . $tour_id;
        }
        if(isset($area_id)) {
            $url .= '&shtm_area_id=' . $area_id;
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

    // formats a float value to the pricision of the database
    public function coord_value_string($float) {
        return sprintf("%.6f", $float);
    }

    // returns a float value reduced to the precision used in the database
    public function coord_value($float) {
        return floatval($this->coord_value_string($float));
    }

    public function random_coordinate() {
        $coordinate = new Coordinate();
        // coordinates should have a precision of 6 decimal points
        $coordinate->lat = $this->coord_value($this->random_float(-90, 90));
        $coordinate->lon = $this->coord_value($this->random_float(-180, 180));
        return $coordinate;
    }

    public function random_julian() {
        $decimal = rand(0, 2457824) + ($this->random_float());
        // incidentally coordinates and julian dates have the same precision
        return $this->coord_value($decimal);
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

    public function make_mapstop($differ = false, $no_posts = 3) {
        $tours = Tours::instance();
        $places = Places::instance();

        $mapstop = new Mapstop();

        // TODO: who tests the tests? Make sure that there are first and last differing ids
        $mapstop->tour_id = $differ ? $tours->first_id() : $tours->last_id();
        $mapstop->place_id = $differ ? $places->first_id() : $places->last_id();

        $mapstop->name = "Mapstop Test Name " . $this->random_str();
        $mapstop->description = "Mapstop Test Desc ". $this->random_str();
        $mapstop->post_ids = array();
        for($i = 0; $i < $no_posts; $i++) {
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

    public function make_area() {
        $area = new Area();
        $area->name = 'Area Test Name ' . $this->random_str();
        $area->coordinate1 = $this->random_coordinate();
        $area->coordinate2 = $this->random_coordinate();
        return $area;
    }

    public function make_place() {
        $place = new Place();
        $place->user_id = $this->get_test_user()->ID;
        $place->area_id = Areas::instance()->first_id();
        $place->name = "Place Test name" . $this->random_str();
        $place->coordinate = $this->random_coordinate();
        return $place;
    }

    public function make_tour($coordinate_amount = 3) {
        $tour = new Tour();
        $tour->area_id = Areas::instance()->first_id();
        $tour->user_id = $this->get_test_user()->ID;
        $tour->name = 'Tour Test Name ' . $this->random_str();
        $tour->intro = 'Tour Test Intro ' . $this->random_str();
        $tour->type = array_keys(Tour::TYPES)[(rand(0,3))];
        $tour->walk_length = rand(0, 2000);
        $tour->duration = rand(0, 120);
        $tour->tag_what = 'tour-test-what-' . $this->random_str();
        $tour->tag_where = 'tour-test-where-' . $this->random_str();
        $tour->accessibility = 'test accessibility ' . $this->random_str();

        $tour->set_tag_when_start('01.02.1803 11:12');
        if(rand(0,1) == 0) {
            $tour->set_tag_when_end('01.02.1803 12:13:14');
        }

        for($i = 0; $i < $coordinate_amount; $i++) {
            $tour->coordinates[] = $this->random_coordinate();
        }

        return $tour;
    }

    // make a tour record for a tour already in the database
    public function make_tour_record($tour) {
        $tour = Tours::instance()->get($tour->id, true, true);
        Tours::instance()->set_related_objects_on($tour);

        $record = new TourRecord();
        $record->tour_id = $tour->id;
        $record->area_id = $tour->area_id;
        $record->user_id = $tour->user_id;
        $record->name = $tour->name;
        $record->is_active = true;

        // TODO: This needs another place...
        require_once(dirname(__FILE__) . '/../view_helper.php');
        require_once(dirname(__FILE__) . '/../views/view.php');
        $template_file = ViewHelper::tour_report_yaml_template();
        $view = new View($template_file, array('tour' => $tour));
        $record->content = $view->get_include_contents();

        $record->media_url =
            "http://example.com/" . $this->random_str() . ".gzip";
        $record->download_size = rand(1, PHP_INT_MAX);

        return $record;
    }

    // add n (default 3) mapstops to the database for the tour, each stop is
    // is linked to n_posts (default: 0) new wordpress posts
    public function add_mapstops_to_tour($tour, $n = 3, $n_posts = 0) {
        if(is_null($tour->mapstop_ids)) {
            $tour->mapstop_ids = array();
        }
        for($i = 0; $i < $n; $i++) {
            $mapstop = $this->make_mapstop();
            $mapstop->tour_id = $tour->id;
            // create and add posts if requested
            for($j = 0; $j < $n_posts; $j++) {
                $post_id = $this->make_wp_post();
                $mapstop->post_ids[] = $post_id;
            }
            Mapstops::instance()->insert($mapstop);
            $tour->mapstop_ids[] = $mapstop->id;
        }
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