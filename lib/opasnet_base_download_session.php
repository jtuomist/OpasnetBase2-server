<?php
/*
 * Created on 22.5.2012
 *
 * To change the template for this generated file go to
 * Window - Preferences - PHPeclipse - PHP - Code Templates
 */
 
 require_once dirname(__FILE__).'/opasnet_base_active_record.php';
 require_once dirname(__FILE__).'/opasnet_base_act.php';
 
 class OpasnetBaseDownloadSession extends OpasnetBaseActiveRecord
 {

		const TABLE_NAME = "download_sessions";

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
 					$us = new OpasnetBaseDownloadSession($mysql_link, $mongo_link, array('token' => $token));
 				}
 				catch (RecordNotFoundException $rnfe)
 				{
 					$taken = false;
 				}
 			} while ($taken);
 			return $token;
 		}
 		
 		function next_chunk()
 		{
			$fname = "/tmp/".$this->token.sprintf("%04s",$this->chunk_counter);
			if (! file_exists($fname))
				return '';
 			if (! ($handle = fopen($fname, "r")))
 				throw new Exception('Cannot open file: '.$fname);
			$json = fread($handle, filesize($fname));
			fclose($handle);
			unlink($fname);
 			$this->chunk_counter ++;
 			$this->save();
 			return $json;
 		}
 		
 		function write_data_files($act, $cs, $params)
 		{
 			$cursor = $act->data_cursor($params, $cs);
 			$i = 0;
 			
 			if (isset($params['samples']))
 				$samples = $params['samples'];
 			else
 				$samples = false;
 			
 			/*do
 			{
 				$chunk=$act->data_chunk($cursor, $cs, $samples);
 				if (! empty($chunk))
 				{
 					$fname = "/tmp/".$this->token.sprintf("%04s",$i);
 					if (! ($handle = fopen($fname, "w")))
 						throw new Exception('Cannot write file: '.$fname);
					fwrite($handle, json_encode($chunk));
					fclose($handle);
 					$i++;
 				}	
 			} while (! empty($chunk));*/
			
			$indices = $act->find_indices();
			
			$j = 0;
			
			$fname = "/tmp/".$this->token.sprintf("%04s",$i);
			if (! ($handle = fopen($fname, "a")))
 				throw new Exception('Cannot write file: '.$fname);
			fwrite($handle, "[");
			foreach($cursor as $obj) {
				if ($j < $cs) {
					if ($j > 0)
						fwrite($handle, ",");
					$j++;
				}
				else 
				{
					$i++;
					$j = 1;
					fwrite($handle, "]");
					fclose($handle);
					$fname = "/tmp/".$this->token.sprintf("%04s",$i);
					if (! ($handle = fopen($fname, "a")))
						throw new Exception('Cannot write file: '.$fname);
					fwrite($handle, "[");
				}
				$ret = array('sid'=>$obj['sid'], 'aid'=>$obj['aid']); 
				foreach ($indices as $index)
				{
				    if ($index->type == 'entity')
				    	$ret[$index->ident] = $index->find_location($obj[$index->ident]);
				    elseif ($index->type == 'time') {
				    	$ret[$index->ident] = $obj[$index->ident]->toDateTime()->format('Y-M-d H:i:s');//date('Y-M-d H:i:s',$obj[$index->ident]->sec);
						//echo $ret[$index->ident];
					}
				    else
				    	$ret[$index->ident] = $obj[$index->ident];
				}
				if (isset($obj['mean']))
			   		$ret['mean'] = $obj['mean']; 
				if (isset($obj['sd']))
				   	$ret['sd'] = $obj['sd'];
				
				if (isset($obj['res']) && $samples === false || intval($samples) > 0)
				{   	
					if ($samples === false || ! is_array($obj['res']) || (int)$samples >= (int)$act->samples)
				   		$ret['res'] = $obj['res']; 
					else
						$ret['res'] = array_slice($obj['res'], 0, $samples);
				}
				fwrite($handle, json_encode($ret));
			}
			fwrite($handle, "]");
			fclose($handle);
			
 		}
 	
 }
 
?>
