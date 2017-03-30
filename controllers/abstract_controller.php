<?php
namespace SmartHistoryTourManager;

require_once( dirname(__FILE__) . '/../view_helper.php');

/**
 * A class to wrap methods used by all controllers.
 */
abstract class AbstractController {

    protected static function wrap_in_page_view($content_view) {
        if(is_null($content_view)) {
            $content_view = new View(ViewHelper::empty_view(), null);
            debug_log('Trying to wrap empty view in page.');
        }

        return new View(ViewHelper::page_wrapper_view(), array(
            'content' => $content_view,
            'message_service' => MessageService::instance(),
        ));
    }

    protected static function create_access_denied_view() {
        MessageService::instance()->add_error(
            "Sie haben nicht die erforderlichen Berechtigungen für diese Aktion.");
        self::status_header(403);
        return self::error_view();
    }

    protected static function create_not_found_view($error_msg) {
        MessageService::instance()->add_error($error_msg);
        self::status_header(404);
        return self::error_view();
    }

    protected static function create_bad_request_view($error_msg) {
        MessageService::instance()->add_error($error_msg);
        self::status_header(400);
        return self::error_view();
    }

    protected static function create_internal_error_view($msg, $status = 500) {
        MessageService::instance()->add_error($msg);
        self::status_header($status);
        return self::error_view();
    }

    protected static function create_view_with_exception($e, $status = 500) {
        return self::create_internal_error_view($e->getMessage(), $status);
    }

    protected static function status_header($status_code, $description = '') {
        // just pass to the wordpress-function
        status_header($status_code, $description);
    }

    // redirects based on the url params passed setting the required status
    // makes sure that messages intended for the user reach the page in question
    public static function redirect($params, $status = 302) {
        $url = 'admin.php?' . $params;

        if(!session_id()) {
            session_start();
        }
        if(isset($_SESSION['shtm_messages'])) {
            error_log(
                "Previous messages in session. This should never happen.");
        }
        $_SESSION['shtm_messages'] = MessageService::instance()->messages;

        wp_redirect($url, $status);
        exit;
    }

    /**
     * Filter an input array based on another array supplying valid keys and
     * example values with the right class for the result.
     *
     * Always return null if any of the keys in the accepted array is missing
     * and report that error to the message service
     *
     * @param array $accepted   An array with a whitelist of keys and example
     *                          values of the correct type.
     * @param array $input      The input params to parse (e.g. $_POST, $_GET)
     *
     * @return  array|null  An array of all params found or null on error
     */
    protected static function filter_params($accepted, $input) {
        $result = array();

        foreach($accepted as $key => $value) {
            // if the other array does not have the key, there is no need for
            // further checks
            if(!isset($input[$key])) {
                MessageService::instance()->add_error(
                    "Missing value for: '$key'");
                return null;
            }
            // recurse if array is found propagate null result upwards
            if(is_array($value) && is_array($input[$key])) {
                $sub_result = self::filter_params($value, $input[$key]);
                if(is_null($sub_result)) {
                    return null;
                } else {
                    $result[$key] = $sub_result;
                }
            }
            // for other types do a conversion
            else if(is_string($value)) {
                $result[$key] = strval($input[$key]);
            }
            else if(is_float($value)) {
                $result[$key] = floatval($input[$key]);
            }
            else if(is_int($value)) {
                $result[$key] = intval($input[$key]);
            }
        }

        return $result;
    }

    // convenience function to create a view specifically to show errors on
    private static function error_view() {
        return new View(ViewHelper::empty_view(), null);
    }

}

?>