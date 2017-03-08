<?php
namespace SmartHistoryTourManager;

const DEBUG_LOG_FILE = '/tmp/wp-shtm-debug.log';

function log_to_file($msg, $path) {
    file_put_contents($path, $msg, FILE_APPEND | LOCK_EX);
}

function debug_log($msg, $prefix = null) {
    if(is_null($prefix)) {
        $prefix = "DEBUG";
    }

    $msg = "$prefix: $msg" . PHP_EOL;

    if(SHTM_ENV_TEST) {
        echo $msg;
    } else {
        log_to_file($msg, DEBUG_LOG_FILE);
    }
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