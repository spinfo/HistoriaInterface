<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/abstract_model.php');
require_once(dirname(__FILE__) . '/coordinates.php');
require_once(dirname(__FILE__) . '/coordinate.php');
require_once(dirname(__FILE__) . '/../db.php');

class Area extends AbstractModel {

    public $name = "";

    public $coordinate1_id;

    public $coordinate2_id;

    public $coordinate1; // Coordinate object

    public $coordinate2; // Coordinate object

    protected function do_validity_check() {
        $this->do_check(Coordinates::instance()->valid_id($this->coordinate1_id),
            'coordinate1_id invalid');
        $this->do_check(Coordinates::instance()->valid_id($this->coordinate2_id),
            'coordinate2_id invalid');

        $this->check_coordinate($this->coordinate1, 'coordinate1');
        $this->check_coordinate($this->coordinate2, 'coordinate2');

        $this->do_check(!empty($this->name), 'name is empty');
    }

}

?>