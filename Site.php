<?php
namespace akiyatkin\site;
use infrajs\path\Path;
use infrajs\load\Load;
use infrajs\once\Once;
use infrajs\access\Access;
use akiyatkin\fs\FS;
use akiyatkin\config\Config;
use infrajs\sequence\Sequence;


class Site
{
	public static $conf = array();
	public static function init () {
		Config::scan($conf['dir'], function($dir, $level){
			echo $dir;
		});
		return true;
	}
}

