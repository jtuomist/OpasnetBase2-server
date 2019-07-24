<?php
/*
 * Created on 28.3.2012
 *
 * To change the template for this generated file go to
 * Window - Preferences - PHPeclipse - PHP - Code Templates
 */
 
class RecordNotFoundException extends Exception
{
}
 
 abstract class OpasnetBaseActiveRecord
 {
 	const TABLE_NAME = 'abstract';

 	protected $mysql_link;
 	protected $mongo_link;

 	protected $data;
	protected $id;
	
	protected $dirty;
 	
 	function __construct($mysql_link, $mongo_link, $id = 0)
 	{
 		$this->mysql_link = $mysql_link;
		$this->mongo_link = $mongo_link;
			
 		$this->data = array();
 		$this->dirty = false;
 
 		if (is_int($id))
 		{
 			$this->load_by_id($id);
 		}
 		elseif (is_array($id))
 		{
 			$this->load_by_columns($id);
 		}
 	}
 	 	
 	function __get($name)
 	 {
 	 	if ($name == 'id')
 	 		return $this->id;
 	 	
 	 	if (! isset($this->data[$name])){
	 	 	$q = "SELECT `".mysqli_real_escape_string($this->mysql_link, $name)."` FROM `".$this->table_name()."` WHERE id=".$this->id;
	 	 	$res = mysqli_query($this->mysql_link, $q);
	 	 	if (! $res)
	 	 		throw new Exception("Query failed: ".mysqli_error($this->mysql_link));
 	 		$row = mysqli_fetch_assoc($res);
			$this->data[$name] =  $row[$name];
			mysqli_free_result($res);
			if (empty($row))
				throw new Exception("__get: Record not found!");
 	 	}
 	 	return $this->data[$name];
 	 }
 	 
 	 function __set($name, $value)
 	 {
 	 	$this->data[$name] = $value;
 	 	$this->dirty = true;
 	 }
 	 
 	 function update_attributes($attributes)
 	 {
		$this->update_data($attributes);
 	 	$this->dirty = true;	
 	 }
 	  	 
 	 /* Implement validation in your model! Use exceptions!  */
 	 abstract function validate();
 	 
 	 function save()
 	 {
 	 	if (! $this->dirty)
 	 		return;
 	 	
 	 	// Validation hook
 	 	$this->validate();
 	 		
 	 	if ($this->is_new_record())
 	 	{
 	 		$q = 'INSERT INTO `'.$this->table_name().'`'.$this->data_to_sql_insert();
 	 		if (! mysqli_query($this->mysql_link, $q) or mysqli_affected_rows($this->mysql_link) != 1)
 	 			throw new Exception("Record creation failed: ".mysqli_error($this->mysql_link)); 	 		
 	 		$this->id = mysqli_insert_id($this->mysql_link);
 	 	}
 	 	else
 	 	{
 	 		$q = 'UPDATE `'.$this->table_name().'` SET '.$this->data_to_sql_pairs().' WHERE id = '.$this->id;
 	 		if (! mysqli_query($this->mysql_link, $q) or mysqli_affected_rows($this->mysql_link) != 1)
 	 			throw new Exception("Record update failed: ".mysqli_error($this->mysql_link).", query: ".$q);
 	 	}
 	 }
 	
 	
 	 function is_new_record()
 	 {
 	 	return ($this->id == 0);
 	 }
 	 	
 	 function dump()
 	 {
 	 	$ret = array('id: '.$this->id);
 	 	foreach($this->data as $k => $v)
 	 		$ret[] = $k.': '.$v;
 	 	return join('<br/>',$ret);
 	 }
 	 
 	 function as_array()
 	 {
 	 	$ret = array('id' => $this->id);
 	 	foreach($this->data as $k => $v)
 	 		$ret[$k] = $v;
 	 	return $ret; 	 	
 	 }
 	 
 	 static function find_all($db_link, $mongo_link, $options = array())
 	 {
 	 	$ok = false;
 	 	$c = get_called_class();
 	 	$where = '';
 	 	$order = '';
 	 	$limit = '';
 	 	if (isset($options['where']))
 	 		$where = ' WHERE '.self::parse_qmarks($options['where'], $db_link);
 	 	if (isset($options['order']))
 	 		$order = ' ORDER BY '.mysqli_real_escape_string($db_link, $options['order']);
 	 	if (isset($options['limit']))
 	 		$limit = ' LIMIT '.mysqli_real_escape_string($db_link, $options['limit']);
 	 	$q = 'SELECT * FROM `'.$c::TABLE_NAME.'`'.$where.$order.$limit;
 	 	
 	 	#echo '<pre>'.$q.'</pre>';
 	 	
 	 	$res = mysqli_query($db_link, $q);
 	 	if (! $res)
 	 		throw new Exception("Query failed: ".mysqli_error($db_link).", query:".$q);
 		
 		$ret = array();
 		while ($row = mysqli_fetch_assoc($res))
 		{
			$c = get_called_class();
	    	$o = 	new $c($db_link, $mongo_link);
	    	$o->update_data($row);
	    	$o->id = $row['id']; 
		    $ret[] = $o;
		    $ok = true;
 		}
 		mysqli_free_result($res);
		#if (! $ok)
		#	throw new RecordNotFoundException("Records not found!");
		return $ret; 	 	
 	 }
 	 
 	 static function parse_qmarks($arr, $db_link)
 	 {
 	 	$i = 0;
 	 	$parts = explode('?',$arr[$i++]);
 	 	$ret = '';
 	 	if (count($parts) != count($arr))
 	 		throw new Exception('Invalid number of array items vs. question marks');
 	 	foreach($parts as $p)
 	 	{
 	 		$ret .= $p;
 	 		if ($i < count($parts))
 	 		 $ret .= mysqli_real_escape_string($db_link, $arr[$i++]);
 	 	}
 	 	return $ret;
 	 }
 	 
 	 	
 	 private function load_by_columns($pairs)
 	 {
 	 	$ok = false;
 	 	$where = array();
 	 	
 	 	foreach($pairs as $k => $v)
 	 		$where []= '`'.mysqli_real_escape_string($this->mysql_link, $k).'`="'.mysqli_real_escape_string($this->mysql_link, $v).'"';
 	 		
 	 	$q = 'SELECT * FROM `'.$this->table_name().'` WHERE '.join(' AND ', $where).' LIMIT 1';
 	 	
 	 	$res = mysqli_query($this->mysql_link, $q);
 	 	if (! $res)
 	 		throw new Exception("Query failed: ".mysqli_error($this->mysql_link));
 		while ($row = mysqli_fetch_assoc($res))
 		{
 			foreach ($row as $k => $v)
 			if ($k != 'id')
		    	$this->data[$k] =  $v;
		    else
		    	$this->id = $v;
		    $ok = true;
 		}
 		mysqli_free_result($res);
		if (! $ok)
	#		throw new RecordNotFoundException(count($pairs));
			throw new RecordNotFoundException('Load by columns: record not found!'.join('+',$pairs));
 	 }
 	 	
 	 private function load_by_id($id)
 	 {
 	 	if ($id < 1)
 	 		return;
 	 	$ok = false;
 	 	$q = 'SELECT * FROM `'.$this->table_name().'` WHERE id='.mysqli_real_escape_string($this->mysql_link, $id);
 	 	$res = mysqli_query($this->mysql_link, $q);
 	 	if (! $res)
 	 		throw new Exception("Query failed: ".mysqli_error($this->mysql_link));
 		while ($row = mysqli_fetch_assoc($res))
 		{
 			foreach ($row as $k => $v)
		    	$this->data[$k] =  $v;
		    $ok = true;
		    $this->id = $id;
 		}
 		mysqli_free_result($res);
		if (! $ok)
			throw new RecordNotFoundException('Load by id: record not found! id=>'.$id);
 	 }
 	 	
 	 private function data_to_sql_pairs()
 	 {
 	 	$attribs = array();
 	 	foreach($this->data	 as $k => $d)
 	 	{
 	 		$attribs[] = '`'.mysqli_real_escape_string($this->mysql_link, $k).'` = "'.mysqli_real_escape_string($this->mysql_link, $d).'"';
 	 	}
 	 	return join(",",$attribs);
 	 }
 	 
 	 private function data_to_sql_insert()
 	 {
  	 	$attribs = array();

		/* Sanitize first */
	 	foreach($this->data	 as $k => $d)
 	 		$attribs[mysqli_real_escape_string($this->mysql_link, $k)] = mysqli_real_escape_string($this->mysql_link, $d);

		return '(`'.join('`,`',array_keys($attribs)).'`) VALUES ("'.join('","',$attribs).'")'; 	 	
 	 }
 	
 	private function table_name()
 	{
 		$c = get_called_class();
 		return $c::TABLE_NAME;
 	}
 	
 	/* does not set dirty */
 	protected function update_data($attributes)
 	{
 	 	if (! is_array($attributes))
 	 		return false;
 	 	foreach($attributes as $k => $v)
	 	 	if ($k != 'id')
	 	 		$this->data[$k] = trim($v); 		
 	}
 	
 }
 
 
?>
