<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/abstract_collection.php');
require_once(dirname(__FILE__) . '/../db.php');

class Tours extends AbstractCollection {

    protected static $instance = null;

    public $table;

    public function __construct() {
        $this->table = DB::table_name('tours');
    }

    protected function db_insert($tour) {}

    protected function db_update($tour) {}

    protected function db_delete($tour) {}

}

?>