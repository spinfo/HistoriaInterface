<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/abstract_controller.php');
require_once(dirname(__FILE__) . '/../route_params.php');
require_once(dirname(__FILE__) . '/../user_service.php');
require_once(dirname(__FILE__) . '/../views/view.php');
require_once(dirname(__FILE__) . '/../view_helper.php');
require_once(dirname(__FILE__) . '/../models/tour_records.php');
require_once(dirname(__FILE__) . '/../models/tours.php');

class TourRecordsController extends AbstractController {


    public static function index() {
        // anybody may view active tour records and the list functions are
        // robust, so no checking is done here
        $area_id = self::determine_area_id();
        $records = TourRecords::instance()->list_active_by_area($area_id);
        $areas = Areas::instance()->list_simple();
        $publishable_tours = Tours::instance()->list_by_area($are_id);
        $view = new View(ViewHelper::index_tour_records_view(), array(
            'records' => $records,
            'areas_list' => $areas,
            'current_area_id' => $area_id,
            'publishable_tours' => $publishable_tours
        ));
        self::wrap_in_page_view($view)->render();
    }

    public static function view() {

    }

    public static function new() {

    }

    public static function create() {

    }

    public static function deactivate() {

    }

    public static function delete() {

    }

    public static function destroy() {

    }

}

?>