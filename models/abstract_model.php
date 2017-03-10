<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/../logging.php');

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

    // convenience method for models linked to coordinates, called during
    // is_valid() to check the validity of the linked coordinates and copy
    // any messages from them to the calling model
    protected function check_coordinate($coordinate, $name) {
        $coord_present = $this->do_check(
            ($coordinate instanceof Coordinate),
            "$name not present or of incorrect class");

        if($coord_present && !$coordinate->is_valid()) {
            foreach($coordinate->messages as $msg => $val) {
                $this->messages["$name: $msg"] = true;
            }
        }
    }

    public function debug_log_messages() {
        foreach($this->messages as $msg => $bool) {
            debug_log("invalid: " . $msg);
        }
    }
}

?>