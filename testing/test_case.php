<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/test_helper.php');

class TestCase {

    public $name;

    public $tests_passed;
    public $tests_failed;

    public $helper;

    public function __construct() {
        $this->helper = new TestHelper();
    }

    // may be overwritten by child to implement different logging
    protected function log($msg = '') {
        echo $msg . "\n";
    }

    protected function log_ok($msg = '') {
        $this->log('OK: ' . $msg);
    }

    protected function log_error($msg = '') {
        $this->log('ERROR: ' . $msg);
    }

    public function note_pass($msg) {
        $this->tests_passed += 1;
        $this->log_ok($msg);
    }

    public function note_fail($msg) {
        $this->tests_failed += 1;
        $this->log_error($msg);
    }

    public function report() {
        $total = $this->tests_passed + $this->tests_failed;
        echo "Passed $this->tests_passed/$total ($this->name)\n";
    }

    public function assert($condition, $message) {
        if($condition) {
            $this->note_pass($message);
        } else {
            $this->note_fail($message);
        }
        return $condition;
    }

}

