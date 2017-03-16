<?php
namespace SmartHistoryTourManager;

require_once( dirname(__FILE__) . '/../route_params.php');

/**
 * A simple solution to render html templates.
 *
 * A view is constructed with a file name and and array of arguments.
 * When render is called the file is included and the args are available in the
 * template via $this->arg
 *
 * EDIT/TODO:
 *  The following blog post talks about a vulnerability in this plan:
 *  http://chadminick.com/articles/simple-php-template-engine.html
 */
class View {
    private $args;
    private $file;

    // each templates needs a file and (optionally) an associative array of
    // data to be displayed
    public function __construct($file, $args = array()) {
        $this->file = $file;
        $this->args = $args;
        // every view gets a route params object
        $this->route_params = RouteParams();
    }

    // this allows templates to call $this->arg instead of $this->args['arg']
    public function __get($name) {
        return $this->args[$name];
    }

    public function render() {
        include $this->file;
    }
}

?>