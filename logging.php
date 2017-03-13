<?php
namespace SmartHistoryTourManager;

class Logging {

    const DEBUG_LOG_FILE = '/tmp/wp-shtm-debug.log';

    const TO_FILE = 0;
    const TO_STDOUT = 1;

    private static $output = self::TO_FILE;

    public static function set_output($output) {
        self::$output = $output;
    }

    public static function debug_log($msg, $prefix = null) {
        if(is_null($prefix)) {
            $prefix = "DEBUG";
        }

        $msg = "$prefix: $msg" . PHP_EOL;

        if(self::$output == self::TO_STDOUT) {
            echo $msg;
        } else {
            self::log_to_file($msg, self::DEBUG_LOG_FILE);
        }
    }

    private static function log_to_file($msg, $path) {
        file_put_contents($path, $msg, FILE_APPEND | LOCK_EX);
    }

}

// convenience function to call the debug log withot Logging class
function debug_log($msg, $prefix = null) {
    Logging::debug_log($msg, $prefix);
}

// this can be hooked into wordpress' wpdb query to log all db queries
function debug_log_query($query) {
    // remove excessive whitespace and log
    $q = preg_replace(
        array('/^\s+/', '/\s+$/', '/\s+/'), array('', '', ' '), $query);
    debug_log($q, "QUERY");
    // return query unchanged
    return $query;
}

?>