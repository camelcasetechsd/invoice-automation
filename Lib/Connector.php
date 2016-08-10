<?php
namespace Timesheet\lib;

class Connector
{
    /**
     *
     * @var \PDO database connection
     */
    public $connection;
    
    /**
     *
     * @var array database configuration 
     */
    private $databaseConfig;
    
    public function __construct()
    {
        $this->databaseConfig = require 'config/config.php';
    }

    /**
     * Start mysql database connection using provided credentials
     * @access private
     */
    public function startConnection()
    {
        $host = $this->databaseConfig['db_host'];
        $dbname = $this->databaseConfig['db_name'];
        $port = $this->databaseConfig['db_port'];
        $username = $this->databaseConfig['db_username'];
        $password = $this->databaseConfig['db_password'];
        $this->connection = new \PDO("mysql:host=$host;dbname=$dbname;port=$port", $username, $password, array(
            \PDO::ATTR_TIMEOUT => 5, // set connection timeout to 5 secs
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, // use exceptions for errors
        ));
    }

    /**
     * Kill existing database connection
     * @access private
     */
    public function killConnection()
    {
        //close connection
        $this->connection = null;
    }

}
