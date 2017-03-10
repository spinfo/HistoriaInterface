<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/logging.php');

/**
 * This provides sanitizing of sql queris as well as their execution.
 * (The class should care about the form but not about the content of queries.)
 */
class DB {

    private static $prefix = 'shtm_';

    private static $transaction_running = false;

    const BAD_ID = -1;

    /**
     * Returns the first id for the table in question.
     * NOTE: Since this is used only for internal functionality, the table name
     * is NOT sanitized.
     *
     * @return int  The id in question or DB::BAD_ID if the table is empty.
     */
    public static function first_id($table) {
        $sql = "SELECT id FROM $table ORDER BY id ASC LIMIT 0,1";

        global $wpdb;
        $result = $wpdb->query($sql);

        return (empty($result)) ? self::BAD_ID : $result;
    }

    /**
     * Returns the last id for the table in question.
     * NOTE: Since this is used only for internal functionality, the table name
     * is NOT sanitized.
     *
     * @return int  The id in question or DB::BAD_ID if the table is empty.
     */
    public static function last_id($table) {
        $sql = "SELECT id FROM $table ORDER BY id DESC LIMIT 0,1";

        global $wpdb;
        $result = $wpdb->query($sql);

        return (empty($result)) ? self::BAD_ID : $result;
    }

    /**
     * Checks if an id is present in the provided table.
     * NOTE: Since this is used only for internal functionality, the table name
     * is NOT sanitized.
     *
     * @return bool true if result was found else false.
     */
    public static function valid_id($table, $id) {
        $sql = "SELECT 1 FROM $table";
        $result = self::get($sql, array('id' => $id));

        if(empty($result)) {
            return false;
        } else {
            return true;
        }
    }

    public static function list($select_sql, $where, $offset, $limit) {
        $sql = $select_sql . " ";
        $sql .= self::where_clause($where);
        $sql .= " LIMIT %d, %d";

        global $wpdb;
        $query = $wpdb->prepare($sql, $offset, $limit);
        $result = $wpdb->get_results($query);

        if(empty($result)) {
            debug_log("DB: Could not retrieve list with: $query");
        }

        return $result;
    }

    // Method to retrieve a single object, expects a sanitized query as input.
    private static function _get($query) {
        global $wpdb;
        $result = $wpdb->get_results($query);

        if(empty($result)) {
            debug_log("DB: Could not retrieve object with: $query");
            return null;
        } else if(count($result) != 1) {
            $count = count($result);
            debug_log("DB: Bad result count: $count for: $query).");
            return null;
        }
        return $result[0];
    }

    /**
     * Retrieves a single object from the database. Builds a where clause
     * from the specified conditions and appends it to the selection sql to
     * achieve that.
     *
     * @return array|null   The table row as an associative array or null on
     *                      failure.
     *
     * @throws DB_Exception If a value in the where conditions is of an unknown
     *                      tpye (not int, float, string or array)
     */
    public static function get($select_sql, $where = array()) {
        $sql = $select_sql . " ";
        $sql .= self::where_clause($where);

        $query = self::prepare($sql);

        return self::_get($query);
    }

    /**
     * Retrieves a single object from the database. Replaces placeholders in
     * the query with the supplied arguments.
     *
     * @return array|null   The table row as an associative array or null on
     *                      failure.
     */
    public static function get_by_query($query, $args) {
        return self::_get(self::prepare($query, $args));
    }

    public static function update($table_name, $id, $values) {
        global $wpdb;
        $result = $wpdb->update($table_name, $values, array('id' => $id));
        if($result == false) {
            debug_log("DB: Error updating ${table_name} for id: '${id}'.");
        }
        return $result;
    }

    /**
     * @return int The id of the new obj.
     */
    public static function insert($table_name, $values) {
        global $wpdb;
        $result = $wpdb->insert($table_name, $values);
        if($result == false) {
            debug_log("DB: Error inserting into ${table_name}.");
            return self::BAD_ID;
        }
        return $wpdb->insert_id;
    }

    /**
     * @return int|false The number of rows updated (1), or false on error.
     */
    public static function delete_single($table_name, $id) {
        $result = self::delete($table_name, array('id' => $id));
        if ($result != 1) {
            debug_log(
                "DB: Wrong row count on delete: $result ($table_name, $id).");
            return false;
        }
        return $result;
    }

    /**
     * @return int|false The number of rows updated, or false on error.
     */
    public static function delete($table_name, $where) {
        global $wpdb;
        $result = $wpdb->delete($table_name, $where);
        if($result == false) {
            debug_log(
                "DB: Error deleting from $table_name with clause: '$where'.");
        }
        return $result;
    }
    /**
     * A wrapper around wpdb->prepare(). Here just used to insert variables into
     * sql in a sane manner.
     *
     * @param string    $sql
     * @param array     $args
     */
    public static function prepare($sql, $args = array()) {
        global $wpdb;
        return $wpdb->prepare($sql, $args);
    }

    public static function start_transaction() {
        if(self::$transaction_running) {
            throw new TransactionException("Transaction already running.");
        }
        global $wpdb;
        $result = $wpdb->query("START TRANSACTION");
        self::$transaction_running = true;
    }

    public static function commit_transaction() {
        if(!self::$transaction_running) {
            throw new TransactionException("No transaction to commit.");
        }
        global $wpdb;
        $result = $wpdb->query("COMMIT");
        self::$transaction_running = false;
    }

    public static function rollback_transaction() {
        if(!self::$transaction_running) {
            throw new TransactionException("No transaction to rollback.");
        }
        global $wpdb;
        $wpdb->query("ROLLBACK");
        // TODO: Check if there is a way to check wpdbs status on a transaction
        self::$transaction_running = false;
    }

    public static function table_name($type) {
        global $wpdb;
        return $wpdb->prefix . self::$prefix . $type;
    }

    /**
     * Builds a where clause for an sql query using the conditions array. 
     *
     * @param  $where_conditions    An array of conditions, eg.
     *                                  ['id' => 3, 'name' => 'test']
     *                              values must be of type int, float, string or
     *                              a sub array of those.
     *
     * @return string   A full where clause with the secified conditions joined
     *                  by AND, e.g. "id = 3 AND name = 'test'"
     *
     * @throws  DB_Exception    If a value in the conditions is of an unknown
     *                          type (neither int, float, str, array).
     */
    public static function where_clause($where_conditions) {
        // return empty string if there are no condition to build
        if(empty($where_conditions)) {
            return "";
        }

        // equality strings with placeholders for values , e.g. 'id = %d'
        $equals = array();
        // arguments that will be mapped to the placeholders
        $args = array();
        foreach($where_conditions as $key => $value) {
            if(is_array($value)) {
                // treat each value in the array as a single condition
                foreach($value as $value_elem) {
                    $equals[] = self::equals_str($key, $value_elem);
                    $args[] = $value_elem;
                }
            } else {
                // build an equality string from the key value pair
                $equals[] = self::equals_str($key, $value);
                $args[] = $value;

            }
        }
        // glue the equals strings together
        $clause = 'WHERE ' . implode(' AND ', $equals);

        // return the string with placeholders replaced and values sanitized
        return self::prepare($clause, $args);
    }

    // builds an equality condition used in an sql WHERE clause, e.g. 'id = %d'
    // placeholders for int, float and string values are supported
    // throws a DB_Exception if the type of $values is otherwise
    private static function equals_str($key, $value) {
        $placeholder = null;
        if(is_int($value)) {
            $placeholder = "%d";
        } else if(is_string($value)) {
            $placeholder = "%s";
        } else if(is_float($value)) {
            $placeholder ="%f";
        } else {
            throw new DB_Exception("DB: Bad value in WHERE of unknown type.");
        }
        return "$key = $placeholder";
    }

}

class DB_Exception extends \Exception {}

// does not extend DB_Exception to not be easily caught
class TransactionException extends \Exception {}

?>