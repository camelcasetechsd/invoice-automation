<?php

$result = exec("php Generator.php");
$zipFileName = "downloads.zip";

header("Content-type: application/zip"); 
header("Content-Disposition: attachment; filename=$zipFileName");
header("Content-length: " . filesize($zipFileName));
header("Pragma: no-cache"); 
header("Expires: 0"); 
readfile("$zipFileName");

