<?php

class TLoger {
	
	public $log_method;
	
	public $logfile;
	
	private $log_format=array('console' => '[ %data% ] [%prog%] [ %msg_type% ] %msg%',
			'console_color' => '$red$ [%data%] $yellow$ [%prog%] $light_blue$ [%msg_type%] $white$ %msg% $default$',
			'file' => '[ %data% ] [%prog%] [ %msg_type% ] %msg%',
			'mysql' => '');
	
	private $log_date_pattern = 'Y-m-d H:i:s';

	private $myconnector;
	
	private $mtypes_to_log='';
	
	private $colors=array('$red$' => "\033[31m",
			'$green$' => "\033[32m",
			'$brown$' => "\033[33m",
			'$blue$' => "\033[34m",
			'$violet$' => "\033[35m",
			'$light_blue$' => "\033[36m",
			'$gray$' => "\033[37m",
			'$white$' => "\033[37;1m",
			'$yellow$' => "\033[33;1m",
			'$default$' => "\033[0m");
			
	function TLoger($method) {
		$this->log_method=$method;
	}
	////////////////////////////////////////////////////////////////
	function SetMessageTypesToLog($types)
	{
		$this->mtypes_to_log=$types;
	}
	////////////////////////////////////////////////////////////////
	function Write($object, $msg_type, $msg){
		$class=get_class($object);
		if (preg_match('/\|'.$msg_type.'\|/', $this->mtypes_to_log)){
		
			//=========================================================
			if (preg_match('/\|console\_color\|/', $this->log_method)){
				$this->ConsoleWrite($class, $msg, $msg_type, TRUE);
			}
			//=========================================================
			if (preg_match('/\|console\|/', $this->log_method)){
				$this->ConsoleWrite($class, $msg, $msg_type, FALSE);
			}
			//=========================================================
			if (preg_match('/\|file\|/', $this->log_method)){
				$this->FileWrite($class, $msg, $msg_type);
			}
			//=========================================================
			if (preg_match('/\|mysql\|/', $this->log_method)){
				$this->DBWrite($class, $msg, $msg_type);
			}
			//=========================================================
		}	
	}
	////////////////////////////////////////////////////////////////
	private function ConsoleWrite($class, $msg, $msg_type, $color){
		if ($color){
			$out=str_replace(array('%data%', '%prog%', '%msg_type%', '%msg%'),
					array(date($this->log_date_pattern), $class, $msg_type, $msg),
					$this->log_format['console_color']);
			$out2=str_replace(array_keys($this->colors),
					$this->colors, $out);
			$out2.=chr(10);
			fputs(STDERR, $out2);
		} else {
			$out=str_replace(array('%data%', '%prog%', '%msg_type%', '%msg%'),
					array(date($this->log_date_pattern), $class, $msg_type, $msg),
					$this->log_format['console']);
			$out.=chr(10);
			fputs(STDERR, $out);
		}
	}
	////////////////////////////////////////////////////////////////
	private function FileWrite($class, $msg, $msg_type){
		if ($fp=fopen($this->logfile, 'a')){
			$out=str_replace(array('%data%', '%prog%', '%msg_type%', '%msg%'),
					array(date($this->log_date_pattern), $class, $msg_type, $msg),
					$this->log_format['file']);
			$out.=PHP_EOL;
			fputs($fp, $out);
			fclose($fp);
		}
	}
	////////////////////////////////////////////////////////////////
	private function DBWrite ($class, $msg, $msg_type){
	
	}
	////////////////////////////////////////////////////////////////
	
}



?>