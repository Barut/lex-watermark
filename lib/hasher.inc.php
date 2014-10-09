<?php

class THasher {
	
	public $parent; //parent -> TApp
	
	private $loger; // TLoger

	private $pathexcept; // THasherExceptions

	private $storage; // THasherStorage
	
	private $file_list = array();
	
	private $encoding; //TEncodings
	
	/////////////////////////////////////////////////////////////////////
	function THasher($parent) {
		$this->parent= (!empty($parent) ?  $parent : FALSE);
		$this->loger = (!empty($parent->loger) ? $parent->loger : FALSE);
		$this->pathexcept = (!empty($parent->pathexcept) ? $parent->pathexcept : FALSE);
		$this->storage = (!empty($parent->storage) ? $parent->storage : FALSE);
		$this->encoding = (!empty($parent->encoding) ? $parent->encoding : FALSE);
	}
	
	function Log ($type, $message) {
		if ($this->loger) $this->loger->Write($this, $type, $message);
	}

	/////////////////////////////////////////////////////////////////////
	function ScanDir($initial_dir, $dir=''){
		$dh = opendir($initial_dir.$dir);
		if ($dh){
			$file = readdir($dh);
			while ($file){
				if (($file != '.') && ($file != '..')){
					if (is_dir($initial_dir.$dir.'/'.$file) && !is_link($initial_dir.$dir.'/'.$file)) {
						$this->ScanDir($initial_dir, $dir.'/'.$file);
					} else {
						///////////////////////////////////////
						if ($this->pathexcept && $this->pathexcept->CheckException($dir.'/'.$file)){
							
							$this->file_list[]=array('file_hash' => hash_file('crc32',$initial_dir.$dir.'/'.$file),
								'file_size' => filesize($initial_dir.$dir.'/'.$file),
								'file_path' => $this->encoding->DetectEnc($dir.'/'.$file),
								'file_type' => GetFileType($file)
							);
							
						}
						///////////////////////////////////////
						if (count($this->file_list) >= MAX_INPUT){
							if ($this->storage) $this->storage->Insert($this->file_list);
							$this->file_list=array();
						}
					}
				}
		
				$file = readdir($dh);
			}
			if (count($this->file_list) !=0){
				if ($this->storage) $this->storage->Insert($this->file_list);
				$this->file_list=array();
			}
			closedir($dh);
		}
	}
	////////////////////////////////////////////////////////////////////////
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


class THasherStorage {

	public $parent;

	private $loger;

	private $sqlconn;
	
	private $watermarker;


    
	private $file_info = array();


	function __construct ($parent){
		$this->parent = (!empty($parent) ? $parent : FALSE);
		$this->loger = (!empty($parent->loger) ? $parent->loger : FALSE);
		$this->sqlconn = (!empty($parent->sqlconn) ? $parent->sqlconn : FALSE);
		$this->watermarker = (!empty($parent->watermarker) ? $parent->watermarker : FALSE);

		$this->InitStorage();
	}

	function Log ($type, $message){
		if ($this->loger) $this->loger->Write($this, $type, $message);
	}

	function InitStorage (){
	    //проверить существуют ли таблицы если их нет, то необходимо создать
	    $this->CheckDirs();
	    if ($this->CheckTables()) {

		if ($this->watermarker->updated) $this->MoveFromOriginal();
    
		$this->RotateTables();
	    }
	}

	function CheckDirs(){
	    if (!is_dir(IMAGE_DIR)){
		$this->Log('ERROR', '!!! Images dir does not exists !!!');
	    }
	    if (!is_dir(ORIGINALS_DIR)) mkdir(ORIGINALS_DIR);

	}


	function CheckTables(){
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
		$res=$state->fetch();
		if ($res && $res[0]!==0) {
		    $state=$this->sqlconn->query('TRUNCATE TABLE `'.MAIN_TABLE.'`');
            
		    $state=$this->sqlconn->query('INSERT INTO `'.MAIN_TABLE.'` SELECT * FROM `'.NEW_TABLE.'`');

		    $state=$this->sqlconn->query('TRUNCATE TABLE `'.NEW_TABLE.'`');
		}

	}

	function Insert ($files_array) {
		$sql='INSERT INTO `'.NEW_TABLE.'` (file_path, file_hash, orig_hash, file_type, file_size) VALUES ( :file_path, :file_hash, :file_hash, :file_type, :file_size)';
		$state=$this->sqlconn->prepare($sql);
		if ($state) {
		    foreach ($files_array as $a_file){
			//$this->Log('DEBUG', '---- file '.$a_file['file_path']);
			$state->bindValue(':file_path', $a_file['file_path']);
			$state->bindValue(':file_hash', $a_file['file_hash']);
			$state->bindValue(':file_type', $a_file['file_type']);
			$state->bindValue(':file_size', $a_file['file_size']);
			$state->execute();
		    }
		}
	}

	function ProcessNewChanged(){
	    $this->Log('DEBUG','----- Start processing new and changed files -----');

	    //NEW
	    //SELECT * FROM `watermark_main` RIGHT JOIN `watermark_new` 
	    //ON `watermark_main`.`file_hash`=`watermark_new`.`file_hash` 
	    //WHERE `watermark_main`.`file_hash` IS NULL

	    $sql='
		    SELECT `watermark_new`.`file_hash`, `watermark_new`.`file_path`, `watermark_new`.`file_type` 
		    FROM `watermark_main` RIGHT JOIN `watermark_new`
		    ON `watermark_main`.`file_hash`=`watermark_new`.`file_hash`
		    WHERE `watermark_main`.`file_hash` IS NULL
	    ';

	    $state=$this->sqlconn->query($sql);
	    if ($state && $state->rowCount()!=0){
		$this->file_info=$state->fetch();
		while ($this->file_info){
		    $this->ProcessFile();
		    $this->file_info=$state->fetch();
		}
	    }

	    $sql='
		SELECT `watermark_new`.`file_hash`, `watermark_new`.`file_path`, `watermark_new`.`file_type` 
		FROM `watermark_new` INNER JOIN `watermark_main` 
		ON `watermark_new`.`file_path`=`watermark_main`.`file_path`
		WHERE `watermark_new`.`file_hash` <> `watermark_main`.`file_hash`
	    ';

	    $state=$this->sqlconn->query($sql);
	    if ($state && $state->rowCount()!=0){
		$this->file_info=$state->fetch();
		while ($this->file_info) {
		    $this->ProcessFile();
		    $this->file_info=$state->fetch();
		}
	    }

	}


	function ProcessFile(){
	    $this->Log('DEBUG', '----- Process file -----');
	    if ($this->CopyOriginal()){
		$this->watermarker->MakeWatermark($this->file_info);
		$this->UpdateHash();
	    }
	}

	function CopyOriginal(){
	    $result=FALSE;
	    $this->Log('DEBUG', '---- Copy original file to ORGINALS dir -----');
	    $orig_file=ORIGINALS_DIR.'/'.$this->file_info['file_hash'].'.'.$this->file_info['file_type'];
	    if (copy(IMAGE_DIR.$this->file_info['file_path'], $orig_file)){
		$this->Log('NOTICE', 'Original file '.$this->file_info['file_path'].' stored in '.$orig_file);
		$result=TRUE;
	    }
	    return $result;
	}

	function UpdateHash(){
	    $this->Log('DEBUG', '---- Update hash after watermark file ----');
	    $sql='
		UPDATE `watermark_new` SET `file_hash`= :new_hash WHERE `file_hash`= :old_hash
	    ';
	    $state=$this->sqlconn->prepare($sql);
	    if ($state) {
		$state->bindValue(':new_hash', hash_file('crc32', IMAGE_DIR.$this->file_info['file_path']));
		$state->bindValue(':old_hash', $this->file_info['file_hash']);
		$state->execute();
	    }
	}

	function MoveFromOriginal(){
	    $this->Log('DEBUG', '----- Watermark was changed and we must make update files ----');
	    $sql='
		SELECT `file_hash`, `file_type`, `file_path` FROM `watermark_new`
	    ';
	    $state=$this->sqlconn->query($sql);
	    if ($state){
		$this->file_info=$state->fetch();
		while ($this->file_info){
		    $orig_file=ORIGINALS_DIR.'/'.$this->file_info['file_hash'].'.'.$this->file_info['file_type'];
		
		    if (is_file($orig_file) && rename(IMAGE_DIR.$this->file_info['file_path'])) {
			$this->Log('NOTICE', 'Original file was moved to '.IMAGE_DIR.$this->file_info['file_path']);
		    }
		    $this->file_info=$state->fetch();
		}
	    }
	    
	    $this->watermarker->SetHashWatermark();
	    
	}

}


?>
