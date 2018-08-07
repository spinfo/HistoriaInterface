<?php
namespace SmartHistoryTourManager;

require_once( dirname(__FILE__) . '/abstract_controller.php');
require_once( dirname(__FILE__) . '/../views/view.php');
require_once( dirname(__FILE__) . '/../models/scene.php');
require_once( dirname(__FILE__) . '/../models/scenes.php');
require_once( dirname(__FILE__) . '/../models/tours.php');


class ScenesController extends AbstractController {

    const SCENE_PARAMS = array(
        'shtm_scene' => array(
            'id' => 0,
        )
    );

    public static function new() {
        $tour_id = RouteParams::get_tour_id_value();
        $tour = Tours::instance()->get($tour_id, false, false, true);

        $error_view = self::filter_if_tour_not_editable($tour);
        if(empty($error_view)) {
            $scenes = Scenes::instance()->get_possible_scenes($tour);

            if(empty($scenes)) {
                $msg = "Bitte laden Sie zuerst neue Szenen über den Media Upload hoch.";
                MessageService::instance()->add_info($msg);
            }

            $view = new View(ViewHelper::new_scene_view(), array(
                'tour' => $tour,
                'scenes' => $scenes
            ));
        } else {
            $view = $error_view;
        }
        self::wrap_in_page_view($view)->render();
    }

    public static function add() {
        $tour_id = RouteParams::get_tour_id_value();
        $tour = Tours::instance()->get($tour_id, false, false, true);

        $error_view = self::filter_if_tour_not_editable($tour);
        if(empty($error_view)) {
            $params = self::get_scene_params();

            if(!empty($params)) {
                $scene = Scenes::instance()->get($params['id']);
                $scene->tour_id = $tour->id;
                $view = self::handle_insert_or_update($scene);
            } else {
                $view = self::create_bad_request_view("Bad input for scene.");
            }
        } else {
            $view = $error_view;
        }
        debug_log("view: " . var_export($view, true));
        self::wrap_in_page_view($view)->render();
    }

    public static function new_stop() {
        $id = RouteParams::get_id_value();
        $scene = Scenes::instance()->get($id);
        $tour = Tours::instance()->get($scene->tour_id, true, true, true);

        $error_view = self::filter_if_not_editable($scene, $id);
        if(is_null($error_view)) {
            $view = new View(ViewHelper::add_scene_stop(), array(
                'scene' => $scene,
                'tour' => $tour
            ));
        } else {
            $view = $error_view;
        }

        if(!empty($tour)) {
            Tours::instance()->set_related_objects_on($tour);
        }

        self::wrap_in_page_view($view)->render();
    }

    public static function set_marker() {
        $id = RouteParams::get_id_value();
        $mapstop = Mapstops::instance()->get($id);
        $scene_id = RouteParams::get_sene_id_value();
        $scene = Scenes::instance()->get($scene_id);

        try {
            $coordinate = new Coordinate();
            $result = Coordinates::instance()->get_by_mapstop_id($mapstop->id);
            if(!empty($result)) {
                $coordinate->id = intval($result->id);
                $coordinate->created_at = $result->created_at;
                $coordinate->updated_at = $result->updated_at;
            }
            $coordinate->reference = "scene";
            $coordinate->lat = floatval($_POST['x']);
            $coordinate->lon = floatval($_POST['y']);
            $result = Coordinates::instance()->save($coordinate);
            if(empty($result)) {
                throw new DB_Exception('Fehler in Coordinates');
            }

            DB::delete(Scenes::instance()->join_mapstops_table, [
                'mapstop_id' => $mapstop->id,
                'scene_id' => $scene->id
            ]);

            $result = DB::insert(Scenes::instance()->join_mapstops_table, [
                'mapstop_id' => $mapstop->id,
                'scene_id' => $scene->id,
                'coordinate_id' => $coordinate->id
            ]);
            if (is_null($result)) {
                throw new DB_Exception('Fehler in join table');
            }

            MessageService::instance()->add_success('Marker erfolgreich gesetzt.');
            self::redirect(RouteParams::new_scene_stop($scene->id));
        }
        catch (DB_Exception $e) {
            $msg = 'Marker konnte nicht gesetzt werden (' . $e->getMessage() . ')';
            MessageService::instance()->add_error($msg);
            self::redirect(RouteParams::new_scene_stop($scene->id));
        }
    }

    public static function delete() {
        $id = RouteParams::get_id_value();
        $scene = Scenes::instance()->get($id);
        $tour = Tours::instance()->get($scene->tour_id);

        $error_view = self::filter_if_not_editable($scene, $id);
        if(is_null($error_view)) {
            $view = new View(ViewHelper::delete_scene_view(), array(
                'scene' => $scene,
                'tour' => $tour,
            ));
        } else {
            $view = $error_view;
        }
        self::wrap_in_page_view($view)->render();
    }

    public static function destroy() {
        // attempt to get the tour by id
        $id = RouteParams::get_id_value();
        $scene = Scenes::instance()->get($id);

        // filter for basic errors
        $error_view = self::filter_if_not_editable($scene, $id);
        if(is_null($error_view)) {
            // attempt the delete
            try {
                $result = Scenes::instance()->delete($scene);
                if(empty($result)) {
                    throw new DB_Exception('Unbekannter Fehler');
                } else {
                    MessageService::instance()->add_success('Scene gelöscht.');
                    self::redirect(RouteParams::edit_tour_track($scene->tour_id));
                }
            } catch(DB_Exception $e) {
                $msg = 'Szene nicht gelöscht (' . $e->getMessage() . ')';
                MessageService::instance()->add_error($msg);
                self::redirect(RouteParams::delete_scene($scene->id));
            }
        } else {
            $view = $error_view;
        }
        self::wrap_in_page_view($view)->render();
    }

    private static function filter_if_not_editable($scene, $id) {
        if(empty($scene)) {
            return self::create_not_found_view("Scene existiert nicht: '$id'.");
        }
        $tour = Tours::instance()->get($scene->tour_id, false, false, true);
        return self::filter_if_tour_not_editable($tour);
    }

    private static function filter_if_tour_not_editable($tour) {
        if(empty($tour)) {
            $msg = "Keine Tour für Scene.";
            debug_log($msg);
            return self::create_internal_error_view($msg, 500);
        } else {
            if(!UserService::instance()->user_may_edit_tour($tour)) {
                return self::create_access_denied_view();
            }
        }
    }

    private static function get_scene_params() {
        $params = self::filter_params(self::SCENE_PARAMS, $_POST);
        if(is_null($params)) {
            debug_log("Could not read scene params from POST data.");
            return null;
        }

        $params = $params['shtm_scene'];
        if(is_array($_POST['shtm_scene']['post_ids'])) {
            $ids = array_map('intval', $_POST['shtm_mapstop']['post_ids']);
            $ids = array_filter($ids, function($id) { return ($id > 0); });
            $params['post_ids'] = array_unique($ids);
        }
        return $params;
    }

    private static function handle_insert_or_update($scene) {
        $result = Scenes::instance()->save($scene);
        if(!is_null($result)) {
            MessageService::instance()->add_success('Gespeichert');
            self::redirect(RouteParams::edit_tour_track($scene->tour_id));
        } else {
            MessageService::instance()->add_model_messages($scene);
            return self::create_bad_request_view("Nicht gespeichert");
        }
    }
}