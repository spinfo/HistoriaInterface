<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/db.php');
require_once(dirname(__FILE__) . '/models/areas.php');

/**
 * This is a wrapper around wordpress' current user (a WP_User object). It
 * determines access and update rights based on wordpress roles and cpabilities.
 */
class UserService {

    // the key used to set the current users chosen area in the wordpress user
    // settings
    const CURRENT_AREA_KEY = "shtm_current_area";

    // a WP_User object or 0 if no user is logged in, cf. wp_get_current_user()
    private $user;

    // the wp-roles that are interesting for us
    private static $admin_role = 'administrator';
    private static $contributor_role = 'contributor';

    private function __construct() {
        $this->user = wp_get_current_user();
    }

    public static function instance() {
        static $instance = null;
        if ($instance === null) {
            $instance = new self();
        }
        return $instance;
    }

    /**
     * Returns access conditions for the current user. This answers the question
     * "Which values must an object have to be readable by the current user?"
     * The result is a set of key => value pairs that can be used in a database
     * query.
     *
     * On a user without relevant roles the result array will contain the
     * key/value pair true => false which can be inserted in a where clause to
     * prevent the return of any results (WHERE true = false).
     *
     * @return array
     */
    public function access_conditions() {
        $conditions = array();

        if($this->is_admin()) {
            // do nothing
        } else if ($this->is_contributor()) {
            $conditions['user_id'] = $this->user_id();
        } else {
            $conditions['true'] = false;
        }

        return $conditions;
    }

    public function is_contributor($id = null) {
        return in_array(self::$contributor_role, $this->get_roles($id));
    }

    public function is_admin($id = null) {
        return in_array(self::$admin_role, $this->get_roles($id));
    }

    public function user_may_edit_place($place) {
        return ($this->is_admin() || ($this->user_id() == $place->id));
    }

    public function is_logged_in() {
        return !empty($this->user);
    }

    /**
     * Sets the area for the current user. (Uses the wordpress user settings.)
     *
     * @return bool     To indicate success/failure
     */
    public function set_current_area_id($id) {
        if(!is_int($id)) {
            throw new UserServiceException(
                "Area id must be present and int value, is: $id.");
            return false;
        }
        $result = false;
        if(!empty($this->user)) {
            $result = set_user_setting(self::CURRENT_AREA_KEY, strval($id));
            if(empty($result)) {
                throw new UserServiceException(
                    "Failed to set current area for user.");
                $result = false;
            }
        }
        return $result;
    }

    /**
     * Gets the current area id for the current user.
     * (Uses the wordpress user settings)
     *
     * @return int    The area id if successful, else DB::BAD_ID
     */
    public function get_current_area_id() {
        $id = get_user_setting(self::CURRENT_AREA_KEY, DB::BAD_ID);
        if(!is_numeric($id)) {
            throw new UserServiceException(
                "Bad value in setting: current_area_id.");
        }
        if($id == DB::BAD_ID || !Areas::instance()->valid_id($id)) {
            $id = Areas::instance()->first_id();
            if($id == DB::BAD_ID) {
                throw new UserServiceException("Cannot set default area id.");
            } else {
                $this->set_current_area_id($id);
            }
        }
        return intval($id);
    }

    /**
     * Returns the id of the currently logged in user or DB::BAD_ID if no user
     * is logged in.
     */
    public function user_id() {
        if(empty($this->user)) {
            return DB::BAD_ID;
        }
        return intval($this->user->ID);
    }

    private function get_roles($id = null) {
        $roles = array();
        if (isset($id)) {
            $user = get_userdata($id);
            if ($user) {
                $roles = $user->roles;
            }
        } else {
            $roles = $this->user->roles;
        }
        return $roles;
    }

}

class UserServiceException extends \Exception {}

?>