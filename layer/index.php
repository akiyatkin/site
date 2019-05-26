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
	$index = Site::init();
	$path = Rest::getQuery();
	$json = Ans::GET('json','bool',false);
	if (!$path && isset($index["index"])) $item = $index["index"];
	else if ($path && isset($index[$path])) $item = $index[$path];
	else return http_response_code(404);
	

	if (isset($item['json']) && $json) {
		$layer = Load::loadTEXT($item['json']);
		$layer = sprintf($layer, $path);
		$layer = Load::json_decode($layer);
	} else {
		$layer = [
			'tpl' => '-site/get/'.$path,
			'childs' => []
		];
		if (isset($item['childs'])) {
			foreach ($item['childs'] as $ch) {
				$layer['childs'][$index[$ch]['nick']] = [
					"external" => "-site/layer/".$ch.'?json=1'
				];
			}
		}
		if (isset($item['data'])) {
			foreach ($item['data'] as $ch) {
				$layer['childs'][$index[$ch]['nick']] = [
					"tpl" => "-site/get/".$ch
				];
			}
		}
	}
	return Ans::ans($layer);
});


