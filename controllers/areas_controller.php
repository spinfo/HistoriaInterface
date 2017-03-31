<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/abstract_controller.php');
require_once(dirname(__FILE__) . '/../route_params.php');
require_once(dirname(__FILE__) . '/../user_service.php');
require_once(dirname(__FILE__) . '/../views/view.php');
require_once(dirname(__FILE__) . '/../view_helper.php');
require_once(dirname(__FILE__) . '/../models/areas.php');
require_once(dirname(__FILE__) . '/../models/tours.php');

class AreasController extends AbstractController {

    // defines accepted parameters for a place
    const AREA_PARAMS = array(
        'shtm_area' => array(
            'name' => "",
            'c1_lat' => 0.0,
            'c1_lon' => 0.0,
            'c2_lat' => 0.0,
            'c2_lon' => 0.0,
        )
    );


    public static function index() {
        $user_service = UserService::instance();
        $areas = Areas::instance()->list_simple();
        $tour_counts = array();
        foreach($areas as $area) {
            $count = Tours::instance()->count(array('area_id' => $area->id));
            $tour_counts[$area->id] = $count;
        }

        $view = new View(ViewHelper::index_areas_view(), array(
            'user_service' => $user_service,
            'areas' => $areas,
            'tour_counts' => $tour_counts
        ));
        self::wrap_in_page_view($view)->render();
    }

    public static function new() {

    }

    public static function create() {

    }

    public static function edit() {

    }

    public static function update() {

    }

    public static function delete() {

    }

    public static function destroy() {

    }

    // set the current area in the wp options and redirect to the previous page
    public static function set_current_area() {
        $user_service = UserService::instance();
        $id = intval(RouteParams::get_id_value());

        $view = null;
        if(!$user_service->is_logged_in()) {
            $view = self::create_access_denied_view();
        } else {
            try {
                $user_service->set_current_area_id($id);
                $back_page = RouteParams::get_back_params_value();
                self::redirect(urldecode($back_page), 302);
            } catch (UserServiceException $e) {
                $view = self::create_view_with_exception($e, 400);
            }
        }
        self::wrap_in_page_view($view)->render();
    }

}

?>