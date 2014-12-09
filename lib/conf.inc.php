<?php

define ('TPL_CONF', '
	    CREATE TABLE IF NOT EXISTS `%table%` (
		`param` varchar(32) NOT NULL,
		`value` varchar(300) BINARY NOT NULL,
		PRIMARY KEY `param` (`param`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE utf8_bin;
');

define ('CONF_TABLE', 'watermark_conf');


class TConf {

    public $parent;

    private $loger;

    private $sqlconn;

    private $defaults=array(
	'watermark_path' => '/home/user/images/watermark.png',
	'watermark_hash' => 'lksjd;lfkjewidklkfjsdlfjsldlskdfjals',
	'watermark_orientation' => 'CENTER',
	'images_dir' => '/home/user/images',
	'storage_dir' => '/home/user/storage',
	'watermark_transparent' =>  81);

    function __construct($parent) {
        $this->parent= (!empty($parent) ?  $parent : FALSE);
        $this->loger = (!empty($parent->loger) ? $parent->loger : FALSE);
        $this->sqlconn = (!empty($parent->sqlconn) ? $parent->sqlconn : FALSE);
    
	$this->CheckAndCreateTable();
    }
        
    function Log ($type, $message) {
        if ($this->loger) $this->loger->Write($this, $type, $message);
    }

    function CheckAndCreateTable(){
	$result=TRUE;
        $state=$this->sqlconn->query('SHOW TABLES FROM `'.MYSQL_DB.'` WHERE `Tables_in_'.MYSQL_DB.'`=\''.CONF_TABLE.'\'');
        if ($state->rowCount()==0){
            if ($this->sqlconn->query(str_replace('%table%', CONF_TABLE, TPL_CONF))) {
		$this->AddDefaultValues();
	    } else {
	     $result=FALSE;
	    }
        }
        return $result;
    }

    function AddDefaultValues(){
	$sql = 'INSERT INTO `'.CONF_TABLE.'` (param, value) VALUES (:param, :value)';
	$state = $this->sqlconn->prepare($sql);
	if ($state){
	    foreach ($this->defaults as $key => $value){
		$state->bindValue(':param', $key);
		$state->bindValue(':value', $value);
		$state->execute();
	    }
	}
    }

    function __get($name){
	$sql = 'SELECT value FROM `'.CONF_TABLE.'` WHERE param= :param_name';
	$state = $this->sqlconn->prepare($sql);
	if ($state) {
	    $state->bindValue(':param_name', $name);
	    if ($state->execute()){
		$param_val = $state->fetch();
		if ($param_val){
		    return $param_val['value'];
		}
	    }
	}
	return FALSE;
    }
    
    function __set($name, $value){
	$sql = 'UPDATE `'.CONF_TABLE.'` SET value=:value WHERE param=:param';
	$state = $this->sqlconn->prepare($sql);
	if ($state){
	    $state->bindValue(':value', $value);
	    $state->bindValue(':param', $name);
	    $state->execute();
	}
    }


}


?>