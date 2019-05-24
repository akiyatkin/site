<?php
namespace akiyatkin\boo;
use infrajs\path\Path;
use infrajs\load\Load;
use infrajs\nostore\Nostore;
use infrajs\each\Each;
use infrajs\hash\Hash;
use infrajs\once\Once;
use infrajs\access\Access;
use akiyatkin\fs\FS;
use infrajs\sequence\Sequence;
use infrajs\router\Router;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use Cache\Adapter\Filesystem\FilesystemCachePool;


class Cache extends Once
{
	public static $cwd = false;
	public static $process = false;
	public static $conds = array();
	public static $conf = array(
		'cachedir' => '!boo/',
		'time' => 0
	);
	public static $admin = false;
	public static $condscounter = 0;
	public static function getCondTime($cond) {
		Cache::$condscounter++;
		//return call_user_func_array($cond['fn'], $cond['args']);   
		//$id = json_encode($cond, JSON_UNESCAPED_UNICODE);
		$id = print_r($cond, true);
		//if (is_array($cond['fn'])) $id = $cond['fn'][0].':'.$cond['fn'][1];
		if (isset(Cache::$conds[$id])) return Cache::$conds[$id];
		Cache::$conds[$id] = call_user_func_array($cond['fn'], $cond['args']);   
		return Cache::$conds[$id];
	}
	public static function setBooTime() {
		$sys = FS::file_get_json('!.infra.json');
		if (!isset($sys['boo'])) $sys['boo'] = array();
		$sys['boo']['time'] = time();
		Cache::$conf['time'] = $sys['boo']['time'];
		FS::file_put_json('!.infra.json', $sys);
	}
	public static function getBooTime() {
		return Cache::$conf['time'];
	}
	
	public static function getItemsSrc() {
		return Cache::$conf['cachedir'].'.items.json';
	}
	public static function setTitle($title, &$item = false)
	{
		if (!$item) $item = &Once::$item;
		$item['title'] = $title;
	}
	public static function &createItem($args = array(), $condfn = array(), $condargs = array(), $level = 0) {
		$level++;
		$item = &Once::createItem($args, $condfn, $condargs, $level);
		if (isset($item['cls'])) return $item;
		$title = [];
		$i = 0;
		while (isset($args[$i]) && (is_string($args[$i]) || is_integer($args[$i]))) {
			$title[] = $args[$i];
			$i++;
		}
		$title = implode(' ', $title);
		if (!$title) $title = $item['hash'];

		$item['cls'] = get_called_class();   
		$item['src'] = static::getSrc();
		$data = static::loadResult($item);
		
		if ($data) {
			$item['loaded'] = true;
			$item['result'] = $data['result'];
			$item['conds'] = $data['conds'];
			$item['timer'] = $data['timer'];
			$item['childs'] = $data['childs'];
			$item['time'] = $data['time'];
		} else {
			$item['loaded'] = false;
		}
		return $item;
	}
	/**
	* Текущий кэш не сохранится
	**/
	public static function ignore(){
		Once::$item['ignore'] = true;
	}
	/**
	* К текущему кэшу добавляется проверка
	**/
	public static function addCond($fn, $args = []){
		Once::$item['conds'][] = [
			'fn' => $fn,
			'args' => $args
		];
	}
	/**
	 * Адрес текущего GET запроса
	 *
	 * @return null|string|string[]
	 */
	public static function getSrc()
	{
		$src = preg_replace("/^\/+/", "", $_SERVER['REQUEST_URI']);
		$src = preg_replace("/\-boo=[^&]*&{0,1}/",'',$src);
		$src = preg_replace("/\-update=[^&]*&{0,1}/",'',$src);
		$src = preg_replace("/\-access=[^&]*&{0,1}/",'',$src);
		$src = preg_replace("/[\?&]$/",'',$src);
		if (!$src) {
			$src = '-boo/empty';
		}
		return $src;
	}

	public static function setStartTime() {
		$sys = FS::file_get_json('!.infra.json');
		if (!isset($sys['boo'])) $sys['boo'] = array();
		$sys['boo']['starttime'] = time();
		$sys['boo']['time'] = $sys['boo']['starttime'];
		Cache::$conf['starttime'] = $sys['boo']['starttime'];
		Cache::$conf['time'] = $sys['boo']['time'];
		FS::file_put_json('!.infra.json', $sys);
	}

	public static function getStartTime() {
		return Cache::$conf['starttime'];
	}
	public static function isChange(&$item) {
		
		$r = static::_isChange($item);
		if (!empty($item['checked'])) {
			header('Boo-cache-check: '.sizeof(Cache::$conds));
		}
		//Мы хотим оптимизировать, что бы время проверки условий записалось и больше условия не проверялись
		//Для этого при false нужно записать время и сохранить кэш. 
		//Но false для пользователя не значит что будет false для админа по этому время установить можно не всегда.
		if ($r || ($r === 0 && !Access::isTest())) {
			//0 - означает что false из после проверки условий, тогда и пользователь может сохранить
			//Нельзя что бы остался time старее AdminTime это будет всегда зпускать проверки
			//Надо один раз сохранить время после AdminTime и проверки запускаться для посетителя не будут
			$item['time'] = time();//Обновляем время, даже если выполнения далее не будет, что бы не запускать проверки
		}

		return $r;
	}
	public static function isReady(&$item) {
		if (Cache::$process) return;
		if (!empty($item['nostore'])) return;
		if (!empty($item['ignore'])) return;
		if (!empty($item['loaded']) && empty($item['start']) && empty($item['checked'])) return;
		if (!empty($item['start']) || (!empty($item['checked']) && !Access::isTest())) { //Было выполнение или проверки
			Cache::$process = $item;
			header('Boo-cache: process'); 
		}
	}
	public static function _isChange(&$item) {
		if (empty($item['start']) && empty($item['loaded'])) return true; //Кэша вообще нет ещё
		if (!empty($item['start'])) return false; //Только что выполненный элемент
		
		
		if (!Access::isTest()) { //Для обычного человека сравниваем время последнего доступа
			$atime = Access::adminTime(); //Заходил Админ
			if ($atime <= $item['time']) return false; //И не запускаем проверки. 
			//Есть кэш и админ не заходил
		} 
		if (Access::isDebug()) {
			if (filemtime($item['file']) > $item['time']) return true;
		}
		
		//Горячий сброс кэша, когда редактор обновляет сайт, для пользователей продолжает показываться старый кэш.
		// -boo сбрасывает BooTime и AccessTime и запускает проверки для всех пользователей
		// -Once::setStartTime() сбрасывает StartTime и BooTime и кэш создаётся только для тестировщика и без проверок
		$atime = static::getStartTime();
		if ($atime > $item['time']) {
			header('Boo-cache-start-time: true');
			return true; //Проверки Не важны, есть отметка что весь кэш устарел
		}

		$item['checked'] = true;
		

 		if(!empty($item['conds'])) {
			foreach ($item['conds'] as $cond) {
				$time = Cache::getCondTime($cond);
				if ($time >= $item['time']) {
					return true;
				}
			}
		}   
		return 0;
	}
	/**
	 * Сохраняет результат в место постоянного хранения.
	 * Используется в расширяемых классах.
	 * В once сохранять ничего не надо.
	 * Сохранение должно вызываться в execfn
	 * @param $item
	 */
	public static function saveResult($item) {
		$dir = Cache::$conf['cachedir'].$item['gid'];
		$file = $dir.'/'.$item['hash'].'.json';
		FS::mkdir($dir);
		FS::file_put_json($file, $item);
	}
	public static function removeResult($item){
		$dir = Cache::$conf['cachedir'].$item['gid'];
		$file = $dir.'/'.$item['hash'].'.json';
		FS::unlink($file);
	}
	public static function loadResult($item) {
		$dir = Cache::$conf['cachedir'].$item['gid'];
		$file = $dir.'/'.$item['hash'].'.json';
		$data = FS::file_get_json($file);
		return $data;
	}
	public static function getDurationTime($strtotime) {
		/*
			-1 month
			-1 day
			-1 week
			last Monday
			last month
			last day
			last week
			last friday
		*/
		return strtotime($strtotime);
	}
	/**
	* Кэш до следующей авторизации админа
	**/
	//public static function getAccessTime() {
	//    return Access::adminTime();
	//}
	public static function getModifiedTime($isrc) {
		if (isset($_GET['-boo']) && $_GET['-boo'] == 'fs') return;
		$src = Path::theme($isrc);
		//if ($isrc == '~catalog/') var_dump(filemtime($src));
		if (!$src) return 0;
		return filemtime($src);
	}
	

	public static function isAdmin($item) {
		if (!isset($item['cls'])) return false;
		return $item['cls']::$admin;
	}
	public static function isSave($item) {
		if (!empty($item['ignore'])) return false;
		if (!isset($item['cls'])) return false;
		return true;
	}
	public static function runNotLoaded(&$allitems, $item, $func) {
		$func($item);
		if (!empty($item['loaded']) && empty($item['start'])) return; //Элемент был загружен и не выполнялся у него уже всё всборе
		if (empty($item['childs'])) return;
		foreach ($item['childs'] as $cid => $v) {
			if ($item['id'] == $cid) continue; //Фигня какая-то баг... 
			if (!isset($allitems[$cid])) continue;
			$it = $allitems[$cid];
			Cache::runNotLoaded($allitems, $it, $func);
		}
	}
	public static function initSave() {
		//isAdmin child добавляет условие для parent
		//1 найти всех родителей
		//Из-за событий мы можем продолжить кэш-элемент после его завершения
		$parents = array();
		$allitems = Once::$items;

		foreach ($allitems as $id => $item) {
			if (empty($item['start'])) continue; //Не выполнялся
			if ($allitems[$id]['condfn']) {
				$allitems[$id]['conds'][] = array(
					'fn' => $allitems[$id]['condfn'],
					'args' => $allitems[$id]['condargs']
				);
			}
			foreach ($allitems[$id]['childs'] as $cid => $v) { //$id например загружен но он есть в $allitems
				//Один из childs мог быть загружен и содержать subchilds которых нет в $allitems
				if (!isset($parents[$cid])) $parents[$cid] = array();
				$parents[$cid][$id] = true; //Найденный родитель для cid
			}
		}

		//2 Теперь у каждого элемента мы знаем куда наследовать и можем удалять
		//И надо удалить упоминания этого элемента
		foreach ($allitems as $id => $item) {
			if (Cache::isSave($allitems[$id])) continue;
			//Текущий элемент $id надо удалить
			if (isset($parents[$id])) { //Есть куда наследовать
				foreach ($allitems[$id]['childs'] as $cid => $i) { //Всех $cid детей переносим
					foreach ($parents[$id] as $pid => $p) { //$pid новый родитель для $cid
						$allitems[$pid]['childs'][$cid] = true; //Новый child у pid
					}
				}
				foreach ($parents[$id] as $pid => $p) { //$pid новый родитешь для $cid
					$parents[$cid][$pid] = true; //Новый родитель у cid
					$allitems[$pid]['conds'] = array_merge( //Новые cond у родителя
						$allitems[$pid]['conds'],
						$allitems[$id]['conds']
					);
				}
			}

			foreach ($allitems[$id]['childs'] as $cid => $i) { //Всех $cid детей переносим
				unset($parents[$cid][$id]); //старый родитель у $cid удалён
			}


			if (isset($parents[$id])) {
				foreach ($parents[$id] as $pid => $p) {
					unset($allitems[$pid]['childs'][$id]);
				}
			}	
		}


		foreach ($allitems as $id => $item) {
			if (Cache::isSave($allitems[$id])) continue;
			unset($allitems[$id]); //conds и childs перенесли
			//Для кого-то он родитель и он остался в $parents
		}

		
		foreach ($allitems as $id => $item) {
			if (!Cache::isAdmin($item)) continue;
			if (isset($parents[$id])) {
				foreach ($parents[$id] as $pid => $p) {
					$allitems[$pid]['conds'][] = array(
						'fn' => ['akiyatkin\\boo\\Cache','getBooTime'],
						'args' => []
					);
				}
			}
		}
			
		//3 Копируем conds родителям
		//Берём элемент и собираем все его conds
		foreach ($allitems as $id => $item) {
			$conds = array();
			Cache::runNotLoaded($allitems, $item, function ($item) use (&$conds){
				$conds = array_merge($conds, $item['conds']);
			});
			$allitems[$id]['conds'] = $conds;			
		}
		
		//Убираем дубликаты conds
		foreach ($allitems as $id => $item) {
			//$allitems[$id]['childs'] = array_values($allitems[$id]['childs']);
			$conds = array();
			foreach ($allitems[$id]['conds'] as $i => $cond) {
				$idc = print_r($cond, true);
				$conds[$idc] = $cond;
			}
			$allitems[$id]['conds'] = array_values($conds);
		}
		/*echo '<pre>';
		foreach ($allitems as $id => &$v) {
			unset($v['result']);
		}
		print_r($allitems);
		exit;*/
		//Сохраняем результат
		foreach ($allitems as $id => &$v) {
			if (!empty($v['nostore'])) continue;
			if (empty($v['start']) && empty($v['checked'])) continue;
			//Выполнено сейчас или были проверки или 
			$v['cls']::saveResult($v);
		}

		//Сохраняем результат для админки
		$admins = array();
		foreach ($allitems as $id => &$v) {
			if (!empty($v['nostore'])) continue;
			if (!Cache::isAdmin($v)) continue;
			if (empty($v['start'])) continue; //Если прям сейчас не выполнялся, то выходим
			$admins[$id] = &$v;
		}
	
		if ($admins) {
			$src = Cache::getItemsSrc();
			$items = FS::file_get_json($src);
			foreach($admins as $id => $it) {
				unset($it['result']);
				$items[$id] = $it;
			}
			FS::file_put_json($src, $items);
		}
	}
	public static function init () {
		Cache::$cwd = getcwd();
		register_shutdown_function( function () {
			if (!Router::$end) return;
			chdir(Cache::$cwd);
			$save = false;
			if (Cache::$process) { //В обычном режиме кэш не создаётся а только используется, вот если было создание тогда сохраняем
				$error = error_get_last();
				
				//E_WARNING - неотправленное письмо mail при неправильно настроенном сервере
				if (is_null($error) || ($error['type'] != E_ERROR
						&& $error['type'] != E_PARSE
						&& $error['type'] != E_COMPILE_ERROR)) {
					$save = true;
				}
			}
			if ($save) {
				Cache::initSave();
			}
		});
	}
}
Cache::init();

