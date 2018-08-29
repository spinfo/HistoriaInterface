<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/abstract_model.php');

class Coordinate extends AbstractModel {

    const TYPES = array(
        'map' => 'map',
        'scene' => 'scene',
    );

    public $lat = 0.0;

    public $lon = 0.0;

    public $reference = "map";

    protected function do_validity_check() {
        $this->do_check(!is_null($this->lat), 'latitude is null');
        $this->do_check(!is_null($this->lon), 'longitude is null');

        if ($this->reference === self::TYPES['map']) {
            $this->do_check($this->has_valid_latitude(),
                "latitude not between -90 and 90: '$this->lat'");

            $this->do_check($this->has_valid_longitude(),
                "longitude not between -180 and 180: '$this->lon'");
        }
    }

    public function has_valid_latitude() {
        return (is_float($this->lat)) && ($this->lat >= -90.0) && ($this->lat <= 90.0);
    }

    public function has_valid_longitude() {
        return (is_float($this->lat)) && ($this->lon >= -180.0) && ($this->lon <= 180.0);
    }
}

?>