<?php
namespace akiyatkin\site;

use infrajs\rest\Rest;
use infrajs\ans\Ans;
use infrajs\load\Load;
use infrajs\path\Path;
use infrajs\access\Access;
use infrajs\config\Config;
use akiyatkin\fs\FS;
use infrajs\rubrics\Rubrics;

return Rest::get( function () {
	$path =  Rest::getQuery();
	if(!$path) $path = 'index';
	$index = Site::init();
	if (isset($index[$path])) {
		$group = $index[$path];
	} else {
		$group = false;
	}
	if(empty($group['items'])) {
		if (!empty($group['parent'])) {
			$gr = $index[$group['parent']];
		} else {
			$gr = $index['index'];
		}
	} else {
		$gr = $group;

	}
	$group['items'] = array_map(function ($path) use ($index, $group){
		if ($index[$path]['nick'] == $group['nick']) {
			$index[$path]['active'] = true;
		}
		return $index[$path];
	}, $gr['items']);
	
	return Ans::ans($group);
});


