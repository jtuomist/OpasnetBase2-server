<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');


 function do_post_request($url, $data, $optional_headers = null)
{
	  $params = array('http' => array(
	              'method' => 'POST',
	              'content' => $data
	            ));
	  if ($optional_headers !== null) {
	    $params['http']['header'] = $optional_headers;
	  }
	  $ctx = stream_context_create($params);
	  $fp = @fopen($url, 'rb', false, $ctx);
	  if (isset($php_errormsg))
	  	$msg = $php_errormsg;
	  else
	  	$msg = "";
	  if (!$fp) {
	    throw new Exception("Problem with $url, $msg");
	  }
	  $response = @stream_get_contents($fp);
	  if ($response === false) {
	    throw new Exception("Problem reading data from $url, $rmsg");
	  }
	  return $response;
}

$header = array(
	'object'=>array(
		'name' => "Ahaaa",
		'ident' => "testi.kaavii",
		'subset_name' => 'Kaavi',
		'type' => "variable",
		'page' => 123578,
		'wiki_id' =>1
		),
	'act'=>array(
		'unit' => "kg/cm3",
		'who' => "Alberto",
		'when' => "2011-10-10 12:44",
		'samples' => 1,
		'comments' => "Tämä on kommentti tähän actiin"
		),
	'indices'=>array(
		1=>array('type'=>'entity','name'=>'Indeksi ykkönen','page'=>666,'wiki_id'=>1,'order_index'=>1,'hidden'=>0),
		2=>array('type'=>'number','name'=>'Indeksi kakkonen','page'=>777,'wiki_id'=>2,'order_index'=>2,'hidden'=>0),
		3=>array('type'=>'time','name'=>'Indeksi kolmonen','page'=>888,'wiki_id'=>1,'order_index'=>3,'hidden'=>1),
		4=>array('type'=>'entity','name'=>'iv','page'=>666,'wiki_id'=>1,'order_index'=>4,'hidden'=>0),
		5=>array('type'=>'number','name'=>'v','page'=>777,'wiki_id'=>2,'order_index'=>5,'hidden'=>0),
		6=>array('type'=>'time','name'=>'vi','page'=>888,'wiki_id'=>1,'order_index'=>6,'hidden'=>1),
		7=>array('type'=>'entity','name'=>'vii','page'=>666,'wiki_id'=>1,'order_index'=>7,'hidden'=>0),
		8=>array('type'=>'number','name'=>'viii','page'=>777,'wiki_id'=>2,'order_index'=>8,'hidden'=>0),
		9=>array('type'=>'time','name'=>'ix','page'=>888,'wiki_id'=>1,'order_index'=>9,'hidden'=>1),
		10=>array('type'=>'entity','name'=>'x','page'=>666,'wiki_id'=>1,'order_index'=>10,'hidden'=>0),
		11=>array('type'=>'number','name'=>'xi','page'=>777,'wiki_id'=>2,'order_index'=>11,'hidden'=>0),
		12=>array('type'=>'time','name'=>'xii','page'=>888,'wiki_id'=>1,'order_index'=>12,'hidden'=>1)
	)
);
$data['indices']=array(
		1=>array('name'=>'Indeksi ykkönen'),
		2=>array('name'=>'Indeksi kakkonen'),
		3=>array('name'=>'Indeksi kolmonen'),
		4=>array('name'=>'iv'),
		5=>array('name'=>'v'),
		6=>array('name'=>'vi'),
		7=>array('name'=>'vii'),
		8=>array('name'=>'viii'),
		9=>array('name'=>'ix'),
		10=>array('name'=>'x'),
		11=>array('name'=>'xi'),
		12=>array('name'=>'xii')
	);

for ($i = 1; $i < 500; $i++)
	$data['data'][$i] = array(1 => 'porsche',2 => 123456.67,3 => "2011-05-13T14:22:46.12+02:00", 4 => 'lambo',5 => 123456.67,6 => "2011-05-13T14:22:46.12",7 => 'lada',8 => 123456.67,9 => "2011-05-13T14:22:46.777Z",10 => 'mosse',11 => 123456.67,12 => "2011-05-13T14:22:46.777Z",'res' => $i, 'mean' => $i+1, 'sd' => $i+2);

try{
	echo "POSTing for key...<br/>";
	$ret = json_decode(do_post_request('http://127.0.0.1/opasnet_base_2/index.php', http_build_query(array('json' => json_encode($header)))));
	if ($ret && ! isset($ret->error) && ! empty($ret->key)) 
	{
		echo "Token: " . $ret->key;
		echo "<br/><br/>POSTing data<br/>";
		$data['key'] = $ret->key;
		$ret = do_post_request('http://127.0.0.1/opasnet_base_2/index.php', http_build_query(array('json' => json_encode($data))));
	}
} catch (Exception $e)
{
	echo $e->getMessage();
}

print_r($ret);

?>
