<?php

namespace InvoiceAutomation;

require 'vendor/autoload.php';
require 'Lib/Connector.php';
require 'Lib/Logger.php';
require 'Lib/Invoice.php';
require 'Lib/Project.php';
use InvoiceAutomation\Lib\Connector;
use InvoiceAutomation\Lib\Logger;
use InvoiceAutomation\Lib\Invoice;
use InvoiceAutomation\Lib\Project;

class InvoicesAdder
{

    /**
     *
     * @var array command options ,default is empty array
     */
    private static $options = array();

    /**
     * Wrap DB connection and invoices add
     * @access public
     * @return boolean true if whole add process is successful or not
     */
    public static function execute()
    {
        Logger::log('Starting adding...');
        $result = false;
        $connector = new Connector();
        // start DB connection
        $connector->startConnection();
        $connection = $connector->connection;
        // handle add exceptions
        try {
            // run migrations
            exec("php migration.php");
            // run the add process
            $addResult = self::add($connection);
            if ($addResult === true) {
                Logger::log('Add attempt successful!');
                $result = true;
            }
            else {
                Logger::log('Add attempt failed!');
            }
        } catch (Exception $e) {
            $addResult = false;
            // log exception message
            Logger::log($e->getMessage());
        }
        $connector->killConnection();
        return $result;
    }

    /**
     * Add an invoice 
     * @access private
     * @param PDO $connection database connection
     * @return boolean true if whole addition process is successful or not
     * @throws \Exception php-zip extension is not enabled!
     */
    private static function add($connection)
    {
        // handle options check
        // load passed options to command
        self::setOptions();
        $number = self::$options["number"];
        $project = self::$options["project"];
        $projectData = Project::getByName($connection, $project);
        Invoice::add($connection, $number, /*4projectId=*/ $projectData["pct_ID"]);
        return true;
    }

    /**
     * Set options after validating it
     * @access private
     * @throws \Exception number is required !
     * @throws \Exception project is required !
     * @throws \Exception number should be numeric !
     * @throws \Exception project should match format 'abcProject' !
     */
    private static function setOptions()
    {
        // options starting with "--" like "--number"
        $longOptions = array(
            "number:", // Required value
            "project:", // Required value
        );
        $options = getopt(/* $shortopts = */ "", $longOptions);
        if (!array_key_exists("number", $options)) {
            throw new \Exception("number is required !");
        }
        if (!array_key_exists("project", $options)) {
            throw new \Exception("project is required !");
        }
        if (!is_numeric($options["number"])) {
            throw new \Exception("number should be numeric !");
        }
        if (!preg_match('/^[0-9a-zA-Z ]+$/', $options['project'])) {
            throw new \Exception("projects should match format 'abcProject' !");
        }
        self::$options["number"] = (int)$options["number"];
        self::$options["project"] = $options["project"];
    }
}
if( ! ini_get('date.timezone') )
{
    date_default_timezone_set('Africa/Cairo');
}
// execute add command
$result = InvoicesAdder::execute();
// Based on the addition result, we need to set the exit code
if ($result === true) {
    exit(0); // exitcode 0 = success
}
else {
    exit(1); // exitcode 1 = error
}   
