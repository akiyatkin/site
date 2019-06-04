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
	$index = Site::data();
	$groups = [];
	Site::runItems($index, function (&$group) use ($path, &$groups){
		$groups[$group['path']] = &$group;
		if ($group['path'] != $path ) return;
		while (!empty($group['path'])) {
			$group['active'] = true;
			$group = &$groups[$group['parent']];
		}
		return true;
	});
	return Ans::ans($index);
});


