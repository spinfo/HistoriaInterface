<?php
namespace SmartHistoryTourManager;

/**
 * A poor man's router.
 * This basically determines "routes" defined as GET params in urls.
 */
class RouteParams {

    private static $key_controller = 'shtm_c';
    private static $key_action = 'shtm_a';
    private static $key_id = 'shtm_id';
    private static $key_back_params = 'shtm_back_params';

    // build GET parametrs for a query by adding all params to the current GET
    // parameters
    // The "route" build here is just a string of GET parameters
    private static function params_to_route($added_params = array()) {
        return http_build_query(array_merge($_GET, $added_params));
    }

    public static function default_page() {
        return self::index_tours();
    }

    private static function make_route($controller, $action, $id = null) {
        $params = array(
            self::$key_controller => $controller,
            self::$key_action => $action
        );
        if(!is_null($id)) {
            $params[self::$key_id] = $id;
        }
        return self::params_to_route($params);
    }

    // parse the supplied string of query params and return an array
    private static function params_from_str($str) {
        $result;
        parse_str($str, $result);
        return $result;
    }

    public static function index_places() {
        return self::make_route('place', 'index');
    }

    public static function new_place() {
        return self::make_route('place', 'new');
    }

    public static function create_place() {
        return self::make_route('place', 'create');
    }

    public static function edit_place($id) {
        return self::make_route('place', 'edit', $id);
    }

    public static function update_place($id) {
        return self::make_route('place', 'update', $id);
    }

    public static function delete_place($id) {
        return self::make_route('place', 'delete', $id);
    }

    public static function destroy_place($id) {
        return self::make_route('place', 'destroy', $id);
    }

    public static function index_tours() {
        return self::make_route('tour', 'index');
    }

    public static function new_tour() {
        return self::make_route('tour', 'new');
    }

    public static function create_tour() {
        return self::make_route('tour', 'create');
    }

    public static function edit_tour($id) {
        return self::make_route('tour', 'edit', $id);
    }

    public static function edit_tour_track($id) {
        return self::make_route('tour', 'edit_track', $id);
    }

    public static function edit_tour_stops($id) {
        return self::make_route('tour', 'edit_stops', $id);
    }

    public static function update_tour($id) {
        return self::make_route('tour', 'update', $id);
    }

    public static function update_tour_stops($id) {
        return self::make_route('tour', 'update_stops', $id);
    }

    public static function delete_tour($id) {
        return self::make_route('tour', 'delete', $id);
    }

    public static function set_current_area($id = null) {
        return self::params_to_route(self::set_current_area_params($id));
    }

    /**
     * Return true if the current page has all get parameters that are in the
     * given parameter string, else false.
     *
     * @param string    $params_str
     *
     * @return boolean
     */
    public static function is_current_page($params_str) {
        $params = self::params_from_str($params_str);
        $result = true;
        foreach ($params as $key => $value) {
            if(!isset($_GET[$key]) || $_GET[$key] != $value) {
                $result = false;
                break;
            }
        }
        return $result;
    }

    public static function set_current_area_params($id = null) {
        $values = array(
            self::$key_controller => 'area',
            self::$key_action => 'set_current_area',
            // encode the current params as back url
            self::$key_back_params => urlencode(self::params_to_route())
        );
        if(!empty($id)) {
            $values[self::$key_id] = $id;
        }
        return array_merge($_GET, $values);
    }

    public static function get_controller_value() {
        return $_GET[self::$key_controller];
    }

    public static function get_action_value() {
        return $_GET[self::$key_action];
    }

    public static function get_id_value() {
        return $_GET[self::$key_id];
    }

    public static function get_back_params_value() {
        return $_GET[self::$key_back_params];
    }
}

?>