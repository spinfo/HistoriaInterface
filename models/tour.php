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

    // set the start date by a datetime object
    public function set_tag_when_start($datetime) {
        if(empty($datetime)) {
            throw new \Exception("Bad datetime given for start date.");
        }
        $this->tag_when_start = $this->julian_date_from_datetime($datetime);
    }

    // get tag_when_start as a datetime object
    public function get_tag_when_start() {
        if(empty($this->tag_when_start)) {
            return null;
        } else {
            return $this->datetime_from_julian_date($this->tag_when_start);
        }
    }

    // set tag_when_end date by a datetime object
    public function set_tag_when_end($datetime) {
        if(empty($datetime)) {
            throw new \Exception("Bad datetime given for end date.");
        }
        $this->tag_when_end = $this->julian_date_from_datetime($datetime);
    }

    // get tag_when_end as a datetime object
    public function get_tag_when_end() {
        if(empty($this->tag_when_end)) {
            return null;
        } else {
            return $this->datetime_from_julian_date($this->tag_when_end);
        }
    }


    protected function do_validity_check() {
        $this->do_check(Areas::instance()->valid_id($this->area_id),
            'area_id invalid');
        $this->do_check(UserService::instance()->get_user($this->user_id),
            'user_id invalid');

        $this->do_check(!empty($this->name), 'name is empty');

        $this->do_check(!is_null($this->intro), 'intro is null');
        $this->do_check(!is_null($this->walk_length), 'walk_length is null');
        $this->do_check(!is_null($this->duration), 'duration is null');
        $this->do_check(!is_null($this->tag_what), 'tag_what is null');
        $this->do_check(!is_null($this->tag_where), 'tag_where is null');
        $this->do_check(!is_null($this->accessibility), 'accessibility null');

        if(!empty($this->coordinates)) {
            foreach($this->coordinates as $c) {
                // check that the coordinates linked are valid
                $this->check_coordinate($c, 'tour track coordinate');

                // check that each coordinate with an id appears in the array
                // of coordinate_ids and that it is a valid id
                if(!is_null($c->id) && $c->id != DB::BAD_ID) {
                    $this->do_check(
                        in_array($c->id, $this->coordinate_ids),
                        "id not in coordinate_ids: $c->id");

                    $this->do_check(Coordinates::instance()->valid_id($c->id),
                        "invalid coordinate id");
                }
            }
        }

        $this->do_check(is_float($this->tag_when_start),
            "no float value as start date");
        $this->do_check($this->tag_when_start >= 0.0, "start date < 0.0");

        if(!is_null($this->tag_when_end)) {
            $this->do_check(is_float($this->tag_when_end),
                "no float value as end date");
            $this->do_check($this->tag_when_start < $this->tag_when_end,
                "start date after end date");
        }
    }

    private function julian_date_from_datetime($d) {
        $julian_day = gregoriantojd(
            $d->format('m'), $d->format('d'), $d->format('Y'));

        // get the day's fraction and correct for half day offset as julian
        // dates start at noon
        $dayfrac = ($d->format('G') / 24) - .5;
        // if($dayfrac < 0) $dayfrac += 1;

        // set the complete fraction of the day
        $frac = $dayfrac + ($d->format('i') + ($d->format('s') / 60)) / 60 / 24;

        $julian_date = $julian_day + $frac;
        return $julian_date;
    }

    private function datetime_from_julian_date($julian_date) {
        $julian_day = floor($julian_date);
        // get the julian date
        list($month, $day, $year) = explode('/', jdtogregorian($julian_day));

        // construct a datetime for the day (julian days begin at noon)
        $str = sprintf("%+05d-%02d-%02d 12:00:00", $year, $month, $day);
        $dt = new \DateTime($str, new \DateTimeZone('UTC'));

        // calculate the fraction of the day in seconds and add it
        $frac = $julian_date - $julian_day;
        $seconds = round($frac * (24 * 60 * 60));
        $interval = new \DateInterval("PT${seconds}S");

        // add the seconds and return the datetime
        $dt->add($interval);
        return $dt;
    }

}





?>