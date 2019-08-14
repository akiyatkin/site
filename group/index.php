<?php
namespace akiyatkin\site;

use infrajs\rest\Rest;
use infrajs\ans\Ans;
use infrajs\load\Load;
use infrajs\path\Path;
use infrajs\sequence\Sequence;
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
	if ($path == 'index') $path = '';
	
	

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
	
	$bread = Sequence::right($path,'/');
	

	$crumbs = [];

	/*while (sizeof($bread)) {
		$p = Sequence::short($bread, '/');
		if (!$p) $p = 'index';
		$crumb = array_intersect_key($index[$p], array_flip(['name','path']));
		array_unshift($crumbs, $crumb);
		$b = array_pop($bread);
	};*/

	do {
		$p = Sequence::short($bread, '/');
		if (!$p) $p = 'index';
		if (!isset($index[$p])) {
			$crumb = [
				'path' => $p,
				'name' => 'Страница <b>'.$p.'</b> не найдена'
			];
		} else {
			$crumb = array_intersect_key($index[$p], array_flip(['name','path']));
		}

		array_unshift($crumbs, $crumb);

	} while ($b = array_pop($bread));
	$group['crumbs'] = $crumbs;	

	return Ans::ans($group);
});


