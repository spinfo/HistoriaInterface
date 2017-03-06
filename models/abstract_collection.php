<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/../user_service.php');

/**
 * A wrapper for model collections. Ensures that all model collections are
 * singleton classes.
 */
abstract class AbstractCollection {

    // Every child has to implement its own $instance like this:
    // protected static $instance = null;

    // Every child should implement its own $table like this:
    // public $table = DB::table_name('str');

    protected $user_service;

    public $table;

    static function instance() {
        if (static::$instance == null) {
            static::$instance = new static;
            static::$instance->user_service =
                UserService::instance();
        }
        return static::$instance;
    }

    /**
     * Sets values all models need from a database row, these are:
     *  - timestamps
     *
     * @param Object extending Model
     */
    protected function set_abstract_model_values($model, $db_row) {
        $db_row = (object) $db_row;
        $model->id = intval($db_row->id);
        $model->created_at = new \DateTime($db_row->created_at);
        $model->updated_at = new \DateTime($db_row->updated_at);
    }
}

?>