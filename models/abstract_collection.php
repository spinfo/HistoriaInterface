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

    /**
     * This will just delegate to the collection's db_update() function.
     *
     * @return bool|mixed   false on error, else undefined
     *                      (Result of DB::update()).
     */
    public function update($model) {
        return $this->db_update($model);
    }

    /**
     * This performs basic checking and delegates the insert to a child
     * method.
     *
     * @return int  The inserted id or DB::BAD_ID on error
     */
    public function insert($model) {
        if(empty($model->id) || $model->id == DB::BAD_ID) {
            return $this->db_insert($model);
        } else {
            $msg = "Can't insert to $this->table with existing id: $model->id";
            debug_log($msg);
            return DB::BAD_ID;
        }
    }

    /**
     * Performs basic checking on the input model, then delegates the
     *
     * @return object|null  The deleted model with id values set to DB::BAD_ID
     *                      on success or null on error.
     *
     * @throws DB_Exception If the delete fails.
     */
    public function delete($model) {
        if(empty($model->id) || $model->id == DB::BAD_ID) {
            throw new DB_Exception(
                "Can't delete from $this->table without id value.");
            return null;
        } else {
            return $this->db_delete($model);
        }
    }

    /**
     * Child implements this to update the model.
     *
     * @return bool|mixed   false on error, else undefined
     *                      (Result of DB::update()).
     */
    abstract protected function db_update($model);

    /**
     * Child implements this to insert a new model
     *
     * @return int  The inserted id or DB::BAD_ID on error
     */
    abstract protected function db_insert($model);

    /**
     * Child implements this to delete a model
     *
     * @return object|null  The deleted model with id values set to DB::BAD_ID
     *                      on success or null on error
     *
     * @throws DB_Exception If the delete fails.
     */
    abstract protected function db_delete($model);
}

?>