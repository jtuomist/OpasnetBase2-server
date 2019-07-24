<?php



error_reporting(E_ALL);
ini_set('display_errors', '1');
date_default_timezone_set('UTC');

require_once(dirname(__FILE__).'/vendor/autoload.php');

echo "JEP";

try{
		$m = new MongoDB\Client("mongodb://localhost/opasnet");

echo "A";

}
catch (Exception $e)
{
echo $e->getMessage();
}

?>
