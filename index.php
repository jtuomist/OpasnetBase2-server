<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
date_default_timezone_set('UTC');

require_once(dirname(__FILE__).'/vendor/autoload.php');

//MongoCursor::$timeout = 120000;

require_once(dirname(__FILE__).'/config.php');
require_once(dirname(__FILE__).'/lib/opasnet_base.php');

function params()
{
	$params = array();
	if (isset($_REQUEST['samples']))
		$params['samples'] = intval($_REQUEST['samples']);
	if (isset($_REQUEST['limit']))
		$params['limit'] = intval($_REQUEST['limit']);
	if (isset($_REQUEST['exclude']))
		$params['exclude'] = $_REQUEST['exclude'];
	if (isset($_REQUEST['include']))
		$params['include'] = $_REQUEST['include'];
	if (isset($_REQUEST['order']))
		$params['order'] = $_REQUEST['order'];
	if (isset($_REQUEST['range']))
		$params['range'] = $_REQUEST['range'];
	return $params;
}

gc_enable();

$start = microtime(true);

$ret = array();

try {

	// Connect to base
	$opbase = new OpasnetBase($db_config['opasnet']);

	if (isset($_REQUEST['json']))
	{
		$data =  json_decode($_REQUEST['json'], true);
		# Free the mem!
		unset($_REQUEST['json']);
	}
		
	if (isset($_REQUEST['_method']))
		$method = $_REQUEST['_method'];
	else
		$method = $_SERVER['REQUEST_METHOD'];

	// RESTful scheisse
	switch ($method) {	
			case 'POST':
				if (! isset($data))
					throw new Exception('Data not given! Unable to replace act!');
				$ret['action'] = "replace";
				if (! isset($data['key']))
				{
					$key = $opbase->replace($data);
					$ret['key'] =  $key;
				}
				else
					$ret['rows'] = $opbase->upload_data($data);
			break;
			case 'PUT':
				$ret['action'] = "append";
				if (! isset($data['key']))
				{
					$key = $opbase->append($data);
					$ret['key'] =  $key;
				}
				else
					$ret['rows'] = $opbase->upload_data($data);
			break;
			case 'GET':
				# Get the data
				if (isset($_REQUEST['key']))
				{
					$ret['data'] = $opbase->download_data($_REQUEST['key']);
				}
				elseif (isset($_REQUEST['index']))
				{
					# Index and its locations
					$arr = array();
					$index = $opbase->find_index((int)$_REQUEST['index']);
					$obj = $opbase->find_object(intval($index->find_object_id()));
					$wiki = $obj->find_wiki();
					if (! $wiki->check_read_access())
						throw new Exception("Secured object authentication failure!");
					
					$ret['index'] = $index->as_array();
					$ret['locations'] = $index->find_locations();
				}
				elseif (isset($_REQUEST['ident']))
				{
					$tmp = explode('.', $_REQUEST['ident']);
					if (count($tmp) > 1)
					{
						$ident = $tmp[0];
						$subset = $tmp[1];
					}
					else
					{
						$ident = $_REQUEST['ident'];
						$subset = null;
					}
					
					$opts = array('ident' => $ident, 'subset' => $subset);
					try {
						$obj = $opbase->find_object($opts);
					}
					catch (RecordNotFoundException $e)
					{
						# Try with lowercase ident before giving it up!
						$opts['ident'] = strtolower($opts['ident']);
						$opts['subset'] = strtolower($opts['subset']);
						$obj = $opbase->find_object($opts);						
					}
					$wiki = $obj->find_wiki();

					if (! $wiki->check_read_access())
						throw new Exception("Secured object authentication failure!");
					
					# Act list with indices
					$ret['object'] = $obj->as_array();
					$ret['object']['wiki_name'] = $wiki->name;
					$ret['object']['wiki_url'] = $wiki->url;
 					
 					# Get index and its locations using its name
 					if (isset($_REQUEST['index_name']))
 					{
 						# Get act by series id or the latest one
 						if (isset($_REQUEST['series']))
							$act = $obj->find_series_act($_REQUEST['series']);
						else
							$act = $obj->find_act();
 						
 						$index = $act->find_index_by_name($_REQUEST['index_name']);
					
 	 					$ret['index'] = $index->as_array();
						$ret['locations'] = $index->find_locations();						
 					}
 					# Results count
					elseif (isset($_REQUEST['act']) && isset($_REQUEST['results_count']))
					{
						if (intval($_REQUEST['act']) == 0)
						{
							$act = $obj->find_act();
							$act_id = $act->id;
						}
						else
						{
							$act_id = intval($_REQUEST['act']);
							#$act = $obj->find_act($act_id);
						}

						$ret['results_count'] = $obj->results_count($act_id, params());
					}
					elseif (isset($_REQUEST['series']))
					{
						# Get latest act of series
						$act = $obj->find_series_act($_REQUEST['series']);
						# Return the key for uploading the data
						$ret['key'] = $opbase->prepare_download($opts, $act->id, params());
						$inds = $act->find_indices();
						$tmp = array();
						foreach ($inds as $ind)
							$tmp[] = $ind->as_array();
						$ret['act'] = $act->as_array();
						$ret['indices'] = $tmp;
					}
					elseif (isset($_REQUEST['act']))
					{
						if (intval($_REQUEST['act']) == 0)
						{
							$act = $obj->find_act();
							$act_id = $act->id;
						}
						else
						{
							$act_id = $_REQUEST['act'];
							$act = $obj->find_act($act_id);
						}
						# Return the key for uploading the data
						$ret['key'] = $opbase->prepare_download($opts, $act_id, params());
						$inds = $act->find_indices();
						$tmp = array();
						foreach ($inds as $ind)
							$tmp[] = $ind->as_array();
						$ret['act'] = $act->as_array();
						$ret['indices'] = $tmp;
					}
					else
					{
						#isset($_REQUEST['act']) ? $act = $obj->find_act($_REQUEST['act']) : $act = $obj->find_act();
						#$arr['act'] = $act->as_array();
						$ret['acts'] = array();
						foreach ($obj->find_acts() as $act)
						{
							$inds = $act->find_indices();
							$tmp = array();
							foreach ($inds as $ind)
								$tmp[] = $ind->as_array();
							$ret['acts'][$act->id] = array('act' => $act->as_array(), 'indices' => $tmp);
						}
					}
					#$arr['data'] = $act->find_data();
				}
				else
				{
					# All objects
					$ret['objects'] = array();
					foreach($opbase->find_objects() as $obj)
						$ret['objects'][] = $obj->as_array();
				}
				# Headers
				header('Cache-Control: no-cache, must-revalidate');
				header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
				header('Content-type: application/json');
			break;
			default:
				echo "Unknown method!!!";
			break;
	} 

	

} catch (Exception $e)
{
	$ret['error'] = $e->getMessage();
}
$ret['time'] = microtime(true) - $start;

echo json_encode($ret);
gc_disable();
?>

