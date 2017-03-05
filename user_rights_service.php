<?php
namespace SmartHistoryTourManager;

/**
 * This is a wrapper around wordpress' current user (a WP_User object). It
 * determines access and update rights based on wordpress roles and cpabilities.
 */
class UserRightsService {

    // a WP_User object
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

    public function user_id() {
        return $this->user->ID;
    }

}

?>