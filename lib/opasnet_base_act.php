<?php
/*
 * Created on 29.3.2012
 *
 * To change the template for this generated file go to
 * Window - Preferences - PHPeclipse - PHP - Code Templates
 */
 
 require_once dirname(__FILE__).'/opasnet_base_active_record.php';
 require_once dirname(__FILE__).'/opasnet_base_index.php';
 
 class OpasnetBaseAct extends OpasnetBaseActiveRecord
 {
 		private $base_object;
 	
		const TABLE_NAME = 'acts';
 	
		function validate()
		{
			$errors = array();
			if (!isset($this->data['obj_id']) or intval($this->data['obj_id']) < 1)
				$errors[] = 'obj_id must be a number larger than zero';
			if (!isset($this->data['series_id']) or intval($this->data['series_id']) < 1)
				$errors[] = 'series_id must be a number larger than zero';
			if (!isset($this->data['samples']) or intval($this->data['samples']) < 1)
				$errors[] = 'samples must be a number larger than zero';
			if (!isset($this->data['type']) or trim($this->data['type']) == '')
				$errors[] =  'type must not be empty';
			if (! empty($errors))
				throw new Exception('Act validation failed: '.join(', ',$errors));
		}
		
		public function __set($name, $value)
		{
			if ($name == 'base_object')
				$this->base_object = $value;
			else
				parent::__set($name, $value);
		}

		public function __get($name)
		{
			if ($name == 'base_object')
				return $this->base_object;
			return parent::__get($name);
		}

		
		function save()
		{
			if (! isset($this->data['series_id']) || (int)$this->data['series_id'] < 1)
			{
				if ($this->type == 'replace')
				{
					$tmp = OpasnetBaseAct::find_all($this->mysql_link, $this->mongo_link,array('order'=>'series_id DESC', 'limit'=>'1'));
					# Empty base?
					if (empty($tmp))
						$this->series_id = 1;
					else
					{
						$latest_act = array_pop($tmp);				
						$this->series_id = $latest_act->series_id + 1;
					}
				}					
				elseif ($this->type == 'append')
				{				
					$tmp = OpasnetBaseAct::find_all($this->mysql_link, $this->mongo_link,array('where' => array('obj_id = ?', $this->obj_id), 'order'=>'series_id DESC', 'limit'=>'1'));
					if (empty($tmp))
						throw new Exception('Saving failed! Could not find act to append to.');
					$latest_act = array_pop($tmp);
					$this->series_id = $latest_act->series_id;	
				}
			}
						
			parent::save();
		}
		
		function find_indices()
		{
			$ret = array();
			$inds =  OpasnetBaseIndex::find_all($this->mysql_link, $this->mongo_link, array('where'=>array('series_id = ?',$this->series_id)));	
			foreach ($inds as $ind)
			{
				$ind->base_object = $this->base_object;
				$ret[] = $ind;
			}
			return $ret;
		}
		
		function find_index_by_name($name)
		{
			$inds =  OpasnetBaseIndex::find_all($this->mysql_link, $this->mongo_link, array('where'=>array('series_id = ? AND name = "?"',$this->series_id, $name)));	
			if (count($inds) > 0)
			{
				$ind = array_pop($inds);
				$ind->base_object = $this->base_object;
			}
			else
				throw new Exception('Index with name "'.$name.'" not found!');
			return $ind;			
		}		
		
		// Returns cursor for downloading the data
		function data_cursor($params, $chunk_size)
		{
			if ($this->base_object === null)
				throw new Exception('Cannot find data, object is not set!');
			
			$collection = $this->collection();
				
			$opts = array('sid'=>intval($this->series_id), 'aid'=> array('$lte' => $this->id));
					
			$this->add_filter_params($params, $opts);
		
			$sort_opts = array();
		
			if (isset($params['order']) && is_array($params['order']))
				foreach ($params['order'] as $o)
				{
					$tmp = explode(',',$o);
					($o[1] == 'a') ? $sort_opts[(string)$o[0]] = 1 : $sort_opts[(string)$o[0]] = -1;					
				}
				
			#throw new Exception(join(',',array_keys($sort_opts)));
				
			if (isset($params['limit']) && intval($params['limit']) > 0)
			{
				//$cursor = $collection->find($opts)->limit(intval($params['limit']))->sort($sort_opts);
				$cursor = $collection->find(
					$opts, 
					[
						'limit' => intval($params['limit']),
						'sort' => $sort_opts
						#'batchSize' => $chunk_size
					]
				);
			}
			else			
			{
				//$cursor = $collection->find($opts)->sort($sort_opts);
				$cursor = $collection->find(
					$opts, 
					[
						'sort' => $sort_opts
						#'batchSize' => $chunk_size
					]
				);
			}
			
			#$cursor->batchSize($chunk_size);
			return $cursor;
		}
		
		// Returns data for given chunk size
		/*function data_chunk(&$cursor, $size, $samples = false)
		{
			$ret = array();
			$indices = $this->find_indices();
			// iterate through the results
			$i = 0;
			#while ($cursor->hasNext() && $i < $size)
			while ($i < $size)
			{
				//$obj = $cursor->getNext();
				$obj = $cursor->next();
				$ret[$i] = array('sid'=>$obj['sid'], 'aid'=>$obj['aid']); 
				foreach ($indices as $index)
				{
				    if ($index->type == 'entity')
				    	$ret[$i][$index->ident] = $index->find_location($obj[$index->ident]);
				    elseif ($index->type == 'time')
				    	$ret[$i][$index->ident] = date('Y-M-d H:i:s',$obj[$index->ident]->sec);
				    else
				    	$ret[$i][$index->ident] = $obj[$index->ident];
				}
				if (isset($obj['mean']))
			   		$ret[$i]['mean'] = $obj['mean']; 
				if (isset($obj['sd']))
				   	$ret[$i]['sd'] = $obj['sd'];
				
				if (isset($obj['res']) && $samples === false || intval($samples) > 0)
				{   	
					if ($samples === false || ! is_array($obj['res']) || (int)$samples >= (int)$this->samples)
				   		$ret[$i]['res'] = $obj['res']; 
					else
						$ret[$i]['res'] = array_slice($obj['res'], 0, $samples);
				}
				$i ++;
			}
			return $ret;			
		}*/

		function series_cell_count($params = array())
		{
			if ($this->base_object === null)
				throw Exception('Cannot find data, object is not set!');

			# If no filter then fetch cell counts directly from mysql act table
			if (! isset($params['exclude']) && ! isset($params['include']) && ! isset($params['range']))
			{
				$acts = OpasnetBaseAct::find_all($this->mysql_link, $this->mongo_link, array('where'=>array('obj_id = ? AND series_id = ? AND id <= ?',$this->base_object->id, $this->series_id, $this->id)));
				$sum = 0;
				foreach($acts as $act)
					$sum += $act->cells;
				return $sum;
			}
			
			$collection = $this->collection();
			
			$opts = array('aid'=>array('$lte' => intval($this->id)), 'sid' => intval($this->series_id));
			$this->add_filter_params($params, $opts);
			return $collection->count($opts);	
		}
		
		function cell_count($params = array())
		{
			if ($this->base_object === null)
				throw Exception('Cannot find data, object is not set!');
							
			$collection = $this->collection();
			
			$opts = array('aid'=>intval($this->id));
			$this->add_filter_params($params, $opts);
			return $collection->count($opts);	
		}
				
		static function exists($db_link, $mongo_link, $ident, $series_id, $time)
		{
			$acts = OpasnetBaseAct::find_all($db_link, $mongo_link, array('where' => array('series_id  = ? AND `when` = "?"', $series_id, $time)));			
			if (empty($acts))
				return false;
			else
			{
				$act = array_pop($acts);
				$act->base_object = new OpasnetBaseObject($db_link, $mongo_link, (int)$act->obj_id);
				if ($act->base_object->ident == $ident)
					return $act;
				else
					return false;
			}
		}
 	
 	
 		private function add_filter_params($params, &$opts)
		{
			$indices = $this->find_indices();
			if (isset($params['exclude']))
			{
				is_array($params['exclude']) ? $arr = $params['exclude'] : $arr = array($params['exclude']); 
				foreach ($arr as $e)
				{
					$tmp = explode(',',$e);
					
					#throw new Exception((string)$ind->ident);

					$ident = (string)array_shift($tmp);
					
					if ($this->index_type($indices, $ident) != 'entity')
						throw new Exception("Exclude can only be applied on entity type indices!");
					
					$loc_ids = array();
					foreach ($tmp as $id)
						$loc_ids[] = intval($id);
					
					$opts[$ident] = array('$nin' => $loc_ids);
				}
			}
			if (isset($params['include']))
			{
				is_array($params['include']) ? $arr = $params['include'] : $arr = array($params['include']);
				foreach ($arr as $e)
				{
					$tmp = explode(',',$e);
					
					#throw new Exception((string)$ind->ident);

					$ident = (string)array_shift($tmp);

					if ($this->index_type($indices, $ident) != 'entity')
						throw new Exception("Exclude can only be applied on entity type indices!");
					
					$loc_ids = array();
					foreach ($tmp as $id)
						$loc_ids[] = intval($id);
					
					$opts[$ident] = array('$in' => $loc_ids);
				}
			}
			if (isset($params['range']))
			{
				is_array($params['range']) ? $arr = $params['range'] : $arr = array($params['range']);
				foreach ($arr as $e)
				{
					$tmp = explode(';',$e);
					
					if (count($tmp) != 3) throw new Exception('Invalid range filter!');

					$ident = trim((string)$tmp[0]);
					
					$tmp2 = array();
					
					if ($this->index_type($indices, $ident) == 'number')
					{
						if ($tmp[1] != '')
							$tmp2['$gte'] = (double)trim($tmp[1]);
						if ($tmp[2] != '')
							$tmp2['$lte'] = (double)trim($tmp[2]);
					}
					elseif ($this->index_type($indices, $ident) == 'time')
					{
						if ($tmp[1] != '')
							$tmp2['$gte'] = new MongoDB\BSON\UTCDateTime(strtotime(trim($tmp[1]))); #MongoDate
						if ($tmp[2] != '')
							$tmp2['$lte'] = new MongoDB\BSON\UTCDateTime(strtotime(trim($tmp[2]))); #MongoDate
					}
					else
						throw new Exception("Range can only be applied on number or time typed indices!");

					$opts[$ident] = $tmp2;
				}
			}
		} 
		
		private function index_type($indices, $index_ident)
		{
			foreach($indices as $i)
				if ($i->ident == $index_ident)
					return $i->type;
			return false;
		}
				
		private function collection()
		{
			if (! empty($this->base_object->subset))
				return $this->mongo_link->{$this->base_object->ident.'.'.$this->base_object->subset.'.dat'};
			else
				return $this->mongo_link->{$this->base_object->ident.'.dat'};			
		}
		
 }
 
?>
