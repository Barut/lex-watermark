<?php

class TEncodings {

	public $parent;

	private $loger;
	
	private $encodings = array('windows-1251', 'koi8-r', 'iso8859-5');

	function __construct($parent){
	    $this->parent = (!empty($parent) ? $parent : FALSE);
	    $this->loger = (!empty($parent->loger) ? $parent->loger : FALSE);

	    foreach ($this->encodings as $encoding){
                        $this->specters[$encoding] = require dirname(__FILE__).'/specters/'.$encoding.'.php';
            }
	}

	function Log ($type, $message){
	    if ($this->loger) $this->loger->Write($this, $type, $message);
	}

	function DetectEnc($string){

                if (preg_match('#.#u', $string)==0){
                        //====== NOT UTF-8
                    foreach ($this->encodings as $encoding) $weights[$encoding] = 0;
                	for ($i = 0; $i < strlen($string) - 1; $i++){
                                $key = substr($string, $i, 2);
                                foreach ($this->encodings as $encoding){
                                        if (isset($this->specters[$encoding][$key])) $weights[$encoding]+=$this->specters[$encoding][$key];
                                }
                        }
                        $sum_weight = array_sum($weights);
                        $enc=''; $max=0;

                        if ($sum_weight!=0){
                                foreach ($weights as $encoding => $weight) {
                                        $weights[$encoding] = $weight / $sum_weight;
                                        if ($weights[$encoding]>$max){
                                                $enc=$encoding; $max=$weights[$encoding];
                                        }
                                }
                        }
                        //$this->Log('DEBUG', 'For sting ['.$string.'] we detect encoding ['.$enc.']');
                        if ($enc!='') $string=iconv($enc, 'UTF-8//IGNORE', $string);
                } else {
                    //$this->Log('DEBUG', 'String is UTF-8');
                }
                return $string;
        }

}




?>