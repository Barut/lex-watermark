<?php

class TWatermarker {

    public $parent;

    private $loger;

    private $sqlconn;



    public $updated = FALSE;    


    private $watermark_image_hash;

    private $orientation;

    private $transparent=100;

    function __construct($parent){
	$this->parent = (!empty($parent) ? $parent : FALSE);
	$this->loger = (!empty($parent->loger) ? $parent->loger : FALSE);
	$this->sqlconn = (!empty($parent->sqlconn) ? $parent->sqlconn : FALSE);
	$this->CheckWatermark();
    }

    function Log($type, $message){
	if ($this->loger) $this->loger->Write($this, $type, $message);
    }


    function CheckWatermark(){
	$this->Log('DEBUG','----- Check Watermark -----');
	if (is_file(WATERMARK_IMAGE)){
	    $this->watermark_image_hash=hash_file('crc32', WATERMARK_IMAGE);
	    if (is_file(WATERMARK_HASH)){
		if ($this->watermark_image_hash!==file_get_contents(WATERMARK_HASH)) $this->updated=TRUE;
	    } else {
		$this->SetHashWatermark();
	    }
	} else {
	    $this->Log('ERROR', 'Watermark image not found!!!');
	    exit(1);
	}
    }

    function SetHashWatermark(){
	$this->Log('DEBUG', '----- Set Hash Watermark -----');
	file_put_contents(WATERMARK_HASH, $this->watermark_image_hash);
    }

    function GetImageByExtension($filename, $type){
	$result=FALSE;
	switch ($type){
	    case 'jpeg':
		$result=imagecreatefromjpeg($filename);
		break;
	    case 'jpg':
		$result=imagecreatefromjpeg($filename);
		break;
	    case 'png':
		$result=imagecreatefrompng($filename);
		break;
	    case 'gif':
		$result=imagecreatefromgif($filename);
		$break;
	} 
	return $result;
    }
    
    function SaveImageByExtension($image, $filename, $type){
	$result=FALSE;
	switch ($type){
	    case 'jpeg':
		$result=imagejpeg($image, $filename);
		break;
	    case 'jpg':
		$result=imagejpeg($image, $filename);
		break;
	    case 'png':
		$result=imagepng($image, $filename);
		break;
	    case 'gif':
		$result=imagegif($image, $filename);
		$break;
	}
	return $result;
    }

    function MakeWatermark($file_info){
	$this->Log('DEBUG','----- Make watermark -----');
	$wat_im = $this->GetImageByExtension(WATERMARK_IMAGE, GetFileType(WATERMARK_IMAGE));
	if ($wat_im) {
	    $wat_im_wid = imagesx($wat_im);
	    $wat_im_hei = imagesy($wat_im);

	    $this->Log('DEBUG', 'Watermark width = '.$wat_im_wid);
	    $this->Log('DEBUG', 'Watermark height = '.$wat_im_hei);
	
	
	    $dest_im = $this->GetImageByExtension(IMAGE_DIR.$file_info['file_path'], $file_info['file_type']);
	    if ($dest_im){
		$dest_im_wid = imagesx($dest_im);
		$dest_im_hei = imagesy($dest_im);

		$this->Log('DEBUG', 'Original width = '.$dest_im_wid);
		$this->Log('DEBUG', 'Original height = '.$dest_im_hei);

		imagecopy($dest_im, $wat_im, $wat_im_wid+10, $wat_im_hei+10, 0,0,$wat_im_wid, $wat_im_hei);

		$this->SaveImageByExtension($dest_im, IMAGE_DIR.$file_info['file_path'], $file_info['file_type']);
	    }
	} else {
	    $this->Log('ERROR', '!!! Watermark image does not loaded !!!');
	}

    }

}


?>