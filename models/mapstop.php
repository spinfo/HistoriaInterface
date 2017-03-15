<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/abstract_model.php');

class Mapstop extends AbstractModel {

    public $tour_id = -1;

    public $place_id = -1;

    public $name = "";

    public $description = "";

    public $post_ids = array(-1);

    protected function do_validity_check() {
        $this->do_check($this->tour_id > 0, 'tour_id <= 0');
        $this->do_check($this->place_id > 0, 'place_id <= 0');

        $this->do_check(!empty($this->name), 'name is empty');
        $this->do_check(!empty($this->description), 'description is empty');

        // post_ids may be empty, but should only contain valid id's
        foreach($this->post_ids as $post_id) {
            $this->do_check($post_id > 0, "post_id <= 0");
        }
    }
}

?>