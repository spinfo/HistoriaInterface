<?php
namespace SmartHistoryTourManager;

/*
Plugin Name: Smart History Tour Manager
Plugin URI: http://www.example.com/no-uri-yet
Description: A plugin to create, manage and host tours for the SmartHistory App
Version: 0.1
Author: David Neugebauer
Author URI: http://www.example.com
License: All rights reserved. This is not free software (for now).
*/


include_once(dirname(__FILE__) . '/resource_helpers.php');
include_once(dirname(__FILE__) . '/user_service.php');
include_once(dirname(__FILE__) . '/message_service.php');
include_once(dirname(__FILE__) . '/models/areas.php');
include_once(dirname(__FILE__) . '/models/coordinates.php');
include_once(dirname(__FILE__) . '/models/places.php');
include_once(dirname(__FILE__) . '/models/mapstops.php');
include_once(dirname(__FILE__) . '/models/tours.php');
include_once(dirname(__FILE__) . '/db.php');

/**
 * INSTALLATION/ACTIVATION
 */
// database version number, not used at the moment, but might be helpful on
// later upgrades. Increment on any db change
global $shtm_db_version;
$shtm_db_version = '0.1';

function shtm_install() {
    // get the table name prefix for tables as well as the default charset
    // from wp, add our name to the prefix
    global $wpdb;
    $table_prefix = $wpdb->prefix . "shtm_";
    $charset_collate = $wpdb->get_charset_collate();

    // CREATE TABLES
    // sql syntax for use with dbDelta() has specific rules, cf.:
    //      https://codex.wordpress.org/Creating_Tables_with_Plugins


    // sql for the coordinates table
    // lat/lon data type chosen according to:
    //      http://stackoverflow.com/a/25120203/1879728
    //      http://mysql.rjweb.org/doc.php/latlng
    $table_name = Coordinates::instance()->table;
    $coordinates_sql = "CREATE TABLE $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        lat decimal(8,6) NOT NULL,
        lon decimal(9,6) NOT NULL,
        created_at timestamp DEFAULT now(),
        updated_at timestamp DEFAULT now() ON UPDATE now(),
        PRIMARY KEY  (id)
    ) $charset_collate;";

    // sql for the areas table
    $table_name = Areas::instance()->table;
    $areas_sql = "CREATE TABLE $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        coordinate1_id bigint(20) NOT NULL,
        coordinate2_id bigint(20) NOT NULL,
        name text NOT NULL,
        created_at timestamp DEFAULT now(),
        updated_at timestamp DEFAULT now() ON UPDATE now(),
        PRIMARY KEY  (id)
    ) $charset_collate;";

    // sql for the places table
    $table_name = Places::instance()->table;
    $places_sql = "CREATE TABLE $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        coordinate_id bigint(20) NOT NULL,
        area_id bigint(20) NOT NULL,
        name text NOT NULL,
        created_at timestamp DEFAULT now(),
        updated_at timestamp DEFAULT now() ON UPDATE now(),
        PRIMARY KEY  (id),
        KEY shtm_places_user_id (user_id),
        KEY shtm_places_area_id (area_id)
    ) $charset_collate;";

    // sql for the tours table (stub)
    $table_name = Tours::instance()->table;
    $tours_sql = "CREATE TABLE $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        name text NOT NULL,
        created_at timestamp DEFAULT now(),
        updated_at timestamp DEFAULT now() ON UPDATE now(),
        PRIMARY KEY  (id)
    ) $charset_collate;";

    // sql for the mapstop table
    $table_name = Mapstops::instance()->table;
    $mapstops_sql = "CREATE TABLE $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        tour_id bigint(20) NOT NULL,
        place_id bigint(20) NOT NULL,
        name text NOT NULL,
        description text NOT NULL,
        created_at timestamp DEFAULT now(),
        updated_at timestamp DEFAULT now() ON UPDATE now(),
        PRIMARY KEY  (id),
        KEY shtm_mapstops_tour_id (tour_id),
        KEY shtm_mapstops_place_id (place_id)
    ) $charset_collate;";

    // sql for joining posts on mapstops
    $table_name = Mapstops::instance()->join_posts_table;
    $mapstops_to_posts_sql = "CREATE TABLE $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        mapstop_id bigint(20) NOT NULL,
        post_id bigint(20) NOT NULL,
        created_at timestamp DEFAULT now(),
        updated_at timestamp DEFAULT now() ON UPDATE now(),
        PRIMARY KEY  (id),
        KEY shtm_mapstops_to_posts (mapstop_id, post_id)
    ) $charset_collate;";

    // collect queries
    $queries = array(
        $coordinates_sql, $areas_sql, $places_sql, $tours_sql,
        $mapstops_sql, $mapstops_to_posts_sql
    );

    // do the table update
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    $messages = dbDelta($queries);

    // use syslog here, because an error might have occured, but we cannot
    // really error_log(), because we do not know for sure as dbDelta() only
    // returns messages
    syslog(LOG_DEBUG, "SHTM on wordpress: tables created on activation: "
        . var_export($messages, true));

    // fix db version number to current version in options
    add_option('shtm_db_version', $shtm_db_version);
}

// This is meant for testing
// TODO: remove after testing
function shtm_create_test_data() {

    $user_service = UserService::instance();

    $values = array(
        "lat" => 51.188801,
        "lon" => 6.794488
    );
    $coord1_id = DB::insert(Coordinates::instance()->table, $values);

    $values = array(
        "lat" => 51.188801,
        "lon" => 6.794488
    );
    $coord2_id = DB::insert(Coordinates::instance()->table, $values);

    $values = array(
        "coordinate1_id" => $coord1_id,
        "coordinate2_id" => $coord2_id,
        "name" => "Düsseldorf"
    );
    $area_id = DB::insert(Areas::instance()->table, $values);

    $values = array(
        "user_id" => $user_service->user_id(),
        "area_id" => $area_id,
        "name" => "HHU-Campus 3",
        "lat" => 51.188801,
        "lon" => 6.794488
    );
    $place = Places::instance()->create($values);
    Places::instance()->save($place);

    // use id values of posts that come with default wordpress install
    // (use only thos that have a title)
    $post_ids = array(1,2,3,4,5,6,7,8,10,11,12,19,20,21);

    // Make some dummy tours to work with
    for($i = 0; $i < 3; $i++) {
        $values = array('name' => "Tour$i");
        $tour_id = DB::insert(Tours::instance()->table, $values);

        // Make two mapstops for each tour
        for($j = 0; $j < 2; $j++) {
            $values = array(
                'tour_id' => $tour_id,
                'place_id' => $place->id,
                'name' => "Mapstop no. $j (t: $tour_id)",
                'description' => "Desc for mapstop no. $j (t: $tour_id)"
            );
            $mapstop_id = DB::insert(Mapstops::instance()->table, $values);

            // link each mapstop to two tours
            for($k = 0; $k < 2; $k++) {
                $values = array(
                    'mapstop_id' => $mapstop_id,
                    'post_id' => array_shift($post_ids)
                );
                DB::insert(Mapstops::instance()->join_posts_table, $values);
            }
        }
    }


}

register_activation_hook(__FILE__, 'SmartHistoryTourManager\shtm_install');
register_activation_hook(__FILE__, 'SmartHistoryTourManager\shtm_create_test_data');

// TODO: Write a hook after post delete to disjoin from mapstop

/**
 * TOUR CREATOR
 * The Tour Creator is that part of the Smart History Manager used to create
 * tours on the site (but not to publish them).
 *
 * The Tour Creator is available for the role Contributor. By default
 * contributors have access to their own tours only.
 */
function shtm_setup_tour_creator() {
    // title used in the menu and in the browser tab
    $title_menu = 'SmartHistory Tours';
    $title_tab = 'SmartHistory Tours';

    // Capability available to Contributor role and above, but not to Subscriber
    $capability = 'edit_posts';

    // Unique identifier for this menu
    $menu_slug = 'shtm_tour_creator';

    // the callable function used to render the tour creator
    $render_func = 'SmartHistoryTourManager\shtm_render_tour_creator';

    // we do not set an icon or menu position for now
    $icon = "";
    $menu_pos = null;

    add_menu_page($title_menu, $title_tab, $capability, $menu_slug,
        $render_func, $icon, $menu_pos);
}

function shtm_render_tour_creator() {

    $route_params = RouteParams::instance();

    $controller = $route_params->get_controller_value();
    $action = $route_params->get_action_value();

    switch($controller) {
        case 'place':
            require_once( dirname(__FILE__) . '/controllers/places_controller.php');
            switch ($action) {
                case 'index':
                    PlacesController::index();
                    break;
                case 'new':
                    PlacesController::new();
                    break;
                case 'create':
                    PlacesController::create();
                    break;
                case 'edit':
                    PlacesController::edit();
                    break;
                case 'update':
                    PlacesController::update();
                    break;
                case 'delete':
                    PlacesController::delete();
                    break;
                case 'destroy':
                    PlacesController::destroy();
                    break;
                default:
                    PlacesController::index();
                    break;
            }
            break;
        case 'area':
            require_once( dirname(__FILE__) . '/controllers/areas_controller.php');
            switch($action) {
                case 'set_current_area':
                    AreasController::set_current_area();
                    break;
                default:
                    error_log("Missing action for area controller.");
                    break;
            }
            break;
        default:
            require_once( dirname(__FILE__) . '/controllers/places_controller.php');
            PlacesController::index();
            break;
    }

    // end the output buffering that was started on 'init' for this page
    //  - cf. the hook to do_output_buffer()
    ob_end_flush();
}
add_action('admin_menu', 'SmartHistoryTourManager\shtm_setup_tour_creator');

// allow redirection and setting status headers, even if wordpress wants to
// start sending output to the browser.
// NOTE: This will hinder performance, but we need the redirection to work.
// NOTE: The corresponding ob_end_flush() is found at the end of
//          shtm_render_tour_creator()
function do_output_buffer() {
    // restrict output buffering to our plugins admin page
    if(!empty($_GET['page']) && ($_GET['page'] == 'shtm_tour_creator')) {
        ob_start();
    }
}
add_action('init', 'SmartHistoryTourManager\do_output_buffer');


// use session data to pass messages to the user on redirection
function check_session_messages() {
    if(!session_id()) {
        session_start();
    }

    if(isset($_SESSION['shtm_messages'])) {
        $message_service = MessageService::instance();
        $message_service->messages = $_SESSION['shtm_messages'];
        unset($_SESSION['shtm_messages']);
    }
}
add_action('init', 'SmartHistoryTourManager\check_session_messages');

?>