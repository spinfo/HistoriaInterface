<?php
namespace SmartHistoryTourManager;

require_once( dirname(__FILE__) . '/../route_params.php');
require_once( dirname(__FILE__) . '/../view_helper.php');

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
        // every view gets a route params object and a view helper
        $this->route_params = new RouteParams();
        $this->view_helper = new ViewHelper();
    }

    // this allows templates to call $this->arg instead of $this->args['arg']
    public function __get($name) {
        return $this->args[$name];
    }

    public function render() {
        include $this->file;
    }

    /**
     * This allows the view to include a child view.
     *
     * @param str   $file   The file to use for the view.
     * @param array $args   The variables available in the view. The parent
     *                      views params will be available in the child as well
     *                      but might be overwritten by the args given here.
     */
    public function include($file, $args = null) {
        if(empty($args)) {
            $available_args = $this->args;
        } else {
            $available_args = array_merge($this->args, $args);
        }

        $included = new View($file, $available_args);
        return $included->render();
    }

    public function datetime_format($datetime) {
        if(empty($datetime)) {
            return "";
        } else {
            return $datetime->format('d.m.Y H:i:s');
        }
    }
}

?>