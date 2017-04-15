<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/abstract_controller.php');
require_once(dirname(__FILE__) . '/../route_params.php');
require_once(dirname(__FILE__) . '/../user_service.php');
require_once(dirname(__FILE__) . '/../views/view.php');
require_once(dirname(__FILE__) . '/../view_helper.php');
require_once(dirname(__FILE__) . '/../models/areas.php');
require_once(dirname(__FILE__) . '/../models/area.php');
require_once(dirname(__FILE__) . '/../models/tours.php');

class AreasController extends AbstractController {

    // defines accepted parameters for a place
    const AREA_PARAMS = array(
        'shtm_area' => array(
            'name' => "",
            'c1_lat' => 0.0,
            'c1_lon' => 0.0,
            'c2_lat' => 0.0,
            'c2_lon' => 0.0,
        )
    );


    public static function index() {
        $areas = Areas::instance()->list_simple();
        $tour_counts = array();
        $place_counts = array();
        foreach($areas as $area) {
            $where = array('area_id' => $area->id);
            $tour_counts[$area->id] = Tours::instance()->count($where);
            $place_counts[$area->id] = Places::instance()->count($where);
        }

        $view = new View(ViewHelper::index_areas_view(), array(
            'areas' => $areas,
            'tour_counts' => $tour_counts,
            'place_counts' => $place_counts,
        ));
        self::wrap_in_page_view($view)->render();
    }

    public static function new() {
        $area = new Area();

        $error_view = self::filter_if_not_editable($area, null);
        if(is_null($error_view)) {
            $view = new View(ViewHelper::edit_area_view(), array(
                'action_params' => RouteParams::create_area($id),
                'area' => $area
            ));
        } else {
            $view = $error_view;
        }
        self::wrap_in_page_view($view)->render();
    }

    public static function create() {
        $area = new Area();

        $error_view = self::filter_if_not_editable($area, null);
        if(is_null($error_view)) {
            $view = self::handle_insert_or_update($area);
        } else {
            $view = $error_view;
        }
        self::wrap_in_page_view($view)->render();
    }

    public static function edit() {
        $id = RouteParams::get_id_value();
        $area = Areas::instance()->get($id);

        $error_view = self::filter_if_not_editable($area, $id);
        if(is_null($error_view)) {
            $view = new View(ViewHelper::edit_area_view(), array(
                'action_params' => RouteParams::update_area($id),
                'area' => $area
            ));
        } else {
            $view = $error_view;
        }
        self::wrap_in_page_view($view)->render();
    }

    public static function update() {
        $id = RouteParams::get_id_value();
        $area = Areas::instance()->get($id);

        $error_view = self::filter_if_not_editable($area, $id);
        if(is_null($error_view)) {
            $view = self::handle_insert_or_update($area);
        } else {
            $view = $error_view;
        }
        self::wrap_in_page_view($view)->render();
    }

    public static function delete() {
        $id = RouteParams::get_id_value();
        $area = Areas::instance()->get($id);

        $error_view = self::filter_if_not_editable($area, $id);
        if(is_null($error_view)) {
            $tours = Tours::instance()->list_by_area($area->id);
            $view = new View(ViewHelper::delete_area_view(), array(
                'area' => $area,
                'tours' => $tours,
            ));
        } else {
            $view = $error_view;
        }
        self::wrap_in_page_view($view)->render();
    }

    public static function destroy() {
        $id = RouteParams::get_id_value();
        $area = Areas::instance()->get($id);

        $error_view = self::filter_if_not_editable($area, $id);
        if(is_null($error_view)) {
            // make sure, that there is no tour connected
            $count = Tours::instance()->count(array('area_id' => $area->id));
            if($count === 0) {
                $places = Places::instance()->list_by_area($area->id);
                // delete the places, this should not fail normally, but issue
                // a warning if it does
                foreach ($places as $place) {
                    try {
                        Places::instance()->delete($place);
                    } catch(DB_Exception $e) {
                        $msg = "Konnte Ort '$place->id' nicht löschen.";
                        $msg .= ' (' . $e->getMessage() . ')';
                        MessageService::instance()->add_warning($msg);
                    }
                }
                // Now delete the area itself
                try {
                    $result = Areas::instance()->delete($area);
                    MessageService::instance()->add_success('Gebiet gelöscht');
                    self::redirect(RouteParams::index_areas());
                } catch(DB_Exception $e) {
                    $msg = 'Fehler beim Löschen. (' . $e->getMessage() . ')';
                    MessageService::instance()->add_error($msg);
                    self::redirect(RouteParams::delete_area($area->id));
                }
            } else {
                $msg = "Gebiet mit Tour(en) kann nicht gelöscht werden.";
                $view = self::create_bad_request_view($smg);
            }
        } else {
            $view = $error_view;
        }
        // this is only reached when an error occured
        self::wrap_in_page_view($view)->render();
    }

    private static function filter_if_not_editable($area, $id) {
        if(empty($area)) {
            return self::create_not_found_view(
                "Gebiet existiert nicht: '$id'.");
        } else if(!UserService::instance()->is_admin()) {
            return self::create_access_denied_view();
        }
        return null;
    }

    private static function read_area_params() {
        $filtered = self::filter_params(self::AREA_PARAMS, $_POST);
        if(is_null($filtered)) {
            return null;
        } else {
            return $filtered['shtm_area'];
        }
    }

    /**
     * @return View     A view for the error encountered on error. Should not
     *                  return at all on success (redirects to edit route).
     */
    private static function handle_insert_or_update($area) {
        $params = self::read_area_params();
        if(empty($params)) {
            return self::create_bad_request_view("Nicht gespeichert.");
        }
        Areas::instance()->update_values($area, $params);
        $result = Areas::instance()->save($area);
        if(empty($result)) {
            MessageService::instance()->add_model_messages($area);
            return self::create_bad_request_view("Nicht gespeichert.");
        }
        MessageService::instance()->add_success("Gespeichert.");
        self::redirect(RouteParams::edit_area($area->id));
    }

}

?>