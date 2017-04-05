<?php
namespace SmartHistoryTourManager;

require_once( dirname(__FILE__) . '/abstract_controller.php');
require_once( dirname(__FILE__) . '/../views/view.php');
require_once( dirname(__FILE__) . '/../models/places.php');
require_once( dirname(__FILE__) . '/../models/place.php');
require_once( dirname(__FILE__) . '/../models/areas.php');
require_once( dirname(__FILE__) . '/../route_params.php');
require_once( dirname(__FILE__) . '/../user_service.php');
require_once( dirname(__FILE__) . '/../message_service.php');


class PlacesController extends AbstractController {

    // defines accepted parameters for a place
    const PLACE_PARAMS_WHITELIST = array(
        'shtm_place' => array(
            'name' => "",
            'lat' => 0.0,
            'lon' => 0.0,
        )
    );

    public static function index() {
        $current_area_id = self::determine_area_id();

        $places_list = Places::instance()->list_by_area($current_area_id);
        $areas_list = Areas::instance()->list_simple();

        $view = new View(ViewHelper::index_places_view(),
            array(
                'user_service' => UserService::instance(),
                'places_list' => $places_list,
                'areas_list' => $areas_list,
                'current_area_id' => $current_area_id,
            )
        );
        self::wrap_in_page_view($view)->render();
    }

    public static function new() {
        $places = Places::instance();

        $place = $places->create();

        $current_area_id = self::determine_area_id();
        $place->area_id = $current_area_id;
        $area = Areas::instance()->get($current_area_id);
        if(!is_null($area)) {
            $center_lat = ($area->coordinate1->lat + $area->coordinate2->lat)/2;
            $center_lon = ($area->coordinate1->lon + $area->coordinate2->lon)/2;
            $place->coordinate->lat = $center_lat;
            $place->coordinate->lon = $center_lon;
        } else {
            debug_log("Area not retrievabel from selection id.");
        }

        $view = new View(ViewHelper::new_place_view(), array(
            'areas' => Areas::instance()->list_simple(),
            'current_area_id' => $current_area_id,
            'place' => $place,
            'action_params' => RouteParams::create_place()
        ));
        self::wrap_in_page_view($view)->render();
    }

    public static function create() {
        $places = Places::instance();

        $place_params = self::get_place_params();
        $area_id = intval($_POST['shtm_place']['area_id']);
        $view = null;
        if(!empty($place_params) && Areas::instance()->valid_id($area_id)) {
            $place = $places->create($place_params);
            $place->area_id = $area_id;
            $place->user_id = UserService::instance()->user_id();
            $result = $places->save($place);
            if(empty($result)) {
                MessageService::instance()->add_error("Ort nicht erstellt.");
                MessageService::instance()->add_model_messages($place);
            } else {
                MessageService::instance()->add_success("Ort erstellt!");
                self::redirect(RouteParams::edit_place($place->id));
            }
        } else {
            if(!Areas::instance()->valid_id($area_id)) {
                $msg = "Invalid area id: '$area_id'.";
                MessageService::instance()->add_error($msg);
            }
            $view = self::create_bad_request_view("Ungültiger Input");
        }

        self::wrap_in_page_view($view)->render();
    }

    public static function edit() {
        $places = Places::instance();
        $user_service = UserService::instance();

        $view;
        $id = RouteParams::get_id_value();
        if(empty($id)) {
            $view = self::create_bad_request_view("Ungültiger Input: id fehlt.");
        } else {
            $place = $places->get($id);
            if(empty($place)) {
                $view = self::create_not_found_view("Kein Objekt für id: $id.");
            } else {
                if($user_service->user_may_edit_place($place)) {
                    $view = new View(ViewHelper::edit_place_view(), array(
                            'area' => Areas::instance()->get($place->area_id),
                            'heading' => 'Ort bearbeiten',
                            'place' => $place,
                            'action_params' => RouteParams::update_place($place->id)
                        )
                    );
                } else {
                    $view = self::create_access_denied_view();
                }
            }
        }

        self::wrap_in_page_view($view)->render();
    }

    public static function update() {
        $places = Places::instance();

        $view;
        $id = RouteParams::get_id_value();
        if(empty($id)) {
            $view = self::create_bad_request_view("Ungültiger Input: id fehlt.");
        } else {
            $place = $places->get($id);
            if(empty($place)) {
                $view = self::create_not_found_view("Kein Objekt für id: $id.");
            } else {
                $place_params = self::get_place_params();
                if(!empty($place_params)) {
                    $places->update_values($place, $place_params);
                    $place = $places->save($place);

                    MessageService::instance()->add_success("Änderungen gespeichert!");
                    self::redirect(RouteParams::edit_place($place->id));
                } else {
                    $view = self::create_bad_request_view("Ungültiger Input.");
                }
            }
        }

        self::wrap_in_page_view($view)->render();
    }

    public static function delete() {
        $places = Places::instance();
        $user_service = UserService::instance();

        $id = RouteParams::get_id_value();
        $place = $places->get($id);

        $view = null;
        if(empty($place)) {
            $view = self::create_not_found_view("Kein Ort mit id: $id.");
        } else {
            if($user_service->user_may_edit_place($place)) {
                $view = new View(ViewHelper::delete_place_view(), array(
                    'action_params' => RouteParams::destroy_place($place->id),
                    'place' => $place
                ));
            } else {
                $view = self::create_access_denied_view();
            }
        }
        self::wrap_in_page_view($view)->render();
    }

    public static function destroy() {
        $places = Places::instance();
        $user_service = UserService::instance();

        $id = RouteParams::get_id_value();
        $place = $places->get($id);

        $view = null;
        if(empty($place)) {
            // Could not retrieve place
            MessageService::instance()->add_error("Kein Ort mit id: '$id'");
            $view = new View(ViewHelper::empty_view(), null);
        } else {
            if($user_service->user_may_edit_place($place)) {
                // actual delete happens here
                $result = $places->delete($place);

                if(is_null($result)) {
                    // Something went wrong
                    MessageService::instance()->add_error("Konnte den Ort nicht löschen.");
                    $view = new View(ViewHelper::delete_place_view(), array(
                        'action_params' => RouteParams::destroy_place($place->id),
                        'place' => $place
                    ));
                } else {
                    // success: redirect to index
                    MessageService::instance()->add_success("Ort gelöscht!");
                    self::redirect(RouteParams::index_places());
                }
            } else {
                // permissions insufficient
                $view = self::create_access_denied_view();
            }
        }
        self::wrap_in_page_view($view)->render();
    }

    private static function get_place_params() {
        $result = self::filter_params(self::PLACE_PARAMS_WHITELIST, $_POST);
        if (empty(result) || !isset($result['shtm_place'])) {
            return null;
        } else {
            return $result['shtm_place'];
        }
    }

}


?>