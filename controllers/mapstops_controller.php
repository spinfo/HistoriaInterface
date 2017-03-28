<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/abstract_controller.php');
require_once(dirname(__FILE__) . '/../route_params.php');
require_once(dirname(__FILE__) . '/../user_service.php');
require_once(dirname(__FILE__) . '/../views/view.php');
require_once(dirname(__FILE__) . '/../models/mapstops.php');
require_once(dirname(__FILE__) . '/../models/tours.php');

class MapstopsController extends AbstractController {

    public static function new() {

    }

    public static function create() {

    }

    public static function edit() {

    }

    public static function update() {

    }

    public static function delete() {
        // attempt to get the mapstop in question
        $id = RouteParams::get_id_value();
        $mapstop = Mapstops::instance()->get($id);

        // determine if we have to show an error
        $error_view = self::filter_if_not_editable($mapstop, $id);
        if(is_null($error_view)) {
            $view = new View(ViewHelper::delete_mapstop_view(), array(
                'mapstop' => $mapstop,
            ));
        } else {
            $view = $error_view;
        }
        self::wrap_in_page_view($view)->render();
    }

    public static function destroy() {
        // attempt to get the mapstop in question
        $id = RouteParams::get_id_value();
        $mapstop = Mapstops::instance()->get($id);

        // determine if we have to show an error right away
        $error_view = self::filter_if_not_editable($mapstop, $id);
        if(is_null($error_view)) {
            // attempt to delete
            try {
                $result = Mapstops::instance()->delete($mapstop);
                if(!is_null($result)) {
                    MessageService::instance()->add_success("Stop gelöscht.");
                    $params = RouteParams::edit_tour_stops($mapstop->tour_id);
                    self::redirect($params);
                } else {
                    $msg = "Ein unbekannter Fehler ist aufgetreten.";
                    $view = self::create_internal_error_view($msg);
                }
            } catch(DB_Exception $e) {
                $view = self::create_view_with_exception($e);
            }
        } else {
            $view = $error_view;
        }
        self::wrap_in_page_view($view)->render();
    }



    private static function filter_if_not_editable($mapstop, $id) {
        // check if there is a mapstop
        if(empty($mapstop)) {
            return self::create_not_found_view("Stop existiert nicht: '$id'.");
        }
        // get the linked tour and check for edit rights
        $tour = Tours::instance()->get($mapstop->tour_id);
        if(empty($tour)) {
            $msg = "Keine Tour für Stop: '$mapstop->id'.";
            debug_log($msg);
            return self::create_internal_error_view($msg, 500);
        } else {
            if(!UserService::instance()->user_may_edit_tour($tour)) {
                return self::create_access_denied_view();
            }
        }
        // nothing to complain about
        return null;
    }

}

?>