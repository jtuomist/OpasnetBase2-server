<?php
/*
 * Created on 19.3.2012
 *
 * To change the template for this generated file go to
 * Window - Preferences - PHPeclipse - PHP - Code Templates
 */
 
 require_once dirname(__FILE__).'/opasnet_base_active_record.php';
 require_once dirname(__FILE__).'/opasnet_base_act.php';
 
 class OpasnetBaseObject extends OpasnetBaseActiveRecord
 {

		const TABLE_NAME = "objs";

		function validate()
		{
			$errors = array();
			if (isset($this->data['subset']) && (! isset($this->data['subset_name']) || $this->data['subset_name'] == ''))
				$errors[] = 'subset name must not be empty';
			if (!isset($this->data['name']) or trim($this->data['name']) == '')
				$errors[] = 'name must not be empty';
			if (!isset($this->data['ident']) or trim($this->data['ident']) == '')
				$errors[] =  'ident must not be empty';
			if (!isset($this->data['type']) or trim($this->data['type']) == '')
				$errors[] = 'type must not be empty';
			if (!isset($this->data['page']) or intval($this->data['page']) < 1)
				$errors[] = 'page must be a number larger than zero';
			if (!isset($this->data['wiki_id']) or intval($this->data['wiki_id']) < 1)
				$errors[] = 'wiki_id must be a number larger than zero';
			else
			{
				$wikis = OpasnetBaseWiki::find_all($this->mysql_link, $this->mongo_link);
				$ids = array();
				foreach ($wikis as $w)
					$ids[] = $w->id;
				if (! in_array(intval($this->data['wiki_id']), $ids))
					$errors[] = 'wiki_id does not match any existing wiki';
			}
			
			if (! empty($errors))
				throw new Exception('Object validation failed: '.join(', ',$errors));
		}
		
		/* Find most recent act for series */
		function find_series_act($id)
		{
			$acts = OpasnetBaseAct::find_all($this->mysql_link, $this->mongo_link, array('where'=>array('series_id = ?',$id), 'order' => 'id DESC', 'limit' => '1'));
			$act = array_pop($acts);
			$act->base_object = $this;
			return $act;
		}


		/* Find most recent act or id identified */
		function find_act($id = null)
		{
			if ($id !== null)
				$act = new OpasnetBaseAct($this->mysql_link, $this->mongo_link, (int)$id);
			else
			{
				$acts = OpasnetBaseAct::find_all($this->mysql_link, $this->mongo_link, array('where'=>array('obj_id = ?',$this->id), 'order' => 'id DESC', 'limit' => '1'));
				$act = array_pop($acts);
			}
			$act->base_object = $this;
			return $act;
		}
		
		function find_acts()
		{
			$acts = OpasnetBaseAct::find_all($this->mysql_link, $this->mongo_link, array('where'=>array('obj_id = ?',$this->id), 'order' => 'id DESC'));
			foreach ($acts as $act)
				$act->base_object = $this;
			return $acts;			
		}
		
		function find_wiki()
		{
			$wikis = OpasnetBaseWiki::find_all($this->mysql_link, $this->mongo_link, array('where'=>array('id = ?',$this->wiki_id),'limit' => '1'));
			return array_pop($wikis);			
		}
				
		function results_count($act_id, $params)
		{
			$act = $this->find_act((int)$act_id);
			$sum = 0;
			$acts = OpasnetBaseAct::find_all($this->mysql_link, $this->mongo_link, array('where'=>array('obj_id = ? AND series_id = ? AND id <= ?',$this->id, $act->series_id, $act->id)));
			
			#return $this->id.','.$act->series_id.','.$act->id;
			
			if (count($acts) == 0)
				throw new Exception('results_count: zero acts!!!'.$this->id.','.$act->series_id.','.$act->id);
			
			# Take a shortcut here for efficiency, not necessarily correct if previous acts of this series have different sample counts
			if ($act->samples == 1)
				return $act->series_cell_count($params);	
			
			if (isset($params['samples']))
				$samples = $params['samples'];
			else
				$samples = null;
			
			foreach ($acts as $act)
			{
				$act->base_object = $this;
				if ($samples !== null && (int)$samples < (int)$act->samples)
					$s = $samples;
				else
					$s = $act->samples;
				$sum += $act->cell_count($params)*(int)$s;
			}
			return $sum;
		}
		
 	
 }
 
?>
