<?php 
function d1($data){
	echo '<pre style="background: #21201f; color: #f03737; padding: 10px; font-size: 15px; word-wrap: break-word;position: absolute;">';
	print_r($data);
	echo "</pre>";
	exit;
}
function d2($data){
	echo '<pre style="background: #21201f; color: #f03737; padding: 10px; font-size: 15px; word-wrap: break-word;position: absolute;">';
	var_dump($data);
	echo "</pre>";
	exit;
}

function d3($data){
	echo '<pre style="background: #21201f; color: #f03737; padding:5px; font-size: 15px; word-wrap: break-word;margin:0px !important;">';
	print_r($data);
	echo "</pre>";
}

