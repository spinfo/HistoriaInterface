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

    protected function do_validity_check() {
        $this->do_check(($this->user_id > 0), 'user_id <= 0');
        $this->do_check(($this->area_id > 0), 'area_id <= 0');
        $this->do_check(($this->coordinate_id > 0), 'coordinate_id <= 0');

        $this->check_coordinate($this->coordinate, 'coordinate');

        $this->do_check(!empty($this->name), 'name is empty');
    }
}

?>