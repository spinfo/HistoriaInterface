<?php
namespace SmartHistoryTourManager;

require_once( dirname(__FILE__) . '/abstract_controller.php');
require_once( dirname(__FILE__) . '/../views/view.php');
require_once( dirname(__FILE__) . '/../models/areas.php');
require_once( dirname(__FILE__) . '/../route_params.php');

class ToursController extends AbstractController {


    // just renders an empty form for a tour's name and area
    public static function new() {

        $areas_list = Areas::instance()->list_simple();
        $view = new View(ViewHelper::new_tour_view(), array(
            'action_params' => RouteParams::create_tour(),
            'areas_list' => $areas_list,
        ));

        self::wrap_in_page_view($view)->render();
    }


}



?>