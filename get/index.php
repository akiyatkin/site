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
	if (!$path && isset($index["index"])) {
		$res = $index["index"];
		$text = Rubrics::article($res['src']);
		return Ans::html($text);
	} else if ($path && isset($index[$path])) {
		$res = $index[$path];
		if (isset($res['src'])) {
			$text = Rubrics::article($res['src']);
		} else {
			$text = '';
		}
		return Ans::html($text);
	} else {
		return http_response_code(404);
	}
	return Ans::ans($index);
});


