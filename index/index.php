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
	if ($path) {
		if (isset($index[$path])) {
			$res = $index[$path];
			return Ans::ans($res);
		} else {
			return http_response_code(404);
		}
	}
	return Ans::ans($index);
});


