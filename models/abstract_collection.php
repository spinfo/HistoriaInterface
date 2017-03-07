<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/../user_service.php');
require_once(dirname(__FILE__) . '/../db.php');

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

    /**
     * Returns the numerically first id from the collection's table.
     *
     * @return int  The id or DB::BAD_ID if none was found.
     */
    public function first_id() {
        return DB::first_id($this->table);
    }

    /**
     * Checks if an id is present in the collection's table.
     *
     * @return bool true if  result was found else false.
     */
    public function valid_id($id) {
        return DB::valid_id($this->table, $id);
    }

    // delegates the update to a child function
    public function update($model) {
        return $this->db_update($model);
    }

    // delegates the insert to a child function
    public function insert($model) {
        return $this->db_insert($model);
    }

    // delegates the delete to a child function
    public function delete($model) {
        return $this->db_delete($model);
    }

    abstract protected function db_update($model);

    abstract protected function db_insert($model);

    abstract protected function db_delete($model);
}

?>