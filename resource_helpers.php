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

    private function __construct() {}

    public static function instance() {
        static $instance = null;
        if ($instance === null) {
            $instance = new self();
        }
        return $instance;
    }

    private function params($added_params = array()) {
        return http_build_query(array_merge($_GET, $added_params));
    }

    public function default_page() {
        return $this->index_places();
    }

    public function index_places() {
        return $this->params(array(
            self::$key_controller => 'place',
            self::$key_action => 'index'
        ));
    }

    public function new_place() {
        return $this->params(array(
            self::$key_controller => 'place',
            self::$key_action => 'new'
        ));
    }

    public function create_place() {
        return $this->params(array(
            self::$key_controller => 'place',
            self::$key_action => 'create'
        ));
    }

    public function edit_place($id) {
        return $this->params(array(
            self::$key_controller => 'place',
            self::$key_action => 'edit',
            self::$key_id => $id
        ));
    }

    public function update_place($id) {
        return $this->params(array(
            self::$key_controller => 'place',
            self::$key_action => 'update',
            self::$key_id => $id
        ));
    }

    public function delete_place($id) {
        return $this->params(array(
            self::$key_controller => 'place',
            self::$key_action => 'delete',
            self::$key_id => $id
        ));
    }

    public function destroy_place($id) {
        return $this->params(array(
            self::$key_controller => 'place',
            self::$key_action => 'destroy',
            self::$key_id => $id
        ));
    }

    public function set_current_area($id = null) {
        return $this->params($this->set_current_area_params($id));
    }

    public function set_current_area_params($id = null) {
        $values = array(
            self::$key_controller => 'area',
            self::$key_action => 'set_current_area',
            // encode the current params as back url
            self::$key_back_params => urlencode(self::instance()->params())
        );
        if(!empty($id)) {
            $values[self::$key_id] = $id;
        }
        return array_merge($_GET, $values);
    }

    public function get_controller_value() {
        return $_GET[self::$key_controller];
    }

    public function get_action_value() {
        return $_GET[self::$key_action];
    }

    public function get_id_value() {
        return $_GET[self::$key_id];
    }

    public function get_back_params_value() {
        return $_GET[self::$key_back_params];
    }
}

class ViewHelper {

    public static function empty_view() {
        return dirname(__FILE__) . '/views/empty_view.php';
    }

    public static function page_wrapper_view() {
        return dirname(__FILE__) . '/views/page_wrapper.php';
    }

    public static function index_places_view() {
        return dirname(__FILE__) . '/views/place_index.php';
    }

    public static function edit_place_view() {
        return dirname(__FILE__) . '/views/place_edit.php';
    }

    public static function delete_place_view() {
        return dirname(__FILE__) . '/views/place_delete.php';
    }

}

?>