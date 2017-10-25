<?php
class StateObj
{
	function __construct($pclass,$tclass,$dclass,$iclass)
	{
	$this->state=array();	
	$this->callbacks=array();
	$this->current_state='';
	$this->process_class=$pclass;
	$this->telegram=$tclass;
	$this->database=$dclass;
	$this->invoice_class=$iclass;
	$this->validates=array();
	$this->fallbacks=array();
	}
	
	function addState($statename,$stateid=null,$initial=false)
	{
		$this->state[]=array("name"=>$statename,"id"=>$stateid,"initial"=>(int)$initial);
		return array("name"=>$statename,"id"=>$stateid,"initial"=>(int)$initial);
	}
	
	function addFallback($statename_from,$statename_to,$fallback)
	{
		if (!isset($this->fallbacks[$statename_from]))
		{
			$this->fallbacks[$statename_from]=array();
		}
		$this->fallbacks[$statename_from][$statename_to]=array("func"=>$fallback,"params"=>array());
	}
	
	function addValidate($statename_from,$statename_to,$validate)
	{
		if (!isset($this->validates[$statename_from]))
		{
			$this->validates[$statename_from]=array();
		}
		$this->validates[$statename_from][$statename_to]=array("func"=>$validate,"params"=>array());		
	}
	
	function addTransition($statename_from,$statename_to,$callback)
	{
		if (is_string($statename_from))
		{
			$state_start=$this->state[$this->findState($statename_from)];
		}
		else
		{
			$state_start=$this->state[$this->findState($statename_from['name'])];
		}
		if (is_string($statename_to))
		{
			$state_end=$this->state[$this->findState($statename_to)];
		}
		else
		{
			$state_end=$this->state[$this->findState($statename_from['name'])];
		}
		if ($state_start===FALSE)
		{
			$state_start=$this->addState($statename_from);
		}
		if ($state_end===FALSE)
		{
			$state_end=$this->addState($statename_to);
		}
		
		if (!isset($this->state[$this->findState($state_start['name'])]['to']))
		{
			$this->state[$this->findState($state_start['name'])]['to']=array();
		}
		$this->state[$this->findState($state_start['name'])]['to'][]=$state_end['name'];
		if (!isset($this->callbacks[$statename_from]))
		{
			$this->callbacks[$statename_from]=array();
		}
		$this->callbacks[$statename_from][$statename_to]=array("func"=>$callback,"params"=>array());
		//print_r($this->state);
	}
	function init($from,$to='')
	{
		if ($this->findState($from)===FALSE)
			return false;
		
		if ($to=='' && $this->findState($from)!==FALSE)
		{
			$this->current_state=$from;
		}
		else
		{
			echo "init_func: to and from used ($from -> $to )";
			$found_start=$this->state[$this->findState($from)];
			$found_end=$this->state[$this->findState($to)];
			if (in_array($to,$found_start['to']))
			{
				if (isset($this->callbacks[$from][$to]) && is_array($this->callbacks[$from][$to]))
				{
	//				call_user_func_array($this->process_class."::".);
					$obj=$this->process_class;
					
					$m_name=$this->callbacks[$from][$to]['func'];
	//				$obj->$m_name();
					$pars=$this->callbacks[$from][$to]['params'];
					$telegram=$this->telegram;
					//$pars=array_merge($pars,array("chat_id"=>$telegram->ChatID(),"old_state"=>$from,"new_state"=>$to,"old_state_id"=>$found_start['id'],"new_state_id"=>$found_to['id']);
					$tg_data=array("chat_id"=>$telegram->ChatID(),"old_state"=>$from,"new_state"=>$to,"old_state_id"=>$found_start['id'],"new_state_id"=>$found_end['id']);
					$tg_init=array("db"=>$this->database,"telegram"=>$this->telegram,"invoice"=>$this->invoice_class);
					call_user_func_array(array($this->process_class,"init"), $tg_init);
					call_user_func_array(array($this->process_class,"getData"), $tg_data);
					call_user_func_array(array($this->process_class,"connect_states"),array("state"=>$this));
					if (!isset($this->validates[$from]) && !isset($this->fallbacks[$from]))
					{
						call_user_func_array(array($this->process_class,$m_name), $pars);
					}
					else
					{
						if (isset($this->validates[$from]))
						{
							$v_name=$this->validates[$from][$to]['func'];
							$valid_res=call_user_func_array(array($this->process_class,$v_name), $pars);
							if ($valid_res!==FALSE)
							{
								echo "validation success";
								call_user_func_array(array($this->process_class,$m_name), $pars);
								$this->current_state=$to;
									global $db;
									$db->setUserState($telegram->ChatID(),$this->current_state);
							}
							else
							{	//Некорректный результат
								echo "validation fail";
								if (isset($this->fallbacks[$from][$to]))
								{
									$f_name=$this->fallbacks[$from][$to]['func'];
									call_user_func_array(array($this->process_class,$f_name), $pars);
									$this->current_state=$from; //Такой же
									global $db;
									    $chat_id = $telegram->ChatID();
									$raw_msg_data=$telegram->getData();
									if (isset($raw_msg_data['pre_checkout_query']))
									{
										$user_id=$raw_msg_data['pre_checkout_query']['from']['id'];
										$chat_id=$raw_msg_data['pre_checkout_query']['from']['id'];
									}
									else
									{
										$user_id= $telegram->UserID();
										$chat_id = $telegram->ChatID();
									}
									$db->setUserState($chat_id,$this->current_state);
								}

							}
						}
					}
									echo "called 3 func";
					
				}
				if (!isset($this->validates[$from][$to]) && !isset($this->fallbacks[$from][$to]))
				{
					$this->current_state=$to;
									global $db;
									$raw_msg_data=$telegram->getData();
									if (isset($raw_msg_data['pre_checkout_query']))
									{
										$user_id=$raw_msg_data['pre_checkout_query']['from']['id'];
										$chat_id=$raw_msg_data['pre_checkout_query']['from']['id'];
									}
									else
									{
										$user_id= $telegram->UserID();
										$chat_id = $telegram->ChatID();
									}									
									
									
									$db->setUserState($chat_id,$this->current_state);
//						$db->saveData($telegram->ChatID(),"state",);
				}	


			}
			else
				return false;
		}
	}
	public function init_any($from)
	{
		$to_arr=$this->state[$this->findState($from)]['to'];
		if (count($to_arr)==1)
		{
			$p=array_pop($to_arr);
			$this->init($from,$p);
		}
	}
	public function curState()
	{
		return $this->current_state;
	}
	
	public function loadCurState($chat_id)
	{
		$db=$this->database;
		return $db->userState($chat_id);
	}
	
	public function listTransitions($from=null)
	{
		if ($from==NULL)
		{
			$from=$this->current_state;
		}
			foreach ($this->state as $state)
			{
				if ($state['name']==$from)
				{
					$to=$state['to'];
					$result_array=array();
					foreach ($to as $name)
					{
						$result_array[]=$this->state[$this->findState($state_start['name'])];
					}
					return $result_array;
				}
			}
	}
	private function findState($statename)
	{

			foreach ($this->state as $skey=>$sitem)
			{
				if ($sitem['name']==$statename)
				{
					return $skey;
				}
			}
			return false;
	}
	
	
	public function getInitial()
	{
		foreach ($this->state as $state)
		{
			if ($state['initial']) return $state;
		}
		return false;
	}
}