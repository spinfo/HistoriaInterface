<?php
namespace SmartHistoryTourManager;

/**
 * A simple static class that knows about the paths to views.
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
}

?>