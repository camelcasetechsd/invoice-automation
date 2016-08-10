<?php
namespace InvoiceAutomation\Lib;

class Logger {
    /**
     *
     * @var array messages logged ,default is empty array
     */
    public static $logMessages = array();
    
    /**
     * Log message in favorable output type(s)
     * @access private
     * @param string $message
     */
    public static function log($message) {
        self::$logMessages[] = $message;
        // display log message in console Standard error stream
        fwrite(STDERR, $message . PHP_EOL);
    }
}
