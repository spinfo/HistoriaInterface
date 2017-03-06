<?php
namespace SmartHistoryTourManager;

/**
 * This provides sanitizing of sql queris as well as their execution.
 * (The class should care about the form but not about the content of queries.)
 */
class DB {

    private static $prefix = 'shtm_';

    const BAD_ID = -1;

    public static function list($select_sql, $where, $offset, $limit) {
        $sql = $select_sql . " ";
        $sql .= self::where_clause($where);
        $sql .= " LIMIT %d, %d";

        global $wpdb;
        $query = $wpdb->prepare($sql, $offset, $limit);
        $result = $wpdb->get_results($query);

        if(empty($result)) {
            error_log("DB: Could not retrieve list with: $query");
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
            error_log("DB: Could not retrieve object with: $query");
            return null;
        } else if(count($result) != 1) {
            $count = count($result);
            error_log("DB: Bad result count: $count for: $query).");
            return null;
        }

        return $result[0];
    }

    public static function update($table_name, $id, $values) {
        global $wpdb;
        $result = $wpdb->update($table_name, $values, array('id' => $id));
        if($result == false) {
            error_log("DB: Error updating ${table_name}: ${id}.");
        }
    }

    /**
     * @return int The id of the new obj.
     */
    public static function insert($table_name, $values) {
        global $wpdb;
        $result = $wpdb->insert($table_name, $values);
        if($result == false) {
            error_log("DB: Error inserting into ${table_name}.");
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
            error_log("DB: Error deleting ${type} with id: ${id}");
        } else if ($result != 1) {
            error_log("DB: Wrong row count on delete: $result (${type}, ${id})");
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


?>