<?php

class TWatermarker {

    public $parent;

    private $loger;

    private $sqlconn;

    public $updated = FALSE;    


    private $watermark_image_hash;



    private $img_watermark;

    private $img_original;

    private $orientation;

    private $transparent=TRANSPARENT_LEVEL;

    function __construct($parent){
	$this->parent = (!empty($parent) ? $parent : FALSE);
	$this->loger = (!empty($parent->loger) ? $parent->loger : FALSE);
	$this->sqlconn = (!empty($parent->sqlconn) ? $parent->sqlconn : FALSE);
	$this->CheckWatermark();
    }

    function Log($type, $message){
	if ($this->loger) $this->loger->Write($this, $type, $message);
    }

    /**
    * Накладывает одно изображение на другое.
    * Дублирует функционал imagecopymerge из GD, т.к. та некорректно отрабатывает полностью прозрачные пиксели.
    *
    * @param resource $dstImg Изображение-подложка
    * @param resource $srcImg Изображение, которое накладываем
    * @param integer $dstX Смещение накладываемого изображения относительно подложки по X
    * @param integer $dstY Смещение накладываемого изображения относительно подложки по Y
    * @param integer $srcX Стартовая точка накладываемого изображения по X
    * @param integer $srcY Стартовая точка накладываемого изображения по Y
    * @param integer $srcW Ширина накладываемого изображения
    * @param integer $srcH Высота накладываемого изображения
    * @param integer $opacity Прозрачность накладываемого изображения
    * @return void
    */
    public function copyMerge($dstImg, $srcImg, $dstX, $dstY, $srcX, $srcY, $srcW, $srcH, $opacity = 0) {
	$dstW = imagesx($dstImg);
	$dstH = imagesy($dstImg);
	$srcW = min(imagesx($srcImg), $srcW);
	$srcH = min(imagesy($srcImg), $srcH);
	//перебираем пиксели $srcImg
	for($srcPointX = $srcX; $srcPointX < $srcW; $srcPointX++) {
	    for($srcPointY = $srcY; $srcPointY < $srcH; $srcPointY++) {
		//определяем соответствующие координаты в $dstImg
		$dstPointX = $dstX + $srcPointX - $srcX;
		$dstPointY = $dstY + $srcPointY - $srcY;

		//проверяем, не вышли ли за пределы $dstImg
		if($dstPointX >= 0 && $dstPointX < $dstW && $dstPointY >= 0 && $dstPointY < $dstH) {
		    //получаем RGB-цвет точки $srcImg
		    $srcIndex = imagecolorat($srcImg, $srcPointX, $srcPointY);
		    $srcData = imagecolorsforindex($srcImg, $srcIndex);

		    //полностью прозрачные пиксели игнорируем
		    if($srcData['alpha'] < 127) {
			//получаем RGB-цвет точки $dstImg
			$dstIndex = imagecolorat($dstImg, $dstPointX, $dstPointY);
			$dstData = imagecolorsforindex($dstImg, $dstIndex);

			//рассчитываем прозрачность точки $srcImg в долях единицы
			$srcAlpha = round(((127 - $srcData['alpha']) / 127), 2);
			$srcAlpha = $srcAlpha * $opacity / 100;

			//рассчитываем цветовые составляющие и прозрачность результирующего пикселя
			$avgRed = $this->getAverageColor($dstData['red'], $srcData['red'], $srcAlpha);
			$avgGreen = $this->getAverageColor($dstData['green'], $srcData['green'], $srcAlpha);
			$avgBlue = $this->getAverageColor($dstData['blue'], $srcData['blue'], $srcAlpha);
			$newAlpha = min($dstData['alpha'], $srcData['alpha']);

			//получаем индекс цвета из палитры
			$newColor = $this->getPaletteColor($dstImg, $avgRed, $avgGreen, $avgBlue, $newAlpha);

			//рисуем пиксель
			imagesetpixel($dstImg, $dstPointX, $dstPointY, $newColor);
		    }
		}
	    }
	}
    }

    /**
    * Возвращает результат сложения цветовых каналов
    *
    * @param integer $colorOne Первый цвет
    * @param integer $colorOne Второй цвет
    * @param integer $alpha Уровень прозрачности
    * @return integer
    */
    protected function getAverageColor($colorOne, $colorTwo, $alpha) {
	return round((($colorOne * (1 - $alpha)) + ($colorTwo * $alpha)));
    }

    /**
    * Ищет цвет в палитре, если такого нет - создает.
    *
    * @param resource $img Изображение
    * @param integer $r Красный
    * @param integer $g Зеленый
    * @param integer $b Синий
    * @param integer $alpha Уровень прозрачности
    * @return integer
    */
    protected function getPaletteColor($img, $r, $g, $b, $alpha) {
	$c = imagecolorexactalpha($img, $r, $g, $b, $alpha);
	if($c != -1) return $c;
	$c = imagecolorallocate($img, $r, $g, $b, $alpha);
	if($c != -1) return $c;
	return imagecolorclosest($img, $r, $g, $b, $alpha);
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
	$this->img_watermark = $this->GetImageByExtension(WATERMARK_IMAGE, GetFileType(WATERMARK_IMAGE));
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

		/*
		imagecopy($dest_im, $wat_im,
		    $wat_im_wid+10, 
		    $wat_im_hei+10, 
		    0,
		    0,
		    $wat_im_wid, 
		    $wat_im_hei);
		*/
	    
		$this->copyMerge();


		$this->SaveImageByExtension($dest_im, IMAGE_DIR.$file_info['file_path'], $file_info['file_type']);
	    }
	} else {
	    $this->Log('ERROR', '!!! Watermark image does not loaded !!!');
	}

    }

}


?>