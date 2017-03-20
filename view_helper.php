<?php
namespace SmartHistoryTourManager;

/**
 * A simple static class that knows the paths to views.
 */
class ViewHelper {

    public static function empty_view() {
        return dirname(__FILE__) . '/views/empty_view.php';
    }

    public static function page_wrapper_view() {
        return dirname(__FILE__) . '/views/page_wrapper.php';
    }

    public static function index_places_view() {
        return dirname(__FILE__) . '/views/place_index.php';
    }

    public static function edit_place_view() {
        return dirname(__FILE__) . '/views/place_edit.php';
    }

    public static function delete_place_view() {
        return dirname(__FILE__) . '/views/place_delete.php';
    }

    public static function index_tours_view() {
        return dirname(__FILE__) . '/views/tour_index.php';
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

    public static function delete_tour_view() {
        return dirname(__FILE__) . '/views/tour_delete.php';
    }


    public static function choose_current_area_template() {
        return dirname(__FILE__) . '/views/templates/choose_current_area.php';
    }

    public static function single_place_header_template() {
        return dirname(__FILE__) . '/views/templates/single_place_header.php';
    }

    public static function single_tour_header_template() {
        return dirname(__FILE__) . '/views/templates/single_tour_header.php';
    }
}

?>