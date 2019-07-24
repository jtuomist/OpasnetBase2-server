<?php
/*
 * Created on 22.5.2012
 *
 * To change the template for this generated file go to
 * Window - Preferences - PHPeclipse - PHP - Code Templates
 */
 
 require_once dirname(__FILE__).'/opasnet_base_active_record.php';
 require_once dirname(__FILE__).'/opasnet_base_act.php';
 
 class OpasnetBaseUploadSession extends OpasnetBaseActiveRecord
 {

		const TABLE_NAME = "upload_sessions";

		function validate()
		{
			$errors = array();
			if (!isset($this->data['token']) or trim($this->data['token']) == '')
				$errors[] = 'token must not be empty and it must be unique';
			if (! empty($errors))
				throw new Exception('Object validation failed: '.join(', ',$errors));
		}
		
		function find_act()
		{
			$act = new OpasnetBaseAct($this->mysql_link, $this->mongo_link, (int)$this->act_id);
			$act->base_object = new OpasnetBaseObject($this->mysql_link, $this->mongo_link, (int)$act->obj_id);
			return $act;
		}
 	
 		static function generate_token($mysql_link, $mongo_link)
 		{
 			$chars = 'aeuybdghjmnpqrstvzAEUYBDGHJLMNPQRSTVWXZ1234567890';
 			do
 			{
				$token = '';
				for ($i = 0; $i < 32; $i++)
					$token .= $chars[(rand() % strlen($chars))];
 				$taken = true;
 				try {
 					$us = new OpasnetBaseUploadSession($mysql_link, $mongo_link, array('token' => $token));
 				}
 				catch (RecordNotFoundException $rnfe)
 				{
 					$taken = false;
 				}
 			} while ($taken);
 			return $token;
 		}
 	
 }
 
?>
