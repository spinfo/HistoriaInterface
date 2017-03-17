<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/abstract_model.php');
require_once(dirname(__FILE__) . '/tours.php');
require_once(dirname(__FILE__) . '/places.php');

class Mapstop extends AbstractModel {

    public $tour_id = -1;

    public $place_id = -1;

    public $name = "";

    public $description = "";

    public $post_ids = array(-1);

    protected function do_validity_check() {
        $this->do_check(Tours::instance()->valid_id($this->tour_id),
            'tour_id invalid');

        $this->do_check(Places::instance()->valid_id($this->place_id),
            'place_id invalid');

        $this->do_check(!empty($this->name), 'name is empty');
        $this->do_check(!empty($this->description), 'description is empty');

        // post_ids may be empty, else it should only contain valid id's
        foreach($this->post_ids as $post_id) {
            $valid = get_post($post_id);
            $this->do_check($valid, "post_id invalid");
        }
    }
}

?>