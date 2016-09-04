<?php

namespace InvoiceAutomation;

require 'vendor/autoload.php';
require 'Lib/Connector.php';
require 'Lib/Logger.php';
require 'Lib/Invoice.php';
use InvoiceAutomation\Lib\Connector;
use InvoiceAutomation\Lib\Logger;
use InvoiceAutomation\Lib\Invoice;

class InvoicesRemover
{

    /**
     *
     * @var array command options ,default is empty array
     */
    private static $options = array();

    /**
     * Wrap DB connection and invoices removal
     * @access public
     * @return boolean true if whole delete process is successful or not
     */
    public static function execute()
    {
        Logger::log('Starting deleting...');
        $result = false;
        $connector = new Connector();
        // start DB connection
        $connector->startConnection();
        $connection = $connector->connection;
        // handle delete exceptions
        try {
            // run migrations
            exec("php migration.php");
            // run the delete process
            $deleteResult = self::delete($connection);
            if ($deleteResult === true) {
                Logger::log('Delete attempt successful!');
                $result = true;
            }
            else {
                Logger::log('Delete attempt failed!');
            }
        } catch (Exception $e) {
            $deleteResult = false;
            // log exception message
            Logger::log($e->getMessage());
        }
        $connector->killConnection();
        return $result;
    }

    /**
     * Delete a range of invoices 
     * @access private
     * @param PDO $connection database connection
     * @return boolean true if whole delete process is successful or not
     * @throws \Exception php-zip extension is not enabled!
     */
    private static function delete($connection)
    {
        // handle options check
        // load passed options to command
        self::setOptions();
        $from = self::$options["from"];
        $to = self::$options["to"];
        
        Invoice::delete($connection, $from, $to);
        return true;
    }

    /**
     * Set options after validating it
     * @access private
     * @throws \Exception from is required !
     * @throws \Exception to is required !
     * @throws \Exception from should be numeric !
     * @throws \Exception to should be numeric !
     * @throws \Exception to should be higher than from !
     */
    private static function setOptions()
    {
        // options starting with "--" like "--from"
        $longOptions = array(
            "from:", // Required value
            "to:", // Required value
        );
        $options = getopt(/* $shortopts = */ "", $longOptions);
        if (!array_key_exists("from", $options)) {
            throw new \Exception("from is required !");
        }
        if (!array_key_exists("to", $options)) {
            throw new \Exception("to is required !");
        }
        if (!is_numeric($options["from"])) {
            throw new \Exception("from should be numeric !");
        }
        if (!is_numeric($options["to"])) {
            throw new \Exception("to should be numeric !");
        }
        if ($options["to"] < $options["from"]) {
            throw new \Exception("to should be higher than from !");
        }
        self::$options["from"] = (int)$options["from"];
        self::$options["to"] = (int)$options["to"];
    }
}

// execute delete command
$result = InvoicesRemover::execute();
// Based on the delete result, we need to set the exit code
if ($result === true) {
    exit(0); // exitcode 0 = success
}
else {
    exit(1); // exitcode 1 = error
}   
