<?php

class MyPDO {
	
	private $pdo=FALSE;
	
	private $loger=FALSE;
	
	public $parent=FALSE;
	
	function __construct($parent, $dsn, $username=NULL, $passwd=NULL, $options=NULL){
		$this->pdo=new PDO($dsn, $username, $passwd, $options);
		if (isset($parent)) $this->parent=$parent;
		if (isset($parent->loger)) $this->loger=$parent->loger;
	}
	
	function __call($name, $params){
		$this->loger->Write($this, 'DEBUG', 'Call magick method ['.$name.'] with params count ['.count($params).'] ');
		foreach ($params as $param){
			$this->loger->Write($this, 'DEBUG', 'Param - '.$param);
		}
		$retval=call_user_func_array(array($this->pdo, $name), $params);
		
		if ($retval){
			$this->loger->Write($this, 'DEBUG', 'Magick method executed and we have retval');
			if ($retval instanceof PDOStatement){
				$retval = new MyPDOStatement($this->parent, $retval);
			}
		} else {
			$this->loger->Write($this, 'DEBUG', 'Magick method doesn\'t executed or method return false');
			$err=$this->pdo->errorInfo();
			
			$this->loger->Write($this, 'DEBUG', 'SQLSTATE='.$err[0]);
			$this->loger->Write($this, 'DEBUG', 'DRIVER='.$err[1]);
			$this->loger->Write($this, 'DEBUG', 'TEXT='.$err[2]);
		}
		
		return $retval;
	}
}

class MyPDOStatement {
	private $state;

	private $parent;
	
	private $loger;
	
	function __construct($parent, PDOStatement $state){
		if (isset($parent)) $this->parent=$parent;
		if (isset($parent->loger)) $this->loger=$parent->loger;
		if (isset($state)) $this->state=$state;
	}
	
	function __call($name, $params){
		$this->loger->Write($this, 'DEBUG', 'Call magick method ['.$name.'] with params count ['.count($params).'] ');
		foreach ($params as $param){
			$this->loger->Write($this, 'DEBUG', 'Param - '.$param);
		}
		$retval=call_user_func_array(array($this->state, $name), $params);
		
		if ($retval){
			$this->loger->Write($this, 'DEBUG', 'Magick method executed and we have retval');
			if ($retval instanceof PDOStatement){
				$retval = new MyPDOStatement($this, $retval);
			}
		} else {
			$this->loger->Write($this, 'DEBUG', 'Magick method doesn\'t executed or method return false');
			$err=$this->state->errorInfo();
				
			$this->loger->Write($this, 'DEBUG', 'SQLSTATE='.$err[0]);
			$this->loger->Write($this, 'DEBUG', 'DRIVER='.$err[1]);
			$this->loger->Write($this, 'DEBUG', 'TEXT='.$err[2]);
		}
		
		return $retval;
	}
}



?>