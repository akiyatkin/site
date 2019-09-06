<?php
namespace akiyatkin\site;
use infrajs\path\Path;
use infrajs\load\Load;
use infrajs\once\Once;
use infrajs\access\Access;
use infrajs\config\Config;
use akiyatkin\fs\FS;
use infrajs\sequence\Sequence;
use infrajs\excel\Xlsx;
use infrajs\rubrics\Rubrics;


/*
//Если это папка, то мы должны к ней привязать первую статью в ней
{ //папка
	"name":"site",
	"isdir":true,
	"file":"site",
	"childs":[
		{
			"name":"site",
			"isdir":true,
			"file":"site",
		}
	]
}

*/

class Site {
	public static $conf = array();
	public static function scan($dir, &$group = array('nick'=>''), $level = 1) {
		FS::scandir($dir, function ($file) use ($dir, &$group, $level) {
			if (in_array($file[0], ['~', '.'])) return;
			$info = Load::pathinfo($dir.$file);
			$nick = Path::encode($info['name']);

			if (FS::is_dir($dir.$file.'/')) {
				if (isset($group['childs'][$nick])) {
					$fd = $group['childs'][$nick];
				} else {
					$fd = array();
					$fd['num'] = $info['num'];
					$fd['nick'] = $nick;
					$fd['name'] = $info['name'];
					$fd['files'] = [];
					$fd['childs'] = [];
					$fd['data'] = [];
				}
				Site::scan($dir.$file.'/', $fd, $level + 1);
				$group['childs'][$nick] = $fd;
			} else if (in_array($info['ext'], ['docx','html'])) {
				if (isset($group['data'][$nick])) {
					$fd = $group['data'][$nick];
				} else {
					$fd = array();
					$fd['nick'] = $nick;
					$fd['num'] = $info['num'];
					$fd['name'] = $info['name'];
					$fd['files'] = [];
					$fd['src'] = [];
				}
				$fd['src'][] = $dir.$file;
				$group['data'][$fd['nick']] = $fd;
			} else {
				$group['files'][] = $dir.$file;
			}
			
		});
		return $group;
	}
	public static function runGroups(&$group, $func, $nick = '', &$parent = false, $level = 0){
		$r = $func($group, $nick, $parent, $level);
		if ($r != null) return $r;
		
		if (isset($group['childs'])) foreach ($group['childs'] as $nick => &$item) {
			$r = Site::runGroups($item, $func, $nick, $group, $level + 1);
			if ($r != null) return $r;
		}
	}
	public static function runItems(&$group, $func, $nick = '', &$parent = false, $level = 0){
		$r = $func($group, $nick, $parent, $level);
		if ($r != null) return $r;
		
		if (isset($group['childs'])) foreach ($group['childs'] as $nick => &$item) {
			$r = Site::runItems($item, $func, $nick, $group, $level + 1);
			if ($r != null) return $r;
		}
		if (isset($group['data'])) foreach ($group['data'] as $nick => &$item) {
			$r = Site::runItems($item, $func, $nick, $group, $level + 1);
			if ($r != null) return $r;
		}
	}
	public static function data () {
		$data = Access::func(function(){
			$data = Site::scan(Site::$conf['dir']);
			//Определяем статью для папки
			Site::runGroups($data, function (&$group, $nick, &$parent) {
				if (isset($parent['data'][$nick])) {
					$group += $parent['data'][$nick];
					unset($parent['data'][$nick]);
				}
			
				if ($group['data']) {
					//Первая статья в папке
					foreach ($group['data'] as $nick => $item) break;
					$group += $item;
					unset($group['data'][$nick]);
				}
			
				if (isset($group['data']['index'])) {
					$group += $group['data']['index'];
					unset($group['data']['index']);
				}
			});


			//Определяем файлы описания
			Site::runItems($data, function (&$item, $j, &$group, $level) {
				foreach ($item['files'] as $i => $src) {
					unset($item['files'][$i]);
					$info = Load::pathinfo($src);
					if (in_array($info['ext'],['jpg','jpeg','png','gif'])) {
						if (!isset($item['images'])) $item['images'] = [];
						$item['images'][] = $src;
					} else if (in_array($info['ext'],['php'])) {
						if (!isset($item['php'])) $item['php'] = [];
						$item['php'][] = $src;
					} else if (in_array($info['ext'],['tpl'])) {
						if (!isset($item['tpl'])) $item['tpl'] = [];
						$item['tpl'][] = $src;
					} else if (in_array($info['ext'],['js'])) {
						if (!isset($item['js'])) $item['js'] = [];
						$item['js'][] = $src;
					} else if (in_array($info['ext'],['json'])) {
						if (!isset($item['json'])) $item['json'] = [];
						$item['json'][] = $src;
					} else {
						$d = Load::pathinfo($src);
						unset($d['num']);
						unset($d['date']);
						$d['src'] = $d['path'];
						unset($d['fname']);
						unset($d['path']);
						unset($d['id']);
						unset($d['folder']);
						$d['size'] = round(FS::filesize($src) / 1000);
						$item['files'][] = $d;
					}
				}
				if (isset($item['images'])) {
					$item['image'] = $item['images'][0];
					if (sizeof($item['images'])==1) unset($item['images']);
				}
				if (!$item['files']) unset($item['files']);
				else $item['files'] = array_values($item['files']);
			});

			//Определяем дополнительные параметры
			Site::runItems($data, function (&$item, $j, &$group, $level) {
				$item['level'] = $level;
				

				if ($group['path']) {
					$item['path'] = $group['path'].'/'.$item['nick'];
					$item['parent'] = $group['path'];
				} else {
					$item['parent'] = '';
					$item['path'] = $item['nick'];
				}
				if (isset($item['src'])) $item['src'] = $item['src'][0];
				if (isset($item['json'])) $item['json'] = $item['json'][0];
				if (isset($item['tpl'])) $item['tpl'] = $item['tpl'][0];
				if (empty($item['json'])) $item['json'] = $group['json'];
				if (empty($item['tpl'])) $item['tpl'] = $group['tpl'];
			});
			

			Site::runGroups($data, function (&$group, $i, &$parent) {
				//if (!empty($group['src']) || !empty($group['data']) || !empty($group['childs'])) return;
				$group['items'] = array_merge($group['childs'],$group['data']);
				Load::sort($group['items'],'ascending');
				$group['items'] = array_map(function ($item){
					return $item['path'];
				}, $group['items']);
				
				if (!empty($group['data']) || !empty($group['childs'])) return;
				$parent['data'][$i] = $parent['childs'][$i];
				
				unset($parent['childs'][$i]);
				unset($group['data']);
				unset($group['childs']);
			});
		
			/*Site::runGroups($data, function (&$group, $i, &$parent) {
				if (!empty($group['src']) && (!empty($group['data']) || !empty($group['childs']))) {

				} else {
					return;
				}
				//if (!empty($group['data']) || !empty($group['childs'])) return;
				$parent['data'][$i] = $parent['childs'][$i];
				unset($parent['childs'][$i]);
				unset($group['data']);
				unset($group['childs']);
			});*/
			//Схлопываем ключи
			Site::runGroups($data, function (&$group) {
				$group['childs'] = array_values($group['childs']);
				$group['data'] = array_values($group['data']);
				Load::sort($group['data'],'ascending');
			});
			Site::runItems($data, function (&$item) use (&$index) {
				if (!isset($item['src'])) return;
				$res = Rubrics::info($item['src']);
				if (isset($res['heading'])) {
					$item['heading'] = $res['heading'];
					$item['name'] = $res['heading'];
				}
				
				if (isset($res['preview'])) $item['preview'] = $res['preview'];
				if (isset($res['images']) && empty($item['image'])) $item['image'] = $res['images'][0]['src'];
			});
			return $data;
		});
		return $data;
	}
	public static function init () {
		$data = Site::data();
		$index = array();
		Site::runItems($data, function (&$item) use (&$index) {
			$index[$item['path']] = $item;
		});
		foreach ($index as $path => &$item) {
			if (isset($item['childs'])) foreach ($item['childs'] as $k => $pos) {
				$item['childs'][$k] = $pos['path'];
			}
			if (isset($item['data'])) foreach ($item['data'] as $k => $pos) {
				$item['data'][$k] = $pos['path'];
			}
		}
		$index['index'] = $index[''];
		unset($index['']);
		return $index;
	}
}

