<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/abstract_collection.php');
require_once(dirname(__FILE__) . '/../db.php');

class Mapstops extends AbstractCollection {

    protected static $instance = null;

    public $table;

    public $join_posts_table;

    public function __construct() {
        $this->table = DB::table_name('mapstops');
        $this->join_posts_table = DB::table_name('mapstops_to_posts');
    }

    protected function db_insert($mapstop) {}

    protected function db_update($mapstop) {}

    protected function db_delete($mapstop) {}

}

?>