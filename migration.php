<?php
$config = require 'config/config.php';

$passwordPart = " ";
if (!empty($config["db_password"])) {
    $passwordPart = " -p'" . $config["db_password"] . "'";
}
$databaseStatus = shell_exec('mysql -u ' . $config["db_username"] . $passwordPart . ' -D ' . $config["db_name"] . ' --execute="SELECT CASE COUNT(*) WHEN \'0\' THEN \'has no invoice table\' ELSE \'has invoice table\' END AS contents FROM information_schema.tables WHERE table_type = \'BASE TABLE\' AND table_name LIKE \'invoice\' AND table_schema = \'' . $config["db_name"] . '\';"');
if (strpos($databaseStatus, "has no invoice table") !== false) {
    exec("mysql -u " . $config["db_username"] . " $passwordPart -D " . $config["db_name"] . " < " . __DIR__ . "/sql/invoice.sql");
}