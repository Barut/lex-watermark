<?php

require_once dirname(__FILE__).'/lib/encoding.inc.php';
require_once dirname(__FILE__).'/lib/watermark.inc.php';
require_once dirname(__FILE__).'/lib/hasher.inc.php';
require_once dirname(__FILE__).'/lib/storage.inc.php';
require_once dirname(__FILE__).'/lib/mod_pdo.inc.php';
require_once dirname(__FILE__).'/lib/loger.inc.php';
require_once dirname(__FILE__).'/lib/conf.inc.php';
require_once dirname(__FILE__).'/const.inc.php';


class TApp {

    public $loger;

    public $sqlconn;

    public $pathexcept;

    public $hasher;

    public $storage;

    public $watermarker;

    public $conf; 

    function __construct() {
	
        $this->loger = new TLoger(LOG_METHOD);
        $this->loger->SetMessageTypesToLog(LOG_TYPES);
        $this->loger->logfile=LOG_FILE;

	$this->sqlconn = new MyPDO($this, 'mysql:dbname='.MYSQL_DB.';host='.MYSQL_HOST, MYSQL_USER, MYSQL_PASS);
        if ($this->sqlconn) $this->loger->Write($this, 'DEBUG', 'MySQL connected');

	$this->conf = new TConf($this);


	$this->pathexcept = new THasherExceptions($this);
	$this->pathexcept->AddException('\.jpeg');
	$this->pathexcept->AddException('\.jpg');
	$this->pathexcept->AddException('\.png');

	

	$this->hasher = new THasher($this);
	$this->hasher->AssignExceptions($this->pathexcept);
    ////////////////////////////////////////////////////////////////////////
	$this->watermarker = new TWatermarker($this);

	if ($this->conf->watermark_path && is_file($this->conf->watermark_path)) { 
	    $this->watermarker->LoadWatermark($this->conf->watermark_path);
	} else {
	    $this->Log('ERROR', 'Watermark image not found, or does not set');
	    exit(1);
	}

	if ($this->conf->watermark_orientation) {
	    $this->watermarker->SetOrientation($this->conf->watermark_orientation);
	} else {
	    $this->Log('ERROR','Watermark orientation does not set');
	    exit(1);
	}

	$this->watermarker->SetTransparentLevel($this->conf->watermark_transparent);
    ///////////////////////////////////////////////////////////////////////
	$this->storage = new TStorage($this);
	
	if ($this->conf->images_dir && is_dir($this->conf->images_dir)){
	    $this->storage->SetOriginalHome($this->conf->images_dir);
	    $this->hasher->SetInitialDir($this->conf->images_dir);
	} else {
	    $this->Log('ERROR', 'Images dir not found');
	    exit(1);
	}

	if ($this->conf->storage_dir && is_dir($this->conf->storage_dir)){
	    $this->storage->SetStorageHome($this->conf->storage_dir);
	} else {
	    $this->Log('ERROR', 'Storage dir not found');
	    exit(1);
	}
    }

    function Log ($type, $message){
	if (!empty($this->loger)) $this->loger->Write($this, $type, $message);
    }

    function CheckWatermark(){
	if (is_file($this->conf->watermark_path) && hash_file('crc32',$this->conf->watermark_path)==$this->conf->watermark_hash){
	    $this->Log('NOTICE', 'Watermark file does not changed');
	    return TRUE;
	} else {
	    $this->Log('NOTICE', 'We have change for watermark file, and we must rebuild watermark from storage');
	    return FALSE;
	}
    }


    function RebuildWatermark(){
	$this->Log('NOTICE', 'We must update all images from storage');
	$state=$this->storage->GetFiles();
	if ($state){
	    $file_info=$state->fetch();
	    while ($file_info){
		
		$this->watermarker->LoadImage($this->conf->storage_dir.$file_info['storage_path']);
		$this->watermarker->MakeWatermark();
		$this->watermarker->SaveResult($this->conf->images_dir.$file_info['file_path']);
		
		$this->hasher->UpdateMainFileHash($file_info['file_path']);

		$file_info = $state->fetch();
	    }
	}
	$this->Log('NOTICE', 'After update all files, we must write new watermark hash');
	$this->conf->watermark_hash = hash_file('crc32',$this->conf->watermark_path);
    }

    function ProcessNewFiles(){
	$this->Log('NOTICE', 'Process new files in folder');
	$state = $this->hasher->GetNew();
	if ($state) {
	    $file_info = $state->fetch();
	    while ($file_info){
		if ($this->storage->Add($file_info['file_path'])){
		    $this->watermarker->LoadImage($this->conf->images_dir.$file_info['file_path']);
		    $this->watermarker->MakeWatermark();
		    $this->watermarker->SaveResult($this->conf->images_dir.$file_info['file_path']);

		    $this->hasher->UpdateNewFileHash($file_info['file_path']);
		}
		$file_info = $state->fetch();
	    }
	}
    }

    function ProcessChangedFiles(){
	$this->Log('NOTICE', 'Changed files');
	$state = $this->hasher->GetModified();
	if ($state) {
	    $file_info = $state->fetch();
	    while ($file_info) {
		$this->storage->Delete($file_info['file_path']);
		if ($this->storage->Add($file_info['file_path'])){
		    $this->watermarker->LoadImage($this->conf->images_dir.$file_info['file_path']);
		    $this->watermarker->MakeWatermark();
		    $this->watermarker->SaveResult($this->conf->images_dir.$file_info['file_path']);

		    $this->hasher->UpdateNewFileHash($file_info['file_path']);
		}
		$file_info = $state->fetch();
	    }
	}
    }

    function ProcessDeletedFiles(){
	$this->Log('NOTICE', 'Deleted files');
	$state = $this->hasher->GetDeleted();
	if ($state) {
	    $file_info = $state->fetch();
	    while ($file_info){
		$this->storage->Delete($file_info['file_path']);		

		$file_info = $state->fetch();
	    }
	}
    }

    function Start(){
	$this->Log ('NOTICE','============ AppStart =============');
	    if (!$this->CheckWatermark()){
		$this->RebuildWatermark();
	    }
	    $this->hasher->ScanDir();

	    $this->ProcessNewFiles();
	    $this->ProcessChangedFiles();
	    $this->ProcessDeletedFiles();
	$this->Log ('NOTICE','============ AppEnd ===============');
    }
}

$app= new TApp();
$app->Start();

?>