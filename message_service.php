<?php
namespace SmartHistoryTourManager;

/**
 * Represents a message that is shown to the user on the top of the page.
 */
class Message {

    public $type;

    public $text;

    public function __construct($text, $type = MessageService::INFO) {
        $this->type = $type;
        $this->text = $text;
    }

    public function get_label() {
        return MessageService::message_label($this->type);
    }

    public function get_prefix() {
        return MessageService::message_prefix($this->type);
    }

}

/**
 * This singleton class keeps a bunch of messages, that can be added from
 * anywhere in the application. These messages are then automatically shown to
 * the user on page rendering.
 */
class MessageService {

    // some constants to distinguish between message types
    const SUCCESS = 0;
    const INFO = 10;
    const WARNING = 20;
    const ERROR = 30;

    public $messages;

    private function __construct() {
        $this->messages = array();
    }

    public function add_success($message) {
        $this->messages[] = new Message($message, self::SUCCESS);
    }

    public function add_info($message) {
        $this->messages[] = new Message($message, self::INFO);
    }

    public function add_warning($message) {
        $this->messages[] = new Message($message, self::WARNING);
    }

    public function add_error($message) {
        $this->messages[] = new Message($message, self::ERROR);
    }

    /**
     * If the model has messages attacthed that indicate invalidity these are
     * added as messages of the specified type.
     * NOTE: This does not perform validity checking itself (left to the user)
     */
    public function add_model_messages($model, $type = self::WARNING) {
        if(!empty($model->messages)) {
            foreach ($model->messages as $msg => $bool) {
                $this->messages[] = new Message($msg, $type);
            }
        }
    }

    public static function message_label($msg_type) {
        switch($msg_type) {
            case self::SUCCESS:
                return 'success';
            case self::INFO:
                return 'info';
            case self::WARNING:
                return 'warning';
            case self::ERROR:
                return 'error';
        }
    }

    public static function message_prefix($msg_type) {
        switch($msg_type) {
            case self::SUCCESS:
                return 'OK';
            case self::INFO:
                return 'Info';
            case self::WARNING:
                return 'Warnung';
            case self::ERROR:
                return 'Fehler';
        }
    }

    public static function instance() {
        static $instance = null;
        if($instance === null) {
            $instance = new self();
        }
        return $instance;
    }

}



?>