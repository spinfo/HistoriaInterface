<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/abstract_model.php');

class Coordinate extends AbstractModel {

    public $lat = 0.0;

    public $lon = 0.0;

    protected function do_validity_check() {
        $this->do_check(!is_null($this->lat), 'latitude is null');
        $this->do_check(!is_null($this->lon), 'longitude is null');

        $condition = ($this->lat >= -90.0) && ($this->lat <= 90.0);
        $this->do_check($condition,
            "latitude not between -90 and 90: '$this->lat'");

        $condition = ($this->lon >= -180.0) && ($this->lon <= 180.0);
        $this->do_check($condition,
            "longitude not between -180 and 180: '$this->lon'");
    }
}

?>