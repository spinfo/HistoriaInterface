<?php
namespace SmartHistoryTourManager;

abstract class AbstractModel {

    public $id = -1;

    public $created_at;

    public $updated_at;

    abstract function is_valid();

    public $messages = array();

    protected function do_check($condition, $message) {
        if($condition) {
            unset($this->messages[$message]);
        } else {
            $this->messages[$message] = true;
        }
        return $condition;
    }
}

?>