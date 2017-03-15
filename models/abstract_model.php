<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/../logging.php');

abstract class AbstractModel {

    public $id = -1;

    public $created_at;

    public $updated_at;

    public $messages = array();

    // every child must implement a validity check
    abstract protected function do_validity_check();

    /**
     * Method to check the model's validity, i.e. whether it is fit to be added
     * to or updated in the database.
     *
     * @return bool     Whether the model is valid or not.
     */
    public function is_valid() {
        // reset the messages indicating if the model is invalid
        $this->messages = array();

        // call the validity check of the child
        $this->do_validity_check();

        // returns true if no messages indicating invalidity were added
        return empty($this->messages);
    }

    // a method the child can call to check for a condition and add a message
    // if the condition does not apply
    protected function do_check($condition, $message) {
        if(!$condition) {
            $this->messages[$message] = true;
        }
        return $condition;
    }

    // convenience method for models linked to coordinates, child can call this
    // during it's validity check to check the validity of the linked
    // coordinates and copy any messages from them to the calling model
    protected function check_coordinate($coordinate, $name) {
        $coord_present = $this->do_check(
            ($coordinate instanceof Coordinate),
            "$name not present or of incorrect class");

        // call the coordinates own validity check and add any messages
        if($coord_present && !$coordinate->is_valid()) {
            foreach($coordinate->messages as $msg => $val) {
                $this->messages["$name: $msg"] = true;
            }
        }
    }

    // convenience function to debug log any messages indicating invalidity
    public function debug_log_messages() {
        foreach($this->messages as $msg => $bool) {
            $class = get_class($this);
            debug_log("-> invalid $class: " . $msg);
        }
    }
}

?>