<?php
/*
 * Created on 19.3.2012
 *
 * To change the template for this generated file go to
 * Window - Preferences - PHPeclipse - PHP - Code Templates
 */

require_once dirname(__FILE__).'/opasnet_base_download_session.php';
require_once dirname(__FILE__).'/opasnet_base_upload_session.php';
require_once dirname(__FILE__).'/opasnet_base_object.php';
require_once dirname(__FILE__).'/opasnet_base_wiki.php';

class OpasnetBase{
	
	private $db_link;
	private $mongo_link;
	
	function __construct($db_config)
	{		
		$this->db_link = mysqli_connect($db_config['mysql_host'], $db_config['mysql_username'], $db_config['mysql_password']);
		if (!$this->db_link)
		    throw new Exception('Could not connect to mysql: ' . mysqli_error($this->db_link));
		if (! mysqli_select_db($this->db_link, $db_config['mysql_db']))
			throw new Exception('Could not select mysql database: '.$db_config['mysql_db']);
			
		$m = new MongoDB\Client("mongodb://".$db_config['mongo_host']."/".$db_config['mongo_db']);
		$this->mongo_link = $m->opasnet;
		//$this->mongo_link = $m->selectDatabase('opasnet');
	}
	
	
	function __destruct()
	{
		mysqli_close($this->db_link);		
	}
		

	function find_object($id)
	{
		return new OpasnetBaseObject($this->db_link, $this->mongo_link, $id);
	}


	function find_objects()
	{
		return OpasnetBaseObject::find_all($this->db_link, $this->mongo_link, array('order' => 'name'));
	}

	function find_index($id)
	{
		$ind = new OpasnetBaseIndex($this->db_link, $this->mongo_link, $id);
		$ind->base_object = new OpasnetBaseObject($this->db_link, $this->mongo_link,(int)$ind->find_object_id());
		return $ind;
	}

	
	/*
	function find_wiki($id)
	{
		return new OpasnetBaseWiki($this->db_link, $id);
	}
	
	function find_wikis()
	{
		return OpasnetBaseWiki::find_all($this->db_link);
	}
	*/ 
	
	function replace($data)
	{
		$data['act']['type'] = 'replace';
		return $this->prepare_upload($data);
	}	
	
	function append($data)
	{
		$data['act']['type'] = 'append';
		return $this->prepare_upload($data);
	}	
	
	function upload_data($data)
	{
		if (! isset($data['key']) or ! isset($data['data']) or ! isset($data['indices']))
			throw new Exception('Imported data is invalid!');
		
		$key = trim($data['key']);
		
		if (empty($key) or strlen($key) != 32)
			throw new Exception('Upload key is invalid ('.$key.')');
		
		$upload_session = new OpasnetBaseUploadSession($this->db_link, $this->mongo_link, array('token'=>$key));
		
		# Test if session is more than an hour old
		$a = time();
		$b = strtotime("+1 hour", strtotime($upload_session->opened));
		if ( $a > $b )
			throw new Exception('Upload session is closed! '.$a.' > '.$b);			  
		
		$act = $upload_session->find_act();
		$obj = $act->base_object;
		
		$wiki = $obj->find_wiki();
		if (! $wiki->check_write_access($data))
			throw new Exception("Insufficient privileges to write!");	
		
		$inds = $this->match_indices($act, $data);

		#Store data to MongoDB
		if (! empty($obj->subset))
			$collection = $this->mongo_link->{$obj->ident.'.'.$obj->subset.'.dat'};#$collection = $this->mongo_link->{$obj->ident}->{$obj->subset}->dat;
		else
			$collection = $this->mongo_link->{$obj->ident.'.dat'};#$collection = $this->mongo_link->{$obj->ident}->dat;
		
		$rows = 0;
		foreach ($data['data'] as $row)
		{
			#echo "INSERTING to Mongo, series:".$act->series_id;
			$doc = array('sid' => (int)$act->series_id,'aid' => (int)$act->id);
			$i = 1;
			foreach ($inds as $index)
			{
				if (! isset($row[$i]))
					throw new Exception('Data cell for index "'.$index->name.'" is missing!');
				$loc =  $row[$i++];
				if ($index->type=='entity')
					$doc[$index->ident] = $index->add_location($loc);
				elseif ($index->type=='number')
					$doc[$index->ident] = (double)$loc;
				elseif ($index->type=='time')
				{
					if (! ($t = strtotime($loc)))
						throw new Exception('Invalid date format '.$loc);
					#$doc[$index->ident] = new MongoDate($t);
					$doc[$index->ident] = new MongoDB\BSON\UTCDateTime($t);
				}
			}
			if (isset($row['res']))
				$doc['res'] = $row['res'];
			if (isset($row['sd']))
				$doc['sd'] = $row['sd'];
			if (isset($row['mean']))
				$doc['mean'] = $row['mean'];
			$collection->insertOne($doc);
			$rows ++;
		}
		# Just ensure the index
		$collection->createIndex(array("sid" => 1, "aid" => 1));
		
		# Update the uploads counter
		$upload_session->uploads += 1;
		$upload_session->save();
		
		# Update the cell counter
		$act->cells += $rows;
		$act->save();
		
		return $rows;
	}
	
	function prepare_download($opts, $act_id, $params = array())
	{
		# Find the object
		$obj = new OpasnetBaseObject($this->db_link, $this->mongo_link, $opts);

		# Find the act
		$act = $obj->find_act($act_id);
		
		if (isset($params['samples']))
			$samples = $params['samples'];
		else
			$samples = $act->samples;
		
		$cs = (int) round(10000 / count($act->find_indices()));
		
		# Just try to estimate the data chunk size via sample count
		 if ($samples > 1)
		 {
		 	$div = (float)$samples / 50;
		 	if ($div < 1) $div = 1;
		 	$cs = round((float)$cs / $div); 		
			if ($cs < 1) $cs = 1;
		 }
		
		# Create new download session
		$ds = new OpasnetBaseDownloadSession($this->db_link, $this->mongo_link);
		$ds->token = OpasnetBaseDownloadSession::generate_token($this->db_link, $this->mongo_link);
		$ds->act_id = $act_id;
		#$ds->cursor = serialize($act->data_cursor($params, $cs));
		#session_id($ds->token);
		#session_start();
		#$_SESSION['cursor'] = $act->data_cursor($params, $cs);
		#$ds->chunk_size = $cs;
		$ds->save();
		
		$ds->write_data_files($act, $cs, $params);
		
		# Return the upload key
		return $ds->token;		
	}
	
	function download_data($token)
	{
		$ds = new OpasnetBaseDownloadSession($this->db_link, $this->mongo_link, array('token'=>$token));
		$act = $ds->find_act();
		$obj = $act->base_object;
		$wiki = $obj->find_wiki();
		if (! $wiki->check_read_access())
			throw new Exception("Secured object data download authentication failure!");
		return $ds->next_chunk();
	}
	
	private function prepare_upload($data)
	{
		if (! isset($data['object']) or ! isset($data['act']) or ($data['act']['type'] == 'replace' and ! isset($data['indices'])))
			throw new Exception('Prepare upload: Imported data is invalid!');
		
			
		$tmp = explode('.', $data['object']['ident']);
		if (count($tmp) == 2)
		{
			$ident = $tmp[0];
			$subset = $tmp[1];
		}
		else
		{
			$ident = $data['object']['ident'];
			$subset = null;
		}
			
		# Seek for ident or create new object
		try
		{
			$opts = array('ident'=>strtolower($ident), 'subset' => strtolower($subset));
			
			$obj = new OpasnetBaseObject($this->db_link, $this->mongo_link, $opts);
			
			# Check for permission to prepare upload
			$wiki = $obj->find_wiki();
			if (! $wiki->check_write_access($data))
				throw new Exception("Insufficient privileges to write!");	
			
			# Check if act with similar timestamp already exists
			//if (isset($data['act']['series_id']) && isset($data['act']['when']))
			//{
			//	$tact = OpasnetBaseAct::exists($this->db_link, $this->mongo_link, $data['object']['ident'], $data['act']['series_id'], $data['act']['when']);
			//	if ($tact)
			//		throw new Exception('Act already exists!!! series_id: '.$data['act']['series_id'].', time: '.$data['act']['when'].', ident: '.$tact->base_object->ident);			
			//}
		}
		catch(RecordNotFoundException $e)
		{
			# About to create new object, not so easy!
			if ($data['act']['type'] == 'append')	throw new Exception('Object not found! Cannot append data. Use replacing act first.');
					
			# Check for permission to prepare upload
			$wiki = new OpasnetBaseWiki($this->db_link, $this->mongo_link, $data['object']['wiki_id']);
			if (! $wiki->check_write_access($data))
				throw new Exception("Insufficient privileges to write!");			
			
			$obj = new OpasnetBaseObject($this->db_link, $this->mongo_link);
			$obj->update_attributes($data['object']);
			$obj->ident = $ident;
			$obj->subset = $subset;
			$obj->save();
		}

		if (! $obj->id)
			throw new Exception('Object id is null');
		
		# Check indices for append!
		if ($data['act']['type'] == 'append')
			$this->match_indices($obj->find_act(), $data);
	
		# Always create a new act
		$act = new OpasnetBaseAct($this->db_link, $this->mongo_link);
		$act->update_attributes($data['act']);
		$act->obj_id = $obj->id;
		$act->base_object = $obj;
		$act->cells = 0;
		$act->save();
		
		# Create indices?
		if ($act->type == 'replace')
			$inds = $this->create_indices($act, $data);
		
		# Create new upload session
		$us = new OpasnetBaseUploadSession($this->db_link, $this->mongo_link);
		$us->token = OpasnetBaseUploadSession::generate_token($this->db_link, $this->mongo_link);
		$us->act_id = $act->id;
		$us->uploads = 0;
		$us->save();
		
		# Return the upload key
		return $us->token;
	}	
	
	private function create_indices($act, $data)
	{
		$ret = array();
		$i = 0;
		foreach ($data['indices'] as $index)
		{
			$ind = new OpasnetBaseIndex($this->db_link, $this->mongo_link);
			$ind->update_attributes($index);
			#if ((! isset($ind->type) or ! $ind->type or empty($ind->type)) and isset($data['data']))
			#	$ind->type = $this->guess_index_type($data['data']);
			$ind->series_id = $act->series_id;
			$ind->ident = base_convert($i++,10,16);
			$ind->base_object = $act->base_object;
			$ind->save();
			$ret[] = $ind;
		}
		return $ret;
	}
	
	private function match_indices($act, $data)
	{
		$ret = array();
		$i = 0;
		foreach ($data['indices'] as $index)
		{
			$inds = OpasnetBaseIndex::find_all($this->db_link, $this->mongo_link, array('where'=>array('series_id = ? AND name = "?"',$act->series_id, trim($index['name'])), 'limit'=>'1'));
			if(empty($inds))
				throw new Exception("Index match not found for this series: ".$index['name']);
			$ind = array_pop($inds);
			$ind->base_object = $act->base_object;
			$ret[] = $ind;
		}
		return $ret;
	}
	/*
	private function guess_index_type($data)
	{
		$ret = array();
		$prev_type = '';
		$type = 'entity';
		foreach ($data as $row)
		{
			$c = trim($row);
					
			if (is_numeric($c) && ! $this->is_float2($c))
				$type = 'integer';
			elseif ($this->is_float2($c))
				$type = 'float';
			elseif (strtotime($c) !== false)
				$type = 'time';

			if (($prev_type == 'float' && $type == 'integer') || ($prev_type == 'integer' && $type == 'float'))
			{
				$type = 'float';
			}
			elseif ($prev_type != '' && $prev_type != $type)
			{
				$ret[$i] = 'entity';
				break;
			}

			switch($type){
				#case 'year': $ret[$i] = 'entity'; break;
				case 'integer': $ret = 'entity'; break;
				case 'float': $ret = 'number'; break;
				case 'time': $ret = 'time'; break;
				default: $ret = 'entity';
			}
			$prev_type = $type;
		}

		return $ret;
	}

	private function is_float2($n)
	{
		if ((string)(int)$n === (string)$n)
			return false;
		$n1 = str_replace(',','.',$n);
		$n2 = str_replace(',','.',str_replace('.','',$n));
		if ((string)(float)$n === (string)$n)
			return true;
		elseif ((string)(float)$n1 === (string)$n1)
			return true;
		elseif ((string)(float)$n2 === (string)$n2)
			return true;
		return false;
	}
*/
		
	
}


?>
