<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once(dirname(__FILE__).'/config.php');

$mysql_link = mysqli_connect($db_config['opasnet']['mysql_host'], $db_config['opasnet']['mysql_username'], $db_config['opasnet']['mysql_password']);
if (!$mysql_link)
	throw new Exception('Could not connect to mysql: ' . mysqli_error($mysql_link));
if (! mysqli_select_db($mysql_link, $db_config['opasnet']['mysql_db']))
	throw new Exception('Could not select mysql database: '.$db_config['opasnet']['mysql_db']);


mysqli_query($mysql_link, 'DELETE FROM acts WHERE cells = 0 AND type = "append"');
$r = mysqli_affected_rows($mysql_link);

echo $r . " records deleted";




?>