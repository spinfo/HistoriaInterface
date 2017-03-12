<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/abstract_model.php');
require_once(dirname(__FILE__) . '/coordinate.php');

class Area extends AbstractModel {

    public $name = "";

    public $coordinate1_id;

    public $coordinate2_id;

    public $coordinate1; // Coordinate object

    public $coordinate2; // Coordinate object

    public function is_valid() {
        $this->do_check(($this->coordinate1_id > 0), 'coordinate1_id <= 0');
        $this->do_check(($this->coordinate2_id > 0), 'coordinate2_id <= 0');

        $this->check_coordinate($this->coordinate1, 'coordinate1');
        $this->check_coordinate($this->coordinate2, 'coordinate2');

        $this->do_check(!empty($this->name), 'name is empty');

        return empty($this->messages);
    }

}

?>