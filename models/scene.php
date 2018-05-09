<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/abstract_model.php');

class Scene extends AbstractModel {

    public $id;
    public $tour_id;
    public $name = "";
    public $title = "";
    public $description = "";
    public $excerpt = "";
    public $path = "";

    protected function do_validity_check() {
        // todo
    }

}
