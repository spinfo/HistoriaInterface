<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/abstract_controller.php');
require_once(dirname(__FILE__) . '/../route_params.php');
require_once(dirname(__FILE__) . '/../user_service.php');
require_once(dirname(__FILE__) . '/../views/view.php');
require_once(dirname(__FILE__) . '/../models/mapstops.php');
require_once(dirname(__FILE__) . '/../models/tours.php');

class MapstopsController extends AbstractController {

    const MAPSTOP_PARAMS = array(
        'shtm_mapstop' => array(
            'place_id' => 0,
            'name' => '',
            'description' => ''
        )
    );

    public static function new() {

    }

    public static function create() {

    }

    public static function edit() {
        // attempt to get the mapstop in question
        $id = RouteParams::get_id_value();
        $mapstop = Mapstops::instance()->get($id);

        // determine if the mapstop may be edited
        $error_view = self::filter_if_not_editable($mapstop, $id);
        if(is_null($error_view)) {
            $available_posts = UserService::instance()->get_available_posts();
            $posts = UserService::instance()->get_posts($mapstop->post_ids);
            $tour = Tours::instance()->get($mapstop->tour_id, true);
            $places = Mapstops::instance()->get_possible_places($mapstop);
            $view = new View(ViewHelper::edit_mapstop_view(), array(
                'mapstop' => $mapstop,
                'posts' => $posts,
                'available_posts' => $available_posts,
                'places' => $places,
            ));
        } else {
            $view = $error_view;
        }
        self::wrap_in_page_view($view)->render();
    }

    public static function update() {
        // attempt to get the mapstop in question
        $id = RouteParams::get_id_value();
        $mapstop = Mapstops::instance()->get($id);

        // determine if the mapstop may be edited
        $error_view = self::filter_if_not_editable($mapstop, $id);
        if(is_null($error_view)) {
            // get the params to update
            $params = self::get_mapstops_params();
            if(!is_null($params)) {
                // re-add the tour, as that should not be user-changable
                $params['tour_id'] = $mapstop->tour_id;
                Mapstops::instance()->update_values($mapstop, $params);
                $result = Mapstops::instance()->update($mapstop);
                if($result) {
                    MessageService::instance()->add_success('Gespeichert');
                    self::redirect(RouteParams::edit_mapstop($id));
                } else {
                    MessageService::instance()->add_model_messages($mapstop);
                    $view = self::create_bad_request_view("Nicht gespeichert");
                }
            } else {
                $view = self::create_bad_request_view("Bad input for mapstop.");
            }
        } else {
            $view = $error_view;
        }
        self::wrap_in_page_view($view)->render();
    }

    public static function delete() {
        // attempt to get the mapstop in question
        $id = RouteParams::get_id_value();
        $mapstop = Mapstops::instance()->get($id);

        // determine if the mapstop may be edited
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

        // determine if the mapstop may be edited
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


    /**
     * @return View|null    A view for the error encountered or null if every-
     *                      thing is okay.
     */
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

    /**
     * @return array|null   The params for a mapstop or null on error.
     */
    private static function get_mapstops_params() {
        // first get the normal POST params
        $params = self::filter_params(self::MAPSTOP_PARAMS, $_POST);
        // if the normal params are null, something went wrong, return null
        if(is_null($params)) {
            debug_log("Could not read mapstop params from POST data.");
            return null;
        }
        // we only need the array below the mapstop key
        $params = $params['shtm_mapstop'];
        // add the post_ids if that key is set and set to an array
        if(is_array($_POST['shtm_mapstop']['post_ids'])) {
            $ids = array_map('intval', $_POST['shtm_mapstop']['post_ids']);
            $ids = array_filter($ids, function($id) { return ($id > 0); });
            $params['post_ids'] = array_unique($ids);
        }
        return $params;
    }

}

?>