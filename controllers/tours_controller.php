<?php
namespace SmartHistoryTourManager;

require_once( dirname(__FILE__) . '/abstract_controller.php');
require_once( dirname(__FILE__) . '/../views/view.php');
require_once( dirname(__FILE__) . '/../models/areas.php');
require_once( dirname(__FILE__) . '/../models/tour.php');
require_once( dirname(__FILE__) . '/../models/tours.php');
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

    public static function create() {
        $create_params = array(
            'shtm_tour' => array('name' => "", 'area_id' => -1)
        );

        $view = null;
        // check for right params, add error message if any is missing
        $input = self::filter_params($create_params, $_POST);
        if(!empty($input)) {
            // create tour and set values
            $tour = new Tour();
            Tours::instance()->update_values($tour, $input['shtm_tour']);
            $tour->user_id = UserService::instance()->user_id();
            // persist tour
            $id = Tours::instance()->insert($tour);
            if($id != DB::BAD_ID) {
                // saved: redirect to the tour edit page
                MessageService::instance()->add_success("Tour erstellt!");
                self::redirect(RouteParams::edit_tour($id));
            } else {
                // not saved, add messages and redirect back
                MessageService::instance()->add_error("Nicht gespeichert");
                MessageService::instance()->add_model_messages($tour);
                self::redirect(RouteParams::new_tour());
            }
        }
        // something went wrong, redirect back
        self::redirect(RouteParams::new_tour());
    }


    public static function edit() {
        // attempt to get the the tour to edit
        $id = RouteParams::get_id_value();
        $tour = Tours::instance()->get($id);
        // determine the right view to use based on the result
        $view = self::determine_edit_view($tour);
        // and render the view in page
        self::wrap_in_page_view($view)->render();
    }

    public static function edit_track() {
        // attempt to get the the tour to edit
        $id = RouteParams::get_id_value();
        $tour = Tours::instance()->get($id, true, true);

        $view = self::determine_edit_track_view($tour);

        if(!empty($tour)) {
            // TODO: there should be a better way to get these
            $tour->coordinates = array();
            foreach ($tour->coordinate_ids as $id) {
                $tour->coordinates[] = Coordinates::instance()->get($id);
            }
            $tour->mapstops = array();
            foreach ($tour->mapstop_ids as $id) {
                $mapstop = Mapstops::instance()->get($id);
                if(!empty($mapstop)) {
                    $mapstop->place =
                        Places::instance()->get($mapstop->place_id);
                    $tour->mapstops[] = $mapstop;
                }
            }
        }
        self::wrap_in_page_view($view)->render();
    }

    private static function determine_edit_track_view($tour) {
        $error_view = self::filter_edit_view($tour);
        if(!is_null($error_view)) {
            return $error_view;
        }
        // the tour's area should always be accessible, but test anyway
        $area = Areas::instance()->get($tour->area_id);
        if(empty($area)) {
            $e = new \Exception("Error not present for tour: '$tour->area_id'");
            return self::create_view_with_exception($e, 500);
        }
        // success
        return new View(ViewHelper::edit_tour_track_view(), array(
            'tour' => $tour,
            'area' => $area
        ));
    }

    private static function determine_edit_view($tour) {
        $error_view = self::filter_edit_view($tour);
        if(!is_null($error_view)) {
            return $error_view;
        }
        // the tour's area should always be accessible, but test anyway
        $area = Areas::instance()->get($tour->area_id);
        if(empty($area)) {
            $e = new \Exception("Error not present for tour: '$tour->area_id'");
            return self::create_view_with_exception($e, 500);
        }
        // success
        return new View(ViewHelper::edit_tour_view(), array(
            'tour' => $tour,
            'area' => $area
        ));
    }

    private static function filter_edit_view($tour) {
        // tour not found
        if(empty($tour)) {
            $msg = "Tour '$tour->id' existiert nicht.";
            return self::create_not_found_view($msg);
        }
        // test access rights
        if(!UserService::instance()->user_may_edit_tour($tour)) {
            return self::create_access_denied_view();
        }
    }

}



?>