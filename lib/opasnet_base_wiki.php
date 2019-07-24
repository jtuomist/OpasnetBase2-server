<?php
/*
 * Created on 29.3.2012
 *
 * To change the template for this generated file go to
 * Window - Preferences - PHPeclipse - PHP - Code Templates
 */
 
 require_once dirname(__FILE__).'/opasnet_base_active_record.php';
 
 require_once dirname(__FILE__).'/opasnet_base_user.php';
 
 class OpasnetBaseWiki extends OpasnetBaseActiveRecord
 {

		const TABLE_NAME = 'wikis';
 	
		function validate()
		{
			$errors = array();
			if (!isset($this->data['url']) or trim($this->data['url']) == '')
				$errors[] = 'url must not be empty';
			if (!isset($this->data['name']) or trim($this->data['name']) == '')
				$errors[] =  'name must not be empty';
			if (! empty($errors))
				throw new Exception('Object validation failed: '.join(', ',$errors));
		}

		function check_read_access()
		{			
			if ($this->public) return true;
			
			if (isset($_REQUEST['username']) && isset($_REQUEST['password']) && $_REQUEST['username'] != '' && $_REQUEST['password'] != '')
			{
				$users = OpasnetBaseUser::find_all($this->mysql_link, $this->mongo_link, array('where'=>array('username = "?"',$_REQUEST['username'])));
				if (count($users) == 0)
					return false;
				$user = array_pop($users);
				$str = '';
				if (isset($_REQUEST['index']))
					$str .= $_REQUEST['index'];
				if (isset($_REQUEST['ident']))
					$str .= $_REQUEST['ident'];
				if (isset($_REQUEST['key']))
					$str .= $_REQUEST['key'];
				$str .= $user->password;
				if (md5($str) != $_REQUEST['password'] || ! $user->has_wiki_permission($this->name, 'R'))
					return false;	
				return true;			
			}
			return false;
		}
		
		function check_write_access($data)
		{			
			if (isset($data['username']) && isset($data['password']) && $data['username'] != '' && $data['password'] != '')
			{
				$users = OpasnetBaseUser::find_all($this->mysql_link, $this->mongo_link, array('where'=>array('username = "?"',$data['username'])));
				if (count($users) == 0)
					return false;
				$user = array_pop($users);
				$str = '';
				if (isset($data['object']['ident']))
					$str .= $data['object']['ident'];
				if (isset($data['key']))
					$str .= $data['key'];
				$str .= $user->password;
				
				if (md5($str) != $data['password'] || ! $user->has_wiki_permission($this->name, 'W'))
					return false;	
				return true;			
			}
			return false;
		} 

 	
 }
 

 
?>
