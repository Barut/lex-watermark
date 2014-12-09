<?php

class CObject {


    public $parent;

    private $loger;

    
    function __construct($parent){
	$this->parent = (!empty($parent) ? $parent : FALSE);
    }

    function Log($type, $message) {
	if ($this->loger) $this->loger->Write($this, $type, $message);
    }

    function AssignLoger (ILoger $loger){
	$this->loger = (!empty($loger) ? $loger : FALSE);
    }

}


?>