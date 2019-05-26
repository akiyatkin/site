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
	if (!$path && isset($index[""])) $item = $index[""];
	else if ($path && isset($index[$path])) $item = $index[$path];
	else return http_response_code(404);
	
	if (isset($item['tpl'])) $tpl = Load::loadTEXT($item['tpl']);
	else $tpl = '';
	return Ans::html($tpl);
});


