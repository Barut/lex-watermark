<?php

define ('TPL_ORIGINALS','

                CREATE TABLE IF NOT EXISTS `%table%` (
		`path_hash` varchar(32) BINARY NOT NULL,
		`file_path` varchar(300) BINARY NOT NULL,
                `file_hash` varchar(8) NOT NULL,
                `file_size` int(11) NOT NULL,
		`storage_path` varchar(300) BINARY NOT NULL,
		PRIMARY KEY `path_hash` (`path_hash`),
                KEY `file_path` (`file_path`),
		KEY `file_hash` (`file_hash`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE utf8_bin;

');

define ('ORIGINALS_TABLE', 'watermark_originals');



class TStorage {


    public $parent;

    private $loger;

    private $sqlconn;

    private $storage_home;

    private $original_home;


    function __construct($parent) {
	$this->parent= (!empty($parent) ?  $parent : FALSE);
	$this->loger = (!empty($parent->loger) ? $parent->loger : FALSE);
	$this->sqlconn = (!empty($parent->sqlconn) ? $parent->sqlconn : FALSE);

	if (!$this->CheckAndCreateTable()) exit(1);
    }

    function Log ($type, $message) {
	if ($this->loger) $this->loger->Write($this, $type, $message);
    }

    function CheckAndCreateTable(){
        $result=TRUE;
        $state=$this->sqlconn->query('SHOW TABLES FROM `'.MYSQL_DB.'` WHERE `Tables_in_'.MYSQL_DB.'`=\''.ORIGINALS_TABLE.'\'');
        if ($state->rowCount()==0){
            if (!$this->sqlconn->query(str_replace('%table%', ORIGINALS_TABLE, TPL_ORIGINALS))) $result=FALSE;
        }
	return $result;
    }

    function GetFileType($filename){
        $result='';
        $matches=array();
        if (preg_match('/\.([^\.]+)$/', $filename, $matches)) $result=strtolower($matches[1]);
        return $result;
    }
    
    function SetStorageHome($dirname){
	$this->storage_home = (is_dir($dirname) ? $dirname : FALSE);
    }

    function SetOriginalHome($dirname){
	$this->original_home = (is_dir($dirname) ? $dirname : FALSE);
    }

    function Add ($filepath){
	$sql='INSERT INTO `'.ORIGINALS_TABLE.'` (path_hash, file_path, file_hash, file_size, storage_path)
                                    VALUES ( :path_hash, :file_path, :file_hash, :file_size, :storage_path)';
        $state=$this->sqlconn->prepare($sql);
        if ($state) {
	    $storage_path = '/'.md5($filepath).'.'.$this->GetFileType($filepath);
	    $state->bindValue(':path_hash', md5($filepath));
    	    $state->bindValue(':file_path', $filepath);
            $state->bindValue(':file_hash', hash_file('crc32', $this->original_home.$filepath));
            $state->bindValue(':file_size', filesize($this->original_home.$filepath));
	    $state->bindValue(':storage_path', $storage_path);
            if ($state->execute()){
		if (copy($this->original_home.$filepath, $this->storage_home.$storage_path)){
		    $this->Log('NOTICE', 'File - '.$filepath.' just copied to - '.$storage_path);
		    return TRUE;
		} else {
		    $this->Log('ERROR', 'Can not copy file to storage dir - '.$filepath);
		}
	    }
        }
	return FALSE;
    }

    function GetFile ($filepath){
	$sql = 'SELECT storage_path FROM `'.ORIGINALS_TABLE.'` WHERE file_path=:file_path';
	$state = $this->sqlconn->prepare($sql);
	if ($state) {
	    $state->bindValue(':file_path', $filepath);
	    if ($state->execute()){
		$path = $state->fetch(PDO::FETCH_ASSOC);
		return $path['storage_path'];
	    }
	}
	return FALSE;
    }

    function GetInfo ($filepath){
	$sql = 'SELECT file_path, file_hash, file_size, storage_path FROM `'.ORIGINALS_TABLE.'` WHERE file_path=:file_path';
	$state = $this->sqlconn->prepare($sql);
	if ($state) {
	    $state->bindValue(':file_path', $filepath);
	    if ($state->execute()) return $state->fetch(PDO::FETCH_ASSOC);
	}
	return FALSE;
    }

    function Delete ($filepath){
	$file = $this->GetFile($filepath);

	$sql='DELETE FROM `'.ORIGINALS_TABLE.'` WHERE file_path = :file_path';
	$state=$this->sqlconn->prepare($sql);
	if ($state) {
	    $state->bindValue(':file_path', $filepath);
	    if ($state->execute()){
		if (unlink($this->storage_home.$file)){
		    $this->Log('NOTICE', 'We remove file - '.$file.' associated to -'.$filepath);
		} else {
		    $this->Log('ERROR', 'Can not unlink '.$filepath);
		}
	    }
	}
    }

    function Clean(){
	$sql = 'SELECT file_path FROM `'.ORIGINALS_TABLE.'`';
	$state = $this->sqlconn->query($sql);
	if ($state){
	    $file = $state->fetch();
	    while ($file){
		$this->Delete($file['file_path']);
		$file = $state->fetch();
	    }
	}
    }

    function GetFiles(){
	$result=FALSE;
	$sql='SELECT * FROM `'.ORIGINALS_TABLE.'`';
	$state = $this->sqlconn->query($sql);
	if ($state){
	    $result=$state;
	}
	return $result;
    }
}



?>
