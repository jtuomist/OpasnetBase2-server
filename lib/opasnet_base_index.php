 <?php
/*
 * Created on 28.3.2012
 *
 * To change the template for this generated file go to
 * Window - Preferences - PHPeclipse - PHP - Code Templates
*/
 
 require_once dirname(__FILE__).'/opasnet_base_active_record.php';
 
 class OpasnetBaseIndex extends OpasnetBaseActiveRecord
 {
  		private $base_object = null;
  		# locations cache
  		private $locations = array();
 	
		const TABLE_NAME = 'inds';
 	
		function validate()
		{
			$errors = array();
			if (!isset($this->data['series_id']) or intval($this->data['series_id']) < 1)
				$errors[] = 'series_id must be a number larger than zero';
			#if (!isset($this->data['page']) or intval($this->data['page']) < 1)
			#	$errors[] = 'page must be a number larger than zero';
			#if (!isset($this->data['wiki_id']) or intval($this->data['wiki_id']) < 1)
			#	$errors[] = 'wiki_id must be a number larger than zero';
			if (!isset($this->data['order_index']) or intval($this->data['order_index']) < 1)
				$errors[] = 'order_index must be a number larger than zero';
			if (!isset($this->data['ident']) or trim($this->data['ident']) == '')
				$errors[] =  'ident must not be empty';
			if (!isset($this->data['name']) or trim($this->data['name']) == '')
				$errors[] =  'name must not be empty';
			if (! empty($errors))
				throw new Exception('Index validation failed: '.join(', ',$errors));
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
 	
 		function find_locations()
 		{
 			if (empty($this->locations))
 			{
 				if ($this->base_object === null)
					throw new Exception('Cannot find data, object is not set!');
				if (! empty($this->base_object->subset))
					$collection = $this->mongo_link->{$this->base_object->ident.'.'.$this->base_object->subset.'.locs'};
				else
					$collection = $this->mongo_link->{$this->base_object->ident.'.locs'};
 				$cursor = $collection->find(array('iid'=>(int)$this->id));
 				foreach ($cursor as $obj)
					$this->locations[(int)$obj['lid']] = (string)$obj['val'];
 			}
 			return $this->locations;	
 		}
 		
 		function find_location($loc_id)
 		{
 			$locs = $this->find_locations();
 			if (! isset($locs[$loc_id]))
 				throw new Exception("Location for given id not found!!!");
 			return $locs[$loc_id];
 		}
 		 	
 		function add_location($loc)
 		{		
			if ($this->base_object === null)
				throw new Exception('Cannot find data, object is not set!');
				
			$locs = $this->find_locations();
			
			if (($key = array_search((string)$loc, $locs)) === false)
			{
				$key = $this->last_location_id() + 1;
				if ($key < 1)
					throw new Exception("Invalid location identifier!!!");
				$doc = array('iid' => (int)$this->id, 'lid' => (int)$key, 'val' => (string)$loc);
				if (! empty($this->base_object->subset))
					$collection = $this->mongo_link->{$this->base_object->ident.'.'.$this->base_object->subset.'.locs'};
				else
					$collection = $this->mongo_link->{$this->base_object->ident.'.locs'};
				$collection->insertOne($doc);
				$collection->createIndex(array("iid" => 1, "lid" => 1)); 
				# update cache too
				$this->locations[(int)$key] = (string)$loc;
			}

			return (int)$key;
 		}
 		
 		function find_object_id()
 		{
 	 	 	$q = "SELECT obj_id FROM acts LEFT JOIN inds ON inds.series_id = acts.series_id WHERE inds.id=".$this->id;
	 	 	
	 	 	$res = mysqli_query($this->mysql_link, $q);
	 	 	
	 	 	if (! $res)
	 	 		throw new Exception("Query failed: ".mysqli_error($this->mysql_link));

			$ret = false;

 	 		while ($row = mysqli_fetch_assoc($res))
				$ret = $row['obj_id'];
			    
			mysqli_free_result($res);
			    
			if (! $ret)
				throw new Exception("Record not found!");
		
			return $ret;			
 		}
 		
 	    private function last_location_id()
 		{
			if (! empty($this->base_object->subset))
				$collection = $this->mongo_link->{$this->base_object->ident.'.'.$this->base_object->subset.'.locs'};
			else
				$collection = $this->mongo_link->{$this->base_object->ident.'.locs'};
			#$cursor = $collection->find(array('iid'=>(int)$this->id))->sort(array('lid' => -1))->limit(1);
			$cursor = $collection->find(
				array('iid'=>(int)$this->id),
				[
					'sort' => array('lid' => -1),
					'limit' => 1
				]
			);
			foreach ($cursor as $obj)
				return (int)$obj['lid'];
			return 0;	 			
 		}
 		
 	
 }
 
?>
