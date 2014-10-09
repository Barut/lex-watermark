<?php


function GetFileType($filename){
	$result='';
	$matches=array();
	if (preg_match('/\.([a-zA-Z]+)$/', $filename, $matches)) $result=strtolower($matches[1]);
	return $result;
}


?>