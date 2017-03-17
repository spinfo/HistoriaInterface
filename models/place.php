<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/abstract_model.php');
require_once(dirname(__FILE__) . '/coordinate.php');
require_once(dirname(__FILE__) . '/coordinates.php');
require_once(dirname(__FILE__) . '/areas.php');
require_once(dirname(__FILE__) . '/../user_service.php');

class Place extends AbstractModel {

    public $user_id = -1;

    public $area_id = -1;

    public $coordinate_id = -1;

    public $coordinate; // Coordinate object

    public $name = "";

    protected function do_validity_check() {
        $this->do_check(UserService::instance()->get_user($this->user_id),
            'user_id invalid');
        $this->do_check(Areas::instance()->valid_id($this->area_id),
            'area_id invalid');
        $this->do_check(Coordinates::instance()->valid_id($this->coordinate_id),
            'coordinate_id invalid');

        $this->check_coordinate($this->coordinate, 'coordinate');

        $this->do_check(!empty($this->name), 'name is empty');
    }
}

?>