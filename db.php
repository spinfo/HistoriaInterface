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
        $result = $wpdb->get_results($sql);

        if(empty($result) || !isset($result[0]->id)) {
            return self::BAD_ID;
        } else {
            return intval($result[0]->id);
        }
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
        $result = $wpdb->get_results($sql);

        if(empty($result) || !isset($result[0]->id)) {
            return self::BAD_ID;
        } else {
            return intval($result[0]->id);
        }
    }

    /**
     * Checks if an id is present in the provided table.
     * NOTE: Since this is used only for internal functionality, the table name
     * is NOT sanitized.
     *
     * @return bool true if result was found else false.
     */
    public static function valid_id($table, $id) {
        $id = intval($id);
        if($id <= 0) {
            return false;
        }

        $sql = "SELECT 1 FROM $table";
        $result = self::get($sql, array('id' => $id));

        if(empty($result)) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Count rows in the table where the conditions match.
     * NOTE: Since this is used only for internal functionality, the table name
     * is NOT sanitized (but the where conditions are).
     *
     * @return bool|int The count's result on success or false
     */
    public static function count($table_name, $where = null) {
        $sql = "SELECT COUNT(*) AS count FROM $table_name";
        if(!empty($where)) {
            $sql .= ' ' . self::where_clause($where);
        }
        $query = self::prepare($sql, array($table_name));

        global $wpdb;
        $result = $wpdb->get_results($query);
        if(!empty($result) && isset($result[0]) && isset($result[0]->count)) {
            return intval($result[0]->count);
        } else {
            return false;
        }
    }

    // Method to retrieve multiple objects, expects a sanitized query as input.
    private static function _list($query) {
        global $wpdb;
        $result = $wpdb->get_results($query);

        if(empty($result)) {
            debug_log("DB: Could not retrieve list with: $query");
        }
        return $result;
    }

    /**
     * Returns a list of results from the database using in the conditions of
     * the where clause and setting offset and limit.
     *
     * @param string    $select_sql A string starting the sql query with
     * @param array     $where      An array of conditions, e.g. (user_id => 3)
     * @param int       $offset     Offset in the table, default: 0
     * @param int       $limit      Limit of objects retrieved, default:
     *                                  PHP_INT_MAX
     *
     * @return array    The result of the query as an associative array
     */
    public static function list($select_sql, $where, $offset = 0,
        $limit = PHP_INT_MAX)
    {
        $sql = $select_sql . " ";
        $sql .= self::where_clause($where);
        $sql .= " LIMIT %d, %d";

        $query = self::prepare($sql, array($offset, $limit));

        return self::_list($query);
    }

    /**
     * Returns a list of results from the database using in the conditions of
     * the where clause and setting offset and limit.
     *
     * @param string    $query  The query with sprintf-like placeholders.
     * @param array     $args   The values to replace and escape in the query.
     *
     * @return array    The result of the query as an associative array.
     */
    public static function list_by_query($query, $args = array()) {
        return self::_list(self::prepare($query, $args));
    }

    // Method to retrieve a single object, expects a sanitized query as input.
    private static function _get($query) {
        global $wpdb;
        $result = $wpdb->get_results($query);

        if(empty($result)) {
            debug_log("DB: Could not retrieve row with: $query");
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
    public static function get_by_query($query, $args = array()) {
        return self::_get(self::prepare($query, $args));
    }

    /**
     * Update a single table row at the specified id, using values.
     *
     * @param string    $table_name The table to update.
     * @param int       $id         Row's id to update.
     * @param array     $values     The values to update, e.g. ['name' => 'str']
     *
     * @return bool     Whether the update was successful or not.
     */
    public static function update($table_name, $id, $values) {
        global $wpdb;
        $result = $wpdb->update($table_name, $values, array('id' => $id));

        if($result == 1) {
            return $result;
        } else if($result == 0) {
            $msg = "DB: Updating ${table_name} for id: '${id}' had no effect.";
            debug_log($msg);
        } else if($result != 1) {
            $msg = "DB: Error updating ${table_name} for id: '${id}'";
            $msg .= " (affected rows: $result)";
            debug_log($msg);
            return false;
        }
        return true;
    }

    /**
     * Replaces all placeholders in sql by the supplied values, then runs the
     * query.
     *
     * (This is a small wrapper around wpdb->query(), adds prepartion step.)
     *
     * @return int|false Number of rows affected or false on error
     */
    public static function query($sql, $values) {
        global $wpdb;

        $query = self::prepare($sql, $values);

        return $wpdb->query($query);;
    }

    /**
     * @return int The id of the new obj of DB::BAD_ID on failure.
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
        if($result === false) {
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

    /**
     * Start a transaction that can later be committed or rolled back.
     *
     * @throws  TransactionException    If a transaction is already running.
     * @return  null
     */
    public static function start_transaction() {
        if(self::$transaction_running) {
            throw new TransactionException("Transaction already running.");
        }
        global $wpdb;
        $result = $wpdb->query("START TRANSACTION");
        self::$transaction_running = true;
    }

    /**
     * Commit the current transaction.
     *
     * @throws  TransactionException    If no transaction is running.
     * @return  null
     */
    public static function commit_transaction() {
        if(!self::$transaction_running) {
            throw new TransactionException("No transaction to commit.");
        }
        global $wpdb;
        $result = $wpdb->query("COMMIT");
        self::$transaction_running = false;
    }

    /**
     * Roll back the current transaction.
     *
     * @throws  TransactionException    If no transaction is running.
     * @return  null
     */
    public static function rollback_transaction() {
        if(!self::$transaction_running) {
            throw new TransactionException("No transaction to rollback.");
        }
        global $wpdb;
        $wpdb->query("ROLLBACK");
        // TODO: Check if there is a way to check wpdbs status on a transaction
        self::$transaction_running = false;
    }

    /**
     * Return the table name with all necessary prefixes prepended.
     *
     * @param string    $type   The table name to prefix, e.g. 'places'
     *
     * @return string   The table name with prefixes.
     */
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