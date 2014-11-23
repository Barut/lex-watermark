<?php

require_once dirname(__FILE__).'/lib/common.inc.php';
require_once dirname(__FILE__).'/lib/encoding.inc.php';
require_once dirname(__FILE__).'/lib/watermark.inc.php';
require_once dirname(__FILE__).'/lib/hasher.inc.php';
require_once dirname(__FILE__).'/lib/mod_pdo.inc.php';
require_once dirname(__FILE__).'/lib/loger.inc.php';
require_once dirname(__FILE__).'/const.inc.php';
require_once dirname(__FILE__).'/conf.inc.php';


class TApp {

    public $loger;

    public $sqlconn;

    public $pathexcept;

    public $hasher;

    public $storage;

    public $watermarker;

    public $encoding;

    function __construct() {
	
        $this->loger = new TLoger(LOG_METHOD);
        $this->loger->SetMessageTypesToLog(LOG_TYPES);
        $this->loger->logfile=LOG_FILE;

	$this->sqlconn = new MyPDO($this, 'mysql:dbname='.MYSQL_DB.';host='.MYSQL_HOST, MYSQL_USER, MYSQL_PASS);
        if ($this->sqlconn) $this->loger->Write($this, 'DEBUG', 'MySQL connected');

	$this->encoding = new TEncodings($this);

	$this->watermarker = new TWatermarker($this);

	////////////
	$this->pathexcept = new THasherExceptions($this);
	$this->pathexcept->AddException('\.jpeg');
	$this->pathexcept->AddException('\.jpg');
	$this->pathexcept->AddException('\.png');

	////////////
	$this->storage = new THasherStorage($this);

	$this->hasher = new THasher($this);

	

    }

    function Log ($type, $message){
	if (isset($this->loger)) $this->loger->Write($this, $type, $message);
    }

    function Start(){
	$this->Log ('NOTICE','============ AppStart =============');

	$this->hasher->ScanDir(IMAGE_DIR);
	$this->storage->ProcessNewChanged();
	
	$this->Log ('NOTICE', '============ AppEnd ===============');
    }
}

$app= new TApp();
$app->Start();

?>
