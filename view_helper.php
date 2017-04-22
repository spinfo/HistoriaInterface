<?php
namespace SmartHistoryTourManager;

/**
 * A simple static class that knows the paths to views and view related files.
 */
class ViewHelper {

    public static function empty_view() {
        return dirname(__FILE__) . '/views/empty_view.php';
    }

    public static function page_wrapper_view() {
        return dirname(__FILE__) . '/views/page_wrapper.php';
    }


    public static function index_areas_view() {
        return dirname(__FILE__) . '/views/area_index.php';
    }

    public static function new_area_view() {
        return dirname(__FILE__) . '/views/area_edit_or_new.php';
    }

    public static function edit_area_view() {
        return dirname(__FILE__) . '/views/area_edit_or_new.php';
    }

    public static function delete_area_view() {
        return dirname(__FILE__) . '/views/area_delete.php';
    }


    public static function index_places_view() {
        return dirname(__FILE__) . '/views/place_index.php';
    }

    public static function new_place_view() {
        return dirname(__FILE__) . '/views/place_edit_or_new.php';
    }

    public static function edit_place_view() {
        return dirname(__FILE__) . '/views/place_edit_or_new.php';
    }

    public static function delete_place_view() {
        return dirname(__FILE__) . '/views/place_delete.php';
    }



    public static function index_tours_view() {
        return dirname(__FILE__) . '/views/tour_index.php';
    }

    public static function tour_report_view() {
        return dirname(__FILE__) . '/views/tour_report.php';
    }

    public static function new_tour_view() {
        return dirname(__FILE__) . '/views/tour_new.php';
    }

    public static function edit_tour_view() {
        return dirname(__FILE__) . '/views/tour_edit.php';
    }

    public static function edit_tour_track_view() {
        return dirname(__FILE__) . '/views/tour_edit_track.php';
    }

    public static function edit_tour_stops_view() {
        return dirname(__FILE__) . '/views/tour_edit_stops.php';
    }

    public static function delete_tour_view() {
        return dirname(__FILE__) . '/views/tour_delete.php';
    }



    public static function new_mapstop_view() {
        return dirname(__FILE__) . '/views/mapstop_new.php';
    }

    public static function edit_mapstop_view() {
        return dirname(__FILE__) . '/views/mapstop_edit.php';
    }

    public static function delete_mapstop_view() {
        return dirname(__FILE__) . '/views/mapstop_delete.php';
    }



    public static function index_tour_records_view() {
        return dirname(__FILE__) . '/views/tour_record_index.php';
    }

    public static function tour_record_view() {
        return dirname(__FILE__) . '/views/tour_record.php';
    }

    public static function new_tour_record_view() {
        return dirname(__FILE__) . '/views/tour_record_new.php';
    }



    public static function choose_current_area_template() {
        return dirname(__FILE__) . '/views/templates/choose_current_area.php';
    }

    public static function single_area_header_template() {
        return dirname(__FILE__) . '/views/templates/single_area_header.php';
    }

    public static function single_place_header_template() {
        return dirname(__FILE__) . '/views/templates/single_place_header.php';
    }

    public static function single_tour_header_template() {
        return dirname(__FILE__) . '/views/templates/single_tour_header.php';
    }

    public static function single_mapstop_header_template() {
        return dirname(__FILE__) . '/views/templates/single_mapstop_header.php';
    }

    public static function area_map_template() {
        return dirname(__FILE__) . '/views/templates/area_map.php';
    }

    public static function place_map_template() {
        return dirname(__FILE__) . '/views/templates/place_map.php';
    }

    public static function tour_map_template() {
        return dirname(__FILE__) . '/views/templates/tour_map.php';
    }

    public static function coordinate_template() {
        return dirname(__FILE__) . '/views/templates/coordinate.php';
    }

    public static function mapstop_simple_form_template() {
        return dirname(__FILE__) . '/views/templates/mapstop_simple_form.php';
    }

    public static function area_selection_template() {
        return dirname(__FILE__) . '/views/templates/area_selection.php';
    }

    public static function tour_report_yaml_template() {
        return dirname(__FILE__) . '/views/templates/tour_report_yaml.php';
    }

    public static function tour_record_template() {
        return dirname(__FILE__) . '/views/templates/tour_record.php';
    }



    public static function map_util_js() {
        return dirname(__FILE__) . '/views/js/map-util.js';
    }

    public static function coordinate_form_binding_js() {
        return dirname(__FILE__) . '/views/js/coordinate-form-binding.js';
    }

    public static function place_edit_map_js() {
        return dirname(__FILE__) . '/views/js/place-edit-map.js';
    }
}

?>