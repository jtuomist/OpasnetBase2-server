<?php
/*
 * Created on 2012
 *
 * To change the template for this generated file go to
 * Window - Preferences - PHPeclipse - PHP - Code Templates
 */
 
 require_once dirname(__FILE__).'/opasnet_base_active_record.php';
 
 class OpasnetBaseUser extends OpasnetBaseActiveRecord
 {

		const TABLE_NAME = 'users';
 	
		function validate()
		{

		}


		function has_wiki_permission($wiki_name, $perm = 'R')
		{
			$arr = explode(',',$this->privileges);
			foreach($arr as $a)
			{
				$tmp = explode('=',$a);
				if (count($tmp) == 2)
				{
					$w = trim($tmp[0]);
					$p = trim($tmp[1]);
					if ($w == $wiki_name)
						if (strpos($p, $perm) !== false)
							return true;
				}	
			}
			return false;
		}
 	
 }
 

 
?>
