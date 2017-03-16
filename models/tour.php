<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/abstract_model.php');

class Tour extends AbstractModel {

    public $area_id = -1;

    public $user_id = -1;

    // the tour's track as array or coordinate ids
    public $coordinates = array();
    public $coordinate_ids = array();

    // the tour's mapstops as array or mapstop ids
    public $mapstops = array();
    public $mapstop_ids = array();

    public $name = '';

    public $intro = '';

    // whether the tour is a simple 'tour' or rather a 'round-tour'
    public $type = 'tour';

    // length of the tour in meters
    public $walk_length = 0;

    // duration of the tour in minutes
    public $duration = 0;

    // a single small description of what the tour is about
    public $tag_what = '';

    // a single small description of where the tour is
    public $tag_where = '';

    // the time the tour is about as a duration in julian dates (days since
    // January 1st, 4713 BC with fraction of day). The second value may be
    // empty to indicate that this is an instant rather than a duration.
    public $tag_when_start = 0.0;
    public $tag_when_end = null;

    // A string indicating accessibility conditions
    public $accessibility = '';

    protected function do_validity_check() {
        $this->do_check($this->area_id > 0, 'area_id <= 0');
        $this->do_check($this->user_id > 0, 'area_id <= 0');

        $this->do_check(!empty($this->name), 'name is empty');

        $this->do_check(!is_null($this->intro), 'intro is null');
        $this->do_check(!is_null($this->walk_length), 'walk_length is null');
        $this->do_check(!is_null($this->duration), 'duration is null');
        $this->do_check(!is_null($this->tag_what), 'tag_what is null');
        $this->do_check(!is_null($this->tag_where), 'tag_where is null');
        $this->do_check(!is_null($this->accessibility), 'accessibility is null');

        if(!empty($this->coordinates)) {
            foreach($this->coordinates as $coordinate) {
                // check that the coordinates linked are valid
                $this->check_coordinate($coordinate, 'tour track coordinate');

                // check that each coordinate with an id appears in the array
                // of coordinate_ids
                if(!is_null($coordinate->id) && $coordinate->id != DB::BAD_ID) {
                    $this->do_check(
                        in_array($coordinate->id, $this->coordinate_ids),
                        "coordinate's id not in coordinate_ids: $coordinate->id");
                }
            }
        }

        $this->do_check(is_float($this->tag_when_start),
            "no float value as start date");
        $this->do_check($this->tag_when_start > 0.0, "start date < 0.0");

        if(!is_null($this->tag_when_end)) {
            $this->do_check(is_float($this->tag_when_end),
                "no float value as end date");
            $this->do_check($this->tag_when_start < $this->tag_when_end,
                "start date after end date");
        }
    }

}





?>