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
        $error_view = self::filter_if_user_may_not_publish();
        if(is_null($error_view)) {
            $id = RouteParams::get_id_value();
            $record = TourRecords::instance()->get($id);
            if(!empty($record)) {
                $result = TourRecords::instance()->set_inactive($record);
                if($result) {
                    MessageService::instance()->add_success("Tour deaktiviert");
                    $are_id = self::determine_area_id();
                    self::redirect(RouteParams::index_tour_records($area_id));
                } else {
                    $msg = "Tour konnte nicht deaktiviert werden.";
                    $view = self::create_internal_error_view($msg);
                }
            } else {
                $msg = "No tour record with id '$id'";
                $view = self::create_not_found_view($msg);
            }
        } else {
            $view = $error_view;
        }
        self::wrap_in_page_view($view)->render();
    }

    public static function delete() {

    }

    public static function destroy() {

    }

    /**
     * @return View|null    An error view if the user may not publish tours
     *                      else null
     */
    private static function filter_if_user_may_not_publish() {
        if(!UserService::instance()->user_may_publish_tours()) {
            return self::create_access_denied_view();
        }
        return null;
    }

}

?>