<?php

define ('MAX_INPUT', 50);
define ('HASH_TYPE', 'crc32');
define ('NEW_TABLE', 'watermark_new');
define ('MAIN_TABLE', 'watermark_main');

define('TPL_TABLE', '
                CREATE TABLE IF NOT EXISTS `%table%` (
                `file_hash` varchar(8) NOT NULL,
                `file_path` varchar(300) BINARY NOT NULL,
                `file_size` int(11) NOT NULL,
                KEY `file_path` (`file_path`),
		KEY `file_hash` (`file_hash`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE utf8_bin;
');


define('TPL_SQL_NEW','
                SELECT `watermark_new`.`file_path` 
                FROM `watermark_main` RIGHT JOIN `watermark_new`
                ON `watermark_main`.`file_path`=`watermark_new`.`file_path`
                WHERE `watermark_main`.`file_path` IS NULL
');

define('TPL_SQL_CHANGED','
            SELECT `watermark_new`.`file_path` 
            FROM `watermark_new` INNER JOIN `watermark_main` 
            ON `watermark_new`.`file_path`=`watermark_main`.`file_path`
            WHERE `watermark_new`.`file_hash` <> `watermark_main`.`file_hash`
');

define('TPL_SQL_DELETED','
	    SELECT `watermark_main`.`file_path` 
	    FROM `watermark_main` LEFT JOIN `watermark_new`
	    ON `watermark_main`.`file_path`=`watermark_new`.`file_path`
	    WHERE `watermark_new`.`file_path` IS NULL
');



class THasher {

	
	public $parent; //parent -> TApp
	
	protected $loger; // TLoger

	private $pathexcept; // THasherExceptions

	private $initial_dir; 

	// created inside class
	    
	private $encoding; //TEncodings

	private $file_list = array();
	
	/////////////////////////////////////////////////////////////////////
	function THasher($parent) {
	    $this->parent= (!empty($parent) ?  $parent : FALSE);
	    $this->loger = (!empty($parent->loger) ? $parent->loger : FALSE);
	    $this->sqlconn = (!empty($parent->sqlconn) ? $parent->sqlconn : FALSE);

	    $this->encoding = new TEncodings($this);

	    if ($this->CheckAndCreateTables()){
		$this->RotateTables();
	    }
	}
	
	function Log ($type, $message) {
	    if ($this->loger) $this->loger->Write($this, $type, $message);
	}

	/////////////////////////////////////////////////////////////////////
	function ScanDir($dir=''){
	    $dh = opendir($this->initial_dir.$dir);
	    if ($dh){
		$file = readdir($dh);
		while ($file){
		    if (($file != '.') && ($file != '..')){
			if (is_dir($this->initial_dir.$dir.'/'.$file) && !is_link($this->initial_dir.$dir.'/'.$file)) {
			    $this->ScanDir($dir.'/'.$file);
			} else {
			///////////////////////////////////////
			    if ($this->pathexcept && $this->pathexcept->CheckException($dir.'/'.$file)){			
				$this->file_list[]=array('file_hash' => hash_file(HASH_TYPE, $this->initial_dir.$dir.'/'.$file),
							'file_size' => filesize($this->initial_dir.$dir.'/'.$file),
							'file_path' => $this->encoding->DetectEnc($dir.'/'.$file)
							);
							
			    }
			///////////////////////////////////////
			    if (count($this->file_list) >= MAX_INPUT){
				    $this->ProcessFiles();
				    $this->file_list=array();
			    }
			}
		    }
		
		    $file = readdir($dh);
		}
		
		if (count($this->file_list) !=0){
		    $this->ProcessFiles();
		    $this->file_list=array();
		}
		
		closedir($dh);
	    
	    } else {
		$this->Log('ERROR', 'can\'t get dir handel');
	    }
	}

	function SetInitialDir($directory){
	    $this->initial_dir = $directory;
	}

	function AssignExceptions(THasherExceptions $exceptions){
	    $this->pathexcept = (!empty($exceptions) ? $exceptions : FALSE);
	}

	////////////////////////////////////////////////////////////////////////
	function ProcessFiles(){
	    $sql='INSERT INTO `'.NEW_TABLE.'` (file_path, file_hash, file_size) 
    				    VALUES ( :file_path, :file_hash, :file_size)';
    	    $state=$this->sqlconn->prepare($sql);
    	    if ($state) {
        	foreach ($this->file_list as $a_file){
        	    $state->bindValue(':file_path', $a_file['file_path']);
        	    $state->bindValue(':file_hash', $a_file['file_hash']);
        	    $state->bindValue(':file_size', $a_file['file_size']);
        	    $state->execute();
        	}
    	    }

	}

	function CheckAndCreateTables(){
	    $result=TRUE;
    	    $state=$this->sqlconn->query('SHOW TABLES FROM `'.MYSQL_DB.'` WHERE `Tables_in_'.MYSQL_DB.'`=\''.MAIN_TABLE.'\'');
    	    if ($state->rowCount()==0){
        	if (!$this->sqlconn->query(str_replace('%table%', MAIN_TABLE, TPL_TABLE))) $result=FALSE;
    	    }
        
    	    $state=$this->sqlconn->query('SHOW TABLES FROM `'.MYSQL_DB.'` WHERE `Tables_in_'.MYSQL_DB.'`=\''.NEW_TABLE.'\'');
    	    if ($state->rowCount()==0){
        	if (!$this->sqlconn->query(str_replace('%table%', NEW_TABLE, TPL_TABLE))) $result=FALSE;
    	    }
        
    	    return $result;
	}

	function RotateTables(){
        //совершить ротацию таблиц
    	    $state=$this->sqlconn->query('SELECT COUNT(*) FROM `'.NEW_TABLE.'`');
    	    $res = $state->fetch();
    	    if ($res && $res[0]!=0) {

		$state=$this->sqlconn->query('TRUNCATE TABLE `'.MAIN_TABLE.'`');
		if ($state) {
		    $state->closeCursor();
		}

        	$state=$this->sqlconn->query('INSERT INTO `'.MAIN_TABLE.'` SELECT * FROM `'.NEW_TABLE.'`');
		if ($state) {
		    $state->closeCursor();
		}

        	$state=$this->sqlconn->query('TRUNCATE TABLE `'.NEW_TABLE.'`');
		if ($state) {
		    $state->closeCursor();
		}

    	    } else {
		$this->Log('NOTICE', 'Nothing rotate. Table - '.NEW_TABLE.' clear');
	    }
	}


	function GetNew(){
	    $result = FALSE;
            $state = $this->sqlconn->query(TPL_SQL_NEW);
	    if ($state && $state->rowCount()!=0){
    		$result = $state;
    	    }
	    return $result;
	}

	function GetModified(){
    	    $result = FALSE;
	    $state=$this->sqlconn->query(TPL_SQL_CHANGED);
    	    if ($state && $state->rowCount()!=0){
        	$result = $state;
    	    }
	    return $result;
	}

	function GetDeleted(){
	    $result = FALSE;
	    $state=$this->sqlconn->query(TPL_SQL_DELETED);
    	    if ($state && $state->rowCount()!=0){
        	$result = $state;
    	    }
	    return $result;
	}

	private function UpdateFileHash ($file, $hash, $table){
	    $sql = 'UPDATE `'.$table.'` SET file_hash=:file_hash WHERE file_path=:file_path';
	    $state=$this->sqlconn->prepare($sql);
	    $state->bindValue(':file_hash', $hash);
	    $state->bindValue(':file_path', $file);
	    return ($state->execute() ? TRUE : FALSE); 
	}

	public function UpdateMainFileHash ($file){
	    $this->UpdateFileHash($file, 
				hash_file(HASH_TYPE, $this->initial_dir.'/'.$file), 
				MAIN_TABLE);
	}

	public function UpdateNewFileHash ($file){
	    $this->UpdateFileHash($file, 
				hash_file(HASH_TYPE, $this->initial_dir.'/'.$file), 
				NEW_TABLE);
	}

}


class THasherExceptions {

	public $parent;

	private $loger;

	private $exceptions = array();

	function __construct ($parent){
		$this->parent = (!empty($parent) ? $parent : FALSE);
		$this->loger = (!empty($parent->loger) ? $parent->loger : FALSE);
	}

	function Log ($type, $message){
		if ($this->loger) $this->loger->Write($this, $type, $message);
	}

	function AddException ($exception){
	    $this->exceptions[]='/'.$exception.'/i';
	}

	function CheckException ($filename){
		$result=FALSE;
		foreach ($this->exceptions as $exception){
		    if (preg_match($exception, $filename)) $result=TRUE;
		}
		return $result;
	}

}

?>
