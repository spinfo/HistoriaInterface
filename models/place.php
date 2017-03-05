<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/abstract_model.php');
require_once(dirname(__FILE__) . '/coordinate.php');

class Place extends AbstractModel {

    public $user_id = -1;

    public $area_id = -1;

    public $coordinate_id = -1;

    public $coordinate; // Coordinate object

    public $name = "";

    public function is_valid() {
        $this->do_check(($this->user_id > 0), 'user_id <= 0');
        $this->do_check(($this->area_id > 0), 'area_id <= 0');
        $this->do_check(($this->coordinate_id > 0), 'coordinate_id <= 0');

        $coord_present = $this->do_check(
            ($this->coordinate instanceof Coordinate),
            'coordinate not present or of incorrect class');
        if($coord_present && !$this->coordinate->is_valid()) {
            foreach($this->coordinate->messages as $key => $val) {
                $this->messages["coordinate: $key"] = true;
            }
        }

        $this->do_check(!empty($this->name), 'name is empty');

        return empty($this->messages);
    }
}

?>