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

        if(empty($result)) {
            return self::BAD_ID;
        } else {
            return $result;
        }
    }

    /**
     * Checks if an id is present in the provided table.
     * NOTE: Since this is used only for internal functionality, the table name
     * is NOT sanitized.
     *
     * @return bool true if  result was found else false.
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

    public static function get($select_sql, $where = array()) {
        $sql = $select_sql . " ";
        $sql .= self::where_clause($where);

        global $wpdb;
        $query = self::prepare($sql);
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

    public static function update($table_name, $id, $values) {
        global $wpdb;
        $result = $wpdb->update($table_name, $values, array('id' => $id));
        if($result == false) {
            debug_log("DB: Error updating ${table_name}: ${id}.");
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
     * @return int|false The number of rows updated, or false on error.
     */
    public static function delete($table_name, $id) {
        global $wpdb;
        $result = $wpdb->delete($table_name, array('id' => $id));
        if($result == false) {
            debug_log("DB: Error deleting ${type} with id: ${id}");
        } else if ($result != 1) {
            debug_log("DB: Wrong row count on delete: $result (${type}, ${id})");
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

    public static function where_clause($where_conditions) {
        $i = count($where_conditions);

        if($i == 0) {
            return "";
        }

        $clause = "WHERE ";
        $args = array();
        foreach($where_conditions as $key => $value) {
            $placeholder = "";
            if(is_int($value)) {
                $placeholder = "%d";
            } else if(is_string($value)) {
                $placeholder = "%s";
            } else if(is_float($value)) {
                $placeholder ="%f";
            } else {
                throw new \Exception("DB: Bad value in WHERE of unknown type.");
            }

            $clause .= "$key = $placeholder";
            $args[] = $value;
            $i -= 1;
            if($i != 0) {
                $clause .= " AND ";
            }
        }
        return self::prepare($clause, $args);
    }

}

class DB_Exception extends \Exception {}

// does not extend DB_Exception to not be easily caught
class TransactionException extends \Exception {}

?>