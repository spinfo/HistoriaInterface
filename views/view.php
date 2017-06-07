<?php
namespace SmartHistoryTourManager;

require_once( dirname(__FILE__) . '/../route_params.php');
require_once( dirname(__FILE__) . '/../view_helper.php');
require_once( dirname(__FILE__) . '/../user_service.php');
require_once( dirname(__FILE__) . '/../post_service.php');

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
        // every view gets a route params object, a view helper and the
        // user service
        $this->route_params = new RouteParams();
        $this->view_helper = new ViewHelper();
        $this->user_service = UserService::instance();
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

    public function coord_format($float) {
        return sprintf("%.6f", floatval($float));
    }

    public function datetime_format($datetime, $format = null) {
        if(empty($datetime)) {
            return "";
        } else {
            if(empty($format)) {
                $format = 'd.m.Y H:i:s';
            }
            return $datetime->format($format);
        }
    }

    /**
     * Trims text to a certain length then adds ellipses if desired
     *
     * @param string $input text to trim
     * @param int $length in characters to trim to
     * @param bool $ellipses if ellipses (...) are to be added
     * @param bool $strip_html if html tags are to be stripped
     * @return string
     */
    public function trim_text($input, $length, $ellipses = true, $strip_html = true) {
        //strip tags, if desired
        if ($strip_html) {
            $input = strip_tags($input);
        }
        //no need to trim, already shorter than trim length
        if (strlen($input) <= $length) {
            return $input;
        }
        //find last space within length
        $last_space = strrpos(substr($input, 0, $length), ' ');
        $trimmed_text = substr($input, 0, $last_space);
        //add ellipses (...)
        if ($ellipses) {
            $trimmed_text .= '...';
        }
        return $trimmed_text;
    }

    public function print_yaml($value) {
        if(is_string($value)) {
            echo "\"" . addslashes($value) . "\"" . PHP_EOL;
        } else {
            echo $value . PHP_EOL;
        }
    }

    /**
     * Prints a yaml block string indenting by the given level. All user
     * supplied string included in the yaml output should use this to be printed
     * as special characters might break parsing in the client.
     */
    public function print_yaml_block($str, $indicator, $indent_level) {
        // if there are no lines we just print an empty string
        $lines = preg_split('/$\R?^/m', $str);
        if(empty($lines)) {
            echo '""';
            return;
        }
        // print the post in YAML's literal style using the given indent_level
        $indent = "";
        for($i = 0; $i < $indent_level; $i++) $indent .= "  ";
        echo $indicator . PHP_EOL;
        foreach ($lines as $line) {
            echo $indent . trim($line) . PHP_EOL;
        }
    }

    public function print_post_to_yaml($post, $indent_level) {
        $content = PostService::get_content_to_publish($post);
        $this->print_yaml_block($content, '|', $indent_level);
    }

    /**
     * A function to return the result of rendering the view as a string.
     *
     * @return string   The result of rendering the view.
     */
    public function get_include_contents() {
        ob_start();
        include $this->file;
        return ob_get_clean();
    }

    /**
     * Provides an alias for the PostService method as we can't require it from
     * the view directly.
     */
    public function get_linked_lexicon_posts($posts, $recurse = false) {
        return PostService::get_linked_lexicon_posts($posts, $recurse);
    }

    public function get_lexicon_articles_link() {
        $url = get_admin_url(null, "edit.php");
        $url = add_query_arg('category_name', PostService::LEXICON_CATEGORY, $url);
        return esc_url($url);
    }

    public function get_post_media($post) {
        return PostService::get_post_media($post);
    }

    public function print_lexicon_post_title($post, $indent_level) {
        $this->print_yaml_block(PostService::get_lexicon_post_title($post),
            '>-', 3);
    }
}

?>