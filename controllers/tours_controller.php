<?php
namespace SmartHistoryTourManager;

require_once( dirname(__FILE__) . '/abstract_controller.php');
require_once( dirname(__FILE__) . '/../views/view.php');
require_once( dirname(__FILE__) . '/../models/areas.php');
require_once( dirname(__FILE__) . '/../models/tour.php');
require_once( dirname(__FILE__) . '/../models/tours.php');
require_once( dirname(__FILE__) . '/../models/scenes.php');
require_once( dirname(__FILE__) . '/../route_params.php');

class ToursController extends AbstractController {

    const TOUR_META_PARAMS = array(
        'shtm_tour' => array(
            'name' => '',
            'intro' => '',
            'type' => '',
            'walk_length' => 0,
            'duration' => 0,
            'tag_what' => '',
            'tag_where' => '',
            'tag_when_start' => '',
            'accessibility' => '',
            'author' => '',
        )
    );

    const ALLOWED_INPUT_DTS = array(
        'TT.MM.JJJJ HH:MM:SS',
        'TT.MM.JJJJ HH:MM',
        'TT.MM.JJJJ',
        'MM.JJJJ',
        'JJJJ'
    );

    public static function index() {
        $current_area_id = self::determine_area_id();
        $tours_list = Tours::instance()->list_by_area($area_id);
        $areas_list = Areas::instance()->list_simple();

        $view = new View(ViewHelper::index_tours_view(),
            array(
                'tours_list' => $tours_list,
                'areas_list' => $areas_list,
                'current_area_id' => $current_area_id,
            )
        );
        self::wrap_in_page_view($view)->render();
    }

    public function report() {
        // attempt to get the the tour to display the report on
        $id = RouteParams::get_id_value();
        $tour = Tours::instance()->get($id, true, true, true);

        if(!empty($tour)) {
            Tours::instance()->set_related_objects_on($tour);
            $content = array('tour' => $tour);
            // respond with just the yaml and exit, if requested
            if($_SERVER['HTTP_CONTENT_TYPE'] === 'text/yaml') {
                $view = new View(ViewHelper::tour_report_yaml_template(),
                    $content);
                ob_end_clean();
                $view->render();
                exit();
            } else {
                $view = new View(ViewHelper::tour_report_view(), $content);
            }
        } else {
            $msg = "Tour '$tour->id' existiert nicht.";
            $view = self::create_not_found_view($msg);
        }
        self::wrap_in_page_view($view)->render();
    }

    // just renders an empty form for a tour's name and area
    public static function new() {
        $areas_list = Areas::instance()->list_simple();
        $current_area_id = self::determine_area_id();
        $view = new View(ViewHelper::new_tour_view(), array(
            'action_params' => RouteParams::create_tour(),
            'areas_list' => $areas_list,
            'current_area_id' => $current_area_id,
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
        $view = self::determine_edit_view($tour, 'edit_info');
        // and render the view in page
        self::wrap_in_page_view($view)->render();
    }

    public static function edit_track() {
        // attempt to get the tour to edit
        $id = RouteParams::get_id_value();
        $tour = Tours::instance()->get($id, true, true, true);

        if ($tour->is_indoor()) {
            $view = self::determine_edit_view($tour, 'edit_track_indoor');
        } else {
            $view = self::determine_edit_view($tour, 'edit_track');
        }

        if(!empty($tour)) {
            Tours::instance()->set_related_objects_on($tour);
        }
        self::wrap_in_page_view($view)->render();
    }

    public static function edit_stops() {
        // attempt to get the tour to edit
        $id = RouteParams::get_id_value();
        $tour = Tours::instance()->get($id, true, true, true);

        if ($tour->is_indoor()) {
            $view = self::determine_edit_view($tour, 'edit_stops_indoor');
        } else {
            $view = self::determine_edit_view($tour, 'edit_stops');
        }

        if(!empty($tour)) {
            Tours::instance()->set_related_objects_on($tour);
        }
        self::wrap_in_page_view($view)->render();
    }

    // this updates: either the tour's track (linked coordinates), that were
    // edited on the 'edit_track' route or it updates the tour information, that
    // was edited on the 'edit' route
    public static function update() {
        // get the tour to update
        $id = RouteParams::get_id_value();
        $tour = Tours::instance()->get($id, true, true, true);

        // if the tour itself is not editale, abort
        $view = null;
        $error_view = self::filter_if_not_editable($tour);
        if(is_null($error_view)) {
            // only proceed if we have valid update params
            $params = self::get_update_params();
            if(!empty($params)) {
                if(!empty($params['coordinates'])) {
                    // just update coordinates on track update
                    Tours::instance()->update_track($tour, $params['coordinates']);
                    // redirect url leads back to track edit
                    $back_params = RouteParams::edit_tour_track($tour->id);
                } else {
                    // update values
                    Tours::instance()->update_values($tour, $params);
                    // TODO: handle all time stuff in a util
                    // update times (returned as datetime)
                    try {
                        if(isset($params['tag_when_start'])) {
                            $tour->set_tag_when_start($params['tag_when_start']);
                            unset($params['tag_when_start']);
                        }
                        if(isset($params['tag_when_end'])) {
                            $tour->set_tag_when_end($params['tag_when_end']);
                            unset($params['tag_when_end']);
                        }
                    } catch(\Exception $e) {
                        $msg = sprintf('Falsches Datumsformat: %s - %s <br>',
                            $params['tag_when_start'], $params['tag_when_end']);
                        $msg .= 'Nutzen Sie: ';
                        $msg .= implode(' | ', self::ALLOWED_INPUT_DTS);
                        MessageService::instance()->add_error($msg);
                        $view = self::create_view_with_exception($e, 500);
                    }
                    // redirect url leads back to tour meta edit
                    $back_params = RouteParams::edit_tour($tour->id);
                }
                // actually update the tour
                $result = Tours::instance()->update($tour);
                if(!$result) {
                    MessageService::instance()->add_model_messages($tour);
                    $msg = "Tour konnte nicht gespeichert werden.";
                    $view = self::create_bad_request_view($msg);
                }
                // proceed only if the view has not been set to an error view
                if(empty($view)) {
                    MessageService::instance()->add_success("Gespeichert!");
                    self::redirect($back_params);
                }
            } else {
                $view = self::create_bad_request_view(
                    "Ungültiger Input: Fehledende update Parameter");
            }
        } else {
            $view = $error_view;
        }
        self::wrap_in_page_view($view)->render();
    }

    // this just updates the order of mapstops of this tour, then redirects
    // to the edit_stops route
    public static function update_stops() {
        // get the tour to update
        $id = RouteParams::get_id_value();
        $tour = Tours::instance()->get($id, true, false, true);

        $scene_id = RouteParams::get_scene_id_value();
        $scene = null;
        if ($scene_id > 0) {
            $scene = Scenes::instance()->get($scene_id);
        }

        // if the tour itself is not editale, abort
        $error_view = self::filter_if_not_editable($tour);
        if(is_null($error_view)) {
            $ids = self::get_mapstop_ids_from_input_for($tour);
            if($ids != false) {
                $result = Tours::instance()->update_mapstop_positions($tour->id, $ids);
                if($result != false) {
                    MessageService::instance()->add_success("Positionen übernommen");
                    if ($scene) {
                        self::redirect(RouteParams::new_scene_stop($scene->id));
                    } else {
                        self::redirect(RouteParams::edit_tour_stops($tour->id));
                    }
                } else {
                    $error_view = self::create_bad_request_view(
                        "Mapstop-Positionen konnten nicht gespeichert werden.");
                }
            } else {
                $error_view = self::create_bad_request_view(
                    "Ungültiger Input: Keine mapstop ids auslesbar.");
            }
        }
        self::wrap_in_page_view($error_view)->render();
    }

    public static function update_scenes() {
        $id = RouteParams::get_id_value();
        $tour = Tours::instance()->get($id, true, false, true);

        $error_view = self::filter_if_not_editable($tour);
        if(is_null($error_view)) {
            $ids = self::get_scene_ids_from_input_for($tour);
            if($ids != false) {
                $result = Tours::instance()->update_scene_positions($tour->id, $ids);
                if($result != false) {
                    MessageService::instance()->add_success("Positionen übernommen");
                    self::redirect(RouteParams::edit_tour_track($tour->id));
                } else {
                    $error_view = self::create_bad_request_view(
                        "Positionen der Szenen konnten nicht gespeichert werden");
                }
            } else {
                $error_view = self::create_bad_request_view(
                    "Keine Szenen ids auslesbar.");
            }
        }
        self::wrap_in_page_view($error_view)->render();
    }

    public static function delete() {
        // attempt to get the tour by id
        $id = RouteParams::get_id_value();
        $tour = Tours::instance()->get($id);
        // filter for basic errors
        $error_view = self::filter_if_not_editable($tour);
        if(!empty($error_view)) {
            $view = $error_view;
        } else {
            $view = new View(ViewHelper::delete_tour_view(), array(
                'tour' => $tour,
            ));
        }
        self::wrap_in_page_view($view)->render();
    }

    public static function destroy() {
        // attempt to get the tour by id
        $id = RouteParams::get_id_value();
        $tour = Tours::instance()->get($id, true, true, true);
        // filter for basic errors
        $error_view = self::filter_if_not_editable($tour);
        if(is_null($error_view)) {
            // attempt the delete
            try {
                $result = Tours::instance()->delete($tour);
                if(empty($result)) {
                    // this should in fact never be reached, as the same
                    // exception type should have been thrown before, but let's
                    // be paranoid.
                    throw new DB_Exception('Unbekannter Fehler');
                } else {
                    MessageService::instance()->add_success('Tour gelöscht.');
                    self::redirect(RouteParams::index_tours());
                }
            } catch(DB_Exception $e) {
                $msg = 'Tour nicht gelöscht (' . $e->getMessage() . ')';
                MessageService::instance()->add_error($msg);
                self::redirect(RouteParams::delete_tour($tour->id));
            }
        } else {
            $view = $error_view;
        }
        self::wrap_in_page_view($view)->render();
    }

    private static function determine_edit_view($tour, $action = 'edit_info') {
        $error_view = self::filter_if_not_editable($tour);
        if(!is_null($error_view)) {
            return $error_view;
        }
        // the tour's area should always be accessible, but test anyway
        $area = Areas::instance()->get($tour->area_id);
        if(empty($area)) {
            $e = new \Exception("Area not present for tour: '$tour->area_id'");
            return self::create_view_with_exception($e, 500);
        }
        // success, choose a file based on the $action param
        if($action == 'edit_info') {
            $file = ViewHelper::edit_tour_view();
        } else if($action == 'edit_track') {
            $file = ViewHelper::edit_tour_track_view();
        } else if($action == 'edit_track_indoor') {
            $file = ViewHelper::edit_tour_track_indoor_view();
        } else if($action == 'edit_stops') {
            $file = ViewHelper::edit_tour_stops_view();
        } else if($action == 'edit_stops_indoor') {
            $file = ViewHelper::edit_tour_stops_indoor_view();
        }
        // return the view with 'tour' and 'area' set
        return new View($file, array(
            'tour' => $tour,
            'area' => $area
        ));
    }

    // determine if the tour may be edited and if not return a suitable view
    // indicating the error, return null if the tour is editable by the user
    private static function filter_if_not_editable($tour) {
        // tour not found
        if(empty($tour)) {
            $msg = "Tour '$tour->id' existiert nicht.";
            return self::create_not_found_view($msg);
        }
        // test access rights
        if(!UserService::instance()->user_may_edit_tour($tour)) {
            return self::create_access_denied_view();
        }
        return null;
    }

    // Get the params to update a tour. There are two possibilities: Either the
    // meta information is updated or the tours track coordinates are. Return
    // only those values meant for either one or the other.
    private static function get_update_params() {
        if(isset($_POST['shtm_tour']['coordinates'])) {
            $input_coords = $_POST['shtm_tour']['coordinates'];
            $whitelist = array('lat' => 0.0, 'lon' => 0.0, 'id' => 0);
            $result = array( 'coordinates' => array());
            foreach($input_coords as $coord) {
                $filtered = self::filter_params($whitelist, $coord);
                if(!is_null($filtered)) {
                    $result['coordinates'][] = $filtered;
                }
            }
        } else {
            $result = self::filter_params(self::TOUR_META_PARAMS, $_POST);
            if(empty($result)) {
                return null;
            } else {
                $result = $result['shtm_tour'];
                // if there is a tag_when_end input, set it on the result
                $end_input = $_POST['shtm_tour']['tag_when_end'];
                if(!is_null($end_input)) {
                    $result['tag_when_end'] = $end_input;
                }
            }
        }
        if(empty($result)) {
            debug_log("Bad params in tour update.");
            return null;
        }
        return $result;
    }

    /**
     * Mapstops ids are given as an array of [ "id" => "position" ]. This parses
     * them from the POST parameters and returns an array of correctly ordered
     * mapstop ids.
     *
     * This does some checking against the actual tours mapstop_ids and returns
     * false if different ids are assumed in the input.
     *
     * @param   obj $tour   The tour to compare values against
     * @return  array|false An array of ids if successful, false if not.
     */
    function get_mapstop_ids_from_input_for($tour) {
        if(!isset($_POST['shtm_tour']['mapstop_ids'])) {
            return false;
        }
        // parse the order of mapstop ids from the params
        $new_mapstop_ids = array();
        foreach ($_POST['shtm_tour']['mapstop_ids'] as $id => $position) {
            $pos = intval($position);
            if($pos > 0 && $pos <= count($tour->mapstop_ids)) {
                $new_mapstop_ids[$pos - 1] = $id;
            } else {
                $error = true;
                break;
            }
        }
        // the arrays should be equal (disregarding positions)
        if(!$tour->is_indoor()) {
            if (!empty(array_diff($tour->mapstop_ids, $new_mapstop_ids)) ||
                !empty(array_diff($new_mapstop_ids, $tour->mapstop_ids))) {
                $error = true;
            }
        }
        return ($error) ? false : $new_mapstop_ids;
    }

    function get_scene_ids_from_input_for($tour) {
        if(!isset($_POST['shtm_tour']['scene_ids'])) {
            debug_log("No scene ids in post values.");
            return false;
        }
        $new_scene_ids = array();
        foreach ($_POST['shtm_tour']['scene_ids'] as $id => $position) {
            $pos = intval($position);
            if($pos > 0 && $pos <= count($tour->scene_ids)) {
                $new_scene_ids[$pos - 1] = $id;
            } else {
                $error = true;
                debug_log("Bad scene id position: " . $pos);
                break;
            }
        }
        // the arrays should be equal (disregarding positions)
        if(!empty(array_diff($tour->scene_ids, $new_scene_ids)) ||
            !empty(array_diff($new_scene_ids, $tour->scene_ids)) ) {
            $error = true;
            debug_log("Scene ids are not equal.");
        }
        return ($error) ? false : $new_scene_ids;
    }

}



?>