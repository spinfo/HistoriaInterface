<?php
namespace SmartHistoryTourManager;

/**
 * A poor man's router.
 * This basically determines "routes" defined as GET params in urls.
 */
class RouteParams {

    // a list of parameter keys order by names
    const KEYS = [
        'controller' => 'shtm_c',
        'action' => 'shtm_a',
        'id' => 'shtm_id',
        'area_id' => 'shtm_area_id',
        'tour_id' => 'shtm_tour_id',
        'back_params' => 'shtm_back_params'
    ];

    // build GET parametrs for a query by adding all params to the current GET
    // parameters
    // The "route" build here is just a string of GET parameters
    private static function params_to_route($added_params = array(), $remove_others = true) {
        $get = $_GET;
        if($remove_others) {
            foreach (self::KEYS as $name => $param_key) {
                unset($get[$param_key]);
            }
        }
        return http_build_query(array_merge($get, $added_params));
    }

    public static function default_page() {
        return self::index_tours();
    }

    private static function make_route(
        $controller,
        $action,
        $id = null,
        $tour_id = null,
        $area_id = null)
    {
        $params = array(
            self::KEYS['controller'] => $controller,
            self::KEYS['action'] => $action
        );
        if(!is_null($id)) {
            $params[self::KEYS['id']] = $id;
        }
        if(!is_null($tour_id)) {
            $params[self::KEYS['tour_id']] = $tour_id;
        }
        if(!is_null($area_id)) {
            $params[self::KEYS['area_id']] = $area_id;
        }
        return self::params_to_route($params);
    }

    // parse the supplied string of query params and return an array
    private static function params_from_str($str) {
        $result;
        parse_str($str, $result);
        return $result;
    }

    public static function index_areas() {
        return self::make_route('area', 'index');
    }

    public static function new_area() {
        return self::make_route('area', 'new');
    }

    public static function create_area() {
        return self::make_route('area', 'create');
    }

    public static function edit_area($id) {
        return self::make_route('area', 'edit', $id);
    }

    public static function update_area($id) {
        return self::make_route('area', 'update', $id);
    }

    public static function delete_area($id) {
        return self::make_route('area', 'delete', $id);
    }

    public static function destroy_area($id) {
        return self::make_route('area', 'destroy', $id);
    }

    // TODO: Remove default setting once area_id is fully supported
    public static function index_places($area_id = -1) {
        return self::make_route('place', 'index', null, null, $area_id);
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

    // TODO: Remove default setting once area_id is fully supported
    public static function index_tours($area_id = -1) {
        return self::make_route('tour', 'index', null, null, $area_id);
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

    public static function new_mapstop($tour_id) {
        return self::make_route('mapstop', 'new', null, $tour_id);
    }

    public static function create_mapstop($tour_id) {
        return self::make_route('mapstop', 'create', null, $tour_id);
    }

    public static function edit_mapstop($id) {
        return self::make_route('mapstop', 'edit', $id);
    }

    public static function update_mapstop($id) {
        return self::make_route('mapstop', 'update', $id);
    }

    public static function delete_mapstop($id) {
        return self::make_route('mapstop', 'delete', $id);
    }

    public static function destroy_mapstop($id) {
        return self::make_route('mapstop', 'destroy', $id);
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
            self::KEYS['controller'] => 'area',
            self::KEYS['action'] => 'set_current_area',
            // encode the current params as back url
            self::KEYS['back_params'] => urlencode(self::params_to_route())
        );
        if(!empty($id)) {
            $values[self::KEYS['id']] = $id;
        }
        return array_merge($_GET, $values);
    }

    public static function get_controller_value() {
        return $_GET[self::KEYS['controller']];
    }

    public static function get_action_value() {
        return $_GET[self::KEYS['action']];
    }

    public static function get_id_value() {
        return intval($_GET[self::KEYS['id']]);
    }

    public static function get_tour_id_value() {
        return intval($_GET[self::KEYS['tour_id']]);
    }

    public static function get_back_params_value() {
        return $_GET[self::KEYS['back_params']];
    }
}

?>