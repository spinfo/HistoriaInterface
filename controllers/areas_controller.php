<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/abstract_controller.php');
require_once(dirname(__FILE__) . '/../resource_helpers.php');
require_once(dirname(__FILE__) . '/../user_service.php');
require_once(dirname(__FILE__) . '/../views/view.php');

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

    public static function set_current_area() {
        $user_service = UserService::instance();
        $route_params = RouteParams::instance();
        $id = intval($route_params->get_id_value());

        $view = null;
        if(!$user_service->is_logged_in()) {
            $view = self::create_access_denied_view();
        } else {
            try {
                $user_service->set_current_area_id($id);
                $back_page = $route_params->get_back_params_value();
                self::redirect(urldecode($back_page), 302);
            } catch (UserServiceException $e) {
                $view = self::create_view_with_exception($e, 400);
            }
        }
        self::wrap_in_page_view($view)->render();
    }

}

?>