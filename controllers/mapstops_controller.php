<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/abstract_controller.php');
require_once(dirname(__FILE__) . '/../route_params.php');
require_once(dirname(__FILE__) . '/../user_service.php');
require_once(dirname(__FILE__) . '/../views/view.php');
require_once(dirname(__FILE__) . '/../models/mapstops.php');
require_once(dirname(__FILE__) . '/../models/mapstop.php');
require_once(dirname(__FILE__) . '/../models/tours.php');
require_once(dirname(__FILE__) . '/../models/scenes.php');

class MapstopsController extends AbstractController {

    const MAPSTOP_PARAMS = array(
        'shtm_mapstop' => array(
            'place_id' => 0,
            'name' => '',
            'description' => '',
            'type' => ''
        )
    );

    public static function new() {
        // attempt to get the tour the mapstop is meant for
        $tour_id = RouteParams::get_tour_id_value();
        $tour = Tours::instance()->get($tour_id);

        $scene_id = RouteParams::get_sene_id_value();
        $scene = null;
        if ($scene_id > 0) {
            $scene = Scenes::instance()->get($scene_id);
        }

        $error_view = self::filter_if_tour_not_editable($tour);
        if(empty($error_view)) {
            $mapstop = new Mapstop();
            $mapstop->tour_id = $tour->id;

            if ($tour->is_indoor()) {
                $places = Places::instance()->list_by_area($tour->area_id);
            } else {
                $places = Mapstops::instance()->get_possible_places($mapstop);
            }

            // if there are no places to link to the tour, write a message and
            // redirect to place->new
            // TODO: Ensure that this goes to the right area, see notes
            if(empty($places)) {
                $msg = "Bitte legen Sie zuerst einen Ort für das Gebiet an.";
                MessageService::instance()->add_info($msg);
                self::redirect(RouteParams::new_place());
            }

            $view = new View(ViewHelper::new_mapstop_view(), array(
                'mapstop' => $mapstop,
                'places' => $places,
                'scene' => $scene
            ));
        } else {
            $view = $error_view;
        }
        self::wrap_in_page_view($view)->render();
    }

    public static function create() {
        // attempt to get the tour the mapstop is meant for
        $tour_id = RouteParams::get_tour_id_value();
        $tour = Tours::instance()->get($tour_id);

        $scene_id = RouteParams::get_sene_id_value();
        $scene = null;
        if ($scene_id > 0) {
            $scene = Scenes::instance()->get($scene_id);
        }

        $error_view = self::filter_if_tour_not_editable($tour);
        if(empty($error_view)) {
            // get the params to update
            $params = self::get_mapstop_params();

            if(!empty($params)) {
                $mapstop = new Mapstop();
                Mapstops::instance()->update_values($mapstop, $params);
                $mapstop->tour_id = $tour->id;
                $view = self::handle_insert_or_update($mapstop, $tour, $scene);
            } else {
                $view = self::create_bad_request_view("Bad input for mapstop.");
            }
        } else {
            $view = $error_view;
        }
        debug_log("view: " . var_export($view, true));
        self::wrap_in_page_view($view)->render();
    }

    public static function edit() {
        // attempt to get the mapstop in question
        $id = RouteParams::get_id_value();
        $mapstop = Mapstops::instance()->get($id);
        $tour = Tours::instance()->get($mapstop->tour_id);

        $scene_id = RouteParams::get_sene_id_value();
        $scene = null;
        if ($scene_id > 0) {
            $scene = Scenes::instance()->get($scene_id);
            $mapstop = Mapstops::instance()->fetch_type_for_mapstop($mapstop);
        }

        // determine if the mapstop may be edited
        $error_view = self::filter_if_not_editable($mapstop, $id);
        if(is_null($error_view)) {
            $available_posts = UserService::instance()->get_available_posts();
            $posts = UserService::instance()->get_posts($mapstop->post_ids);
            if ($tour->is_indoor()) {
                $places = Places::instance()->list_by_area($tour->area_id);
            } else {
                $places = Mapstops::instance()->get_possible_places($mapstop);
            }
            $view = new View(ViewHelper::edit_mapstop_view(), array(
                'mapstop' => $mapstop,
                'posts' => $posts,
                'available_posts' => $available_posts,
                'places' => $places,
                'scene' => $scene
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

        $scene_id = RouteParams::get_sene_id_value();
        $scene = null;
        if ($scene_id > 0) {
            $scene = Scenes::instance()->get($scene_id);
        }

        // determine if the mapstop may be edited
        $error_view = self::filter_if_not_editable($mapstop, $id);
        if(is_null($error_view)) {
            // get the params to update
            $params = self::get_mapstop_params();
            if(!is_null($params)) {
                // re-add the tour, as that should not be user-changable
                $params['tour_id'] = $mapstop->tour_id;
                Mapstops::instance()->update_values($mapstop, $params);
                $tour = Tours::instance()->get($mapstop->tour_id);
                $view = self::handle_insert_or_update($mapstop, $tour, $scene);
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

        $scene_id = RouteParams::get_sene_id_value();
        $scene = null;
        if ($scene_id > 0) {
            $scene = Scenes::instance()->get($scene_id);
        }

        // determine if the mapstop may be edited
        $error_view = self::filter_if_not_editable($mapstop, $id);
        if(is_null($error_view)) {
            $view = new View(ViewHelper::delete_mapstop_view(), array(
                'mapstop' => $mapstop,
                'scene' => $scene
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

        $scene_id = RouteParams::get_sene_id_value();
        $scene = null;
        if ($scene_id > 0) {
            $scene = Scenes::instance()->get($scene_id);
        }

        // determine if the mapstop may be edited
        $error_view = self::filter_if_not_editable($mapstop, $id);
        if(is_null($error_view)) {
            // attempt to delete
            try {
                if ($scene) {
                    Coordinates::instance()->delete_by_mapstop_id($mapstop->id);
                }

                $result = Mapstops::instance()->delete($mapstop);
                if(!is_null($result)) {
                    MessageService::instance()->add_success("Stop gelöscht.");
                    if ($scene) {
                        $params = RouteParams::new_scene_stop($scene->id);
                    } else {
                        $params = RouteParams::edit_tour_stops($mapstop->tour_id);
                    }
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
     * @return View     A view for the error encountered on error. Should not
     *                  return at all on success (redirects to edit route).
     */
    private static function handle_insert_or_update($mapstop, $tour, $scene = null) {
        // check that the place id is correct
        $place = Places::instance()->get($mapstop->place_id);
        if($place->area_id != $tour->area_id) {
            $msg = "Ort liegt nicht im Tour-Gebiet.";
            return self::create_bad_request_view($msg);
        }
        // do the save
        $result = Mapstops::instance()->save($mapstop);
        if(!is_null($result)) {
            if ($scene) {
                if ($mapstop->id === -1) {
                    $result = DB::insert(DB::table_name('mapstops_to_scenes'), [
                        'mapstop_id' => $mapstop->id,
                        'scene_id' => $scene->id,
                        'type' => $mapstop->type
                    ]);
                } else {
                    $result = DB::update_where(DB::table_name('mapstops_to_scenes'), [
                        'type' => $mapstop->type
                    ], [
                        'mapstop_id' => $mapstop->id,
                        'scene_id' => $scene->id
                    ]);
                }
                if (is_null($result)) {
                    MessageService::instance()->add_model_messages($mapstop);
                    return self::create_bad_request_view("Nicht gespeichert");
                }
                MessageService::instance()->add_success('Gespeichert');
                self::redirect(RouteParams::edit_mapstop($mapstop->id, $scene->id));
            } else {
                MessageService::instance()->add_success('Gespeichert');
                self::redirect(RouteParams::edit_mapstop($mapstop->id));
            }
        } else {
            MessageService::instance()->add_model_messages($mapstop);
            return self::create_bad_request_view("Nicht gespeichert");
        }
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
        // a mapstop is only editable if the tour is, so check that
        return self::filter_if_tour_not_editable($tour);
    }

    /**
     * @return View|null    A view for the error encountered or null if every-
     *                      thing is okay.
     */
    private static function filter_if_tour_not_editable($tour) {
        if(empty($tour)) {
            $msg = "Keine Tour für Stop.";
            debug_log($msg);
            return self::create_internal_error_view($msg, 500);
        } else {
            if(!UserService::instance()->user_may_edit_tour($tour)) {
                return self::create_access_denied_view();
            }
        }
    }

    /**
     * @return array|null   The params for a mapstop or null on error.
     */
    private static function get_mapstop_params() {
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