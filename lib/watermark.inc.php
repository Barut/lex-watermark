<?php

class TWatermarker {

    public $parent;

    private $loger;



    private $img_watermark;

    private $img_original;

    private $img_result;

    private $orientation;

    private $transparent;


    function __construct($parent){
	$this->parent = (!empty($parent) ? $parent : FALSE);
	$this->loger = (!empty($parent->loger) ? $parent->loger : FALSE);
    }

    function Log($type, $message){
	if ($this->loger) $this->loger->Write($this, $type, $message);
    }

    
    function GetFileType($filename){
	$result='';
	$matches=array();
	if (preg_match('/\.([a-zA-Z]+)$/', $filename, $matches)) $result=strtolower($matches[1]);
	return $result;
    }


    function LoadWatermark ($filename){
	$this->img_watermark = $this->GetImageByExtension($filename);
    }

    function LoadImage ($filename){
	$this->img_original = $this->GetImageByExtension($filename);
    }

    function SetTransparentLevel($level){
	$this->transparent = (!$level ? 100 : $level);
    }

    function SetOrientation($orientation){
	$this->orientation=$orientation;
    }

    function SaveResult($filename){
	if ($this->img_original){
	    $this->SaveImageByExtension ($this->img_original, $filename);
	}
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


    function GetImageByExtension($filename){
	$result=FALSE;
	$type = $this->GetFileType($filename);
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
    
    function SaveImageByExtension($image, $filename){
	$result = FALSE;
	$type = $this->GetFileType($filename);
	switch ($type){
	    case 'jpeg':
		$result = imagejpeg($image, $filename);
		break;
	    case 'jpg':
		$result = imagejpeg($image, $filename);
		break;
	    case 'png':
		$result = imagepng($image, $filename);
		break;
	    case 'gif':
		$result = imagegif($image, $filename);
		$break;
	}
	return $result;
    }

    function WatermarkPosition (&$x, &$y){
	switch ($this->orientation){
	    case 'TOPLEFT':
		    $x=0; $y=0;
		    break;
	    case 'TOPCENTER':
		    $y=0;
		    $x=round((imagesx($this->img_original) / 2) - (imagesx($this->img_watermark) / 2));
		    break;
	    case 'TOPRIGHT':
		    $y=0;
		    $x=round(imagesx($this->img_original) - imagesx($this->img_watermark));
		    break;
	    case 'CENTERLEFT':
		    $x=0;
		    $y=round((imagesy($this->img_original) / 2) - (imagesy($this->img_watermark) / 2));
		    break;
	    case 'CENTER':
		    $x=round((imagesx($this->img_original) / 2) - (imagesx($this->img_watermark) / 2));
		    $y=round((imagesy($this->img_original) / 2) - (imagesy($this->img_watermark) / 2));
		    break;
	    case 'CENTERRIGHT':
		    $x=round(imagesx($this->img_original) - imagesx($this->img_watermark));
		    $y=round((imagesy($this->img_original) / 2) - (imagesy($this->img_watermark) / 2));
		    break;
	    case 'BOTTOMLEFT':
		    $x=0;
		    $y=round(imagesy($this->img_original) - imagesy($this->img_watermark));
		    break;
	    case 'BOTTOMCENTER':
		    $x=round((imagesx($this->img_original) / 2) - (imagesx($this->img_watermark) / 2));
		    $y=round(imagesy($this->img_original) - imagesy($this->img_watermark));
		    break;
	    case 'BOTTOMRIGHT':
		    $x=round(imagesx($this->img_original) - imagesx($this->img_watermark));
		    $y=round(imagesy($this->img_original) - imagesy($this->img_watermark));
		    break;
	}
    }

    function MakeWatermark(){
	if ($this->img_watermark && $this->img_original) {
	    $x = $y = 0;
	    $this->WatermarkPosition($x, $y);
	    $this->copyMerge($this->img_original, $this->img_watermark, 
		$x, $y, 
		0,0, 
		imagesx($this->img_watermark), imagesy($this->img_watermark), 
		$this->transparent
	    );
	}
    }

}


?>