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
    public $mapstops = [];
    public $mapstop_ids = [];
    public $coordinates = [];
    public $coordinate_ids = [];

    protected function do_validity_check() {
        // todo
    }

}
