<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/abstract_model.php');
require_once(dirname(__FILE__) . '/areas.php');
require_once(dirname(__FILE__) . '/tours.php');
require_once(dirname(__FILE__) . '/../user_service.php');

/**
 * A TourRecord represents the published version of a tour. It is a record of
 * the tour's state, when it was published and might differ considerably from
 * the current state of the tour.
 */
class TourRecord extends AbstractModel {

    // the tour that this represents the published version of
    public $tour_id;

    // the area of the tour published
    public $area_id;

    // the user, that published this tour
    public $user_id;

    // the name the tour is published under (just the tour's name, but
    // as that might change, the name is recorded here as well)
    public $name;

    // whether the record is active, i.e. this version of the tour should be
    // downloadable by the client (and no other version of it)
    // a boolean
    public $is_active;

    // the tour's represention readable by the client (a yaml report)
    public $content;

    // a download url to the tour's compressed media
    public $media_url;

    // how big the tour download would be, an int representing size in bytes
    public $download_size;

    // a timestamp used to identify this tour record from other versions
    // NOTE: We could use a timstamp from the created_at field, but this value
    //      needs to exist before the record is persisted to the database
    // NOTE: Duplicate combinations of tour_id and published_at are not possible
    //      due to a unique index in the database
    public $published_at;

    public function __construct() {
        // use wordpress time to align this with the settings
        $this->published_at = current_time('timestamp');
    }

    protected function do_validity_check() {
        $this->do_check(UserService::instance()->get_user($this->user_id),
            'user_id invalid');
        $this->do_check(Areas::instance()->valid_id($this->area_id),
            'area_id invalid');
        $this->do_check(Tours::instance()->valid_id($this->tour_id),
            'tour_id invalid');

        $this->do_check(!empty($this->name), 'name is empty');
        $this->do_check(is_bool($this->is_active), 'is_active not a bool');
        $this->do_check(!empty($this->content), 'content is empty');
    }

}

?>