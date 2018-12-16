<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/abstract_model.php');

class Scene extends AbstractModel {

    public $id = -1;
    public $tour_id = -1;
    public $post_id = -1;
    public $name = "";
    public $title = "";
    public $description = "";
    public $excerpt = "";
    public $src = "";
    public $raw = "";
    public $mapstops = [];
    public $mapstop_ids = [];
    public $coordinates = [];
    public $coordinate_ids = [];


    protected function do_validity_check() {
        $this->do_check(Tours::instance()->valid_id($this->tour_id), 'Invalid tour_id');
        $this->do_check($this->id > 0, 'Invalid id');
        $this->do_check(!empty($this->name), 'Scenes has no name');
        $this->do_check(!empty($this->title), 'Scenes has no title');
        //$this->do_check(!empty($this->description), 'Scenes has no description');
        //$this->do_check(!empty($this->excerpt), 'Scenes has no excerpt');

        foreach ($this->mapstops as $mapstop) {
            $mapstop->is_valid();
        }

        foreach ($this->coordinates as $coordinate) {
            $coordinate->is_valid();
        }
    }

}
