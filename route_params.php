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
    private static function params($added_params = array()) {
        return http_build_query(array_merge($_GET, $added_params));
    }

    public static function default_page() {
        return self::index_places();
    }

    public static function index_places() {
        return self::params(array(
            self::$key_controller => 'place',
            self::$key_action => 'index'
        ));
    }

    public static function new_place() {
        return self::params(array(
            self::$key_controller => 'place',
            self::$key_action => 'new'
        ));
    }

    public static function create_place() {
        return self::params(array(
            self::$key_controller => 'place',
            self::$key_action => 'create'
        ));
    }

    public static function edit_place($id) {
        return self::params(array(
            self::$key_controller => 'place',
            self::$key_action => 'edit',
            self::$key_id => $id
        ));
    }

    public static function update_place($id) {
        return self::params(array(
            self::$key_controller => 'place',
            self::$key_action => 'update',
            self::$key_id => $id
        ));
    }

    public static function delete_place($id) {
        return self::params(array(
            self::$key_controller => 'place',
            self::$key_action => 'delete',
            self::$key_id => $id
        ));
    }

    public static function destroy_place($id) {
        return self::params(array(
            self::$key_controller => 'place',
            self::$key_action => 'destroy',
            self::$key_id => $id
        ));
    }

    public static function new_tour() {
        return self::params(array(
            self::$key_controller => 'tour',
            self::$key_action => 'new'
        ));
    }

    public static function create_tour() {
        return self::params(array(
            self::$key_controller => 'tour',
            self::$key_action => 'create'
        ));
    }

    public static function set_current_area($id = null) {
        return self::params(self::set_current_area_params($id));
    }

    public static function set_current_area_params($id = null) {
        $values = array(
            self::$key_controller => 'area',
            self::$key_action => 'set_current_area',
            // encode the current params as back url
            self::$key_back_params => urlencode(self::params())
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