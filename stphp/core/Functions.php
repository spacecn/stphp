<?php
/**
 * STPHP FRAMEWORK v1.0
 * shengdi_lin <www.i3ui.com>
 * 
 * 系统函数
 */

/**
 * 获取配置文件信息
 * @param  string $name
 * @return mixed
 */
function config($name=null){
	$default_value = null;
	if(is_string($name)){
		$name = strtoupper($name);
		if(!strpos($name, '.')){
			return isset($GLOBALS['config'][$name]) ? $GLOBALS['config'][$name] : $default_value;
		}
		$name = explode('.', $name);
		return isset($GLOBALS['config'][$name[0]][$name[1]]) ? $GLOBALS['config'][$name[0]][$name[1]] : $default_value;
	}
	return null;
}

/**
 * 单个变量打印
 * @param  mixed  $var
 * @param  bool $print_r
 */
function p($var){
	if(is_bool($var)){
		var_dump($var);
	} elseif(is_null($var)){
		var_dump(NULL);
	} else {
		echo "<pre style='margin:20px;padding:20px;background:#2a2a2a;color:#bebebe;white-space:pre-wrap;word-wrap:break-word;font:14px/18px consolas,monospace;border-radius:6px;'>" . print_r($var, true) . "</pre>";
	}
}

/**
 * trace面板
 * @param  mixed $content
 * @param  string $type
 * @return array
 */
function trace($content, $type='DEBUG'){
	$traceType = array('DEBUG'=>'调试','SQL'=>'SQL');
	$type = strtoupper($type);
	if(!array_key_exists($type, $traceType)) $type = 'DEBUG';
	$contentArr[$type] = array();
	array_push($contentArr[$type], $content);
	\core\Controller::$trace = array_merge_recursive(\core\Controller::$trace, $contentArr);
	\core\Controller::showTrace($traceType);
}

/**
 * 实例化控制器并调用方法
 * @param string $controller
 * @param string $action
 */
function C($controller, $action){
	$controller = str_replace('/','\\',$controller);
	$controller = '\\app\\controllers\\'.$controller.'Controller';
	$obj = new $controller();
	if(!method_exists($controller, $action) && !method_exists($controller, '__call')){
		throw new \Exception("不存在的方法：" . $controller . ' -> ' . $action . '();');
	}
	$obj->$action();
}

/**
 * 实例化模型
 * @param string $model
 */
function M($model){
	$model = str_replace('/','\\',$model);
	if(!empty($model)){
		$model = '\\app\\models\\'.ucfirst(strtolower($model)).'Model';
	} else {
		$model = 'core\\Model';
	}
	$args = func_get_args();
	if(count($args) > 1){
		$obj = count($args) == 2 ? new $model($args[1]) : new $model($args[1],$args[2]);
	} else {
		$obj = new $model();
	}
	return $obj;
}

/**
 * 创建目录
 * @param  string $dir
 * @return bool
 */
function createDir($dir){
    return is_dir($dir) or (createDir(dirname($dir)) and @mkdir($dir, 0777));
}

/**
 * 数据转义
 * @param string $data
 */
function D($data){
	return is_array($data) ? array_map('deep_addslashes',$data) : data_addslashes($data);
}

/**
 * 字符串过滤
 * @param  string $str
 * @return string
 */
function data_addslashes($data){
	$data = trim($data, ' ');
	$data = htmlspecialchars($data);
	$data = _addslashes($data);
	return $data;
}

/**
 * 字符串过滤
 */
function _addslashes($str){
	return (!get_magic_quotes_gpc()) ? addslashes($str) : $str;		// 判断是否开启get_magic_quotes_gpc功能，再进行转义
}

/**
 * 获取数据
 * @param  string $name
 * @return mixed
 */
function get_data($name){
	static $_PUT = null;
	if(strpos($name, '.')){
		list($method, $name) = explode('.', $name, 2);
	}
	switch (strtolower($method)) {
		case 'get':
			$input = & $_GET;
			break;
		case 'post':
			$input = & $_POST;
			break;
		case 'request':
			$input = & $_REQUEST;
			break;
		case 'session':
			$input = & $_SESSION;
			break;
		case 'cookie':
			$input = & $_COOKIE;
			break;
		default:
			return null;
	}
	if('' == $name){
		$data = $input;
	} elseif(isset($input[$name])){
		$data = $input[$name];
	} else{
		$data = null;
	}
	if(!is_array($data)) $data = array($data);
	$data   =   array_map('D', $data); // 参数过滤
	return $data;
}

/**
 * 获取数组中指定元素为新数组 (只支持一维数组)
 * @param  array $target
 * @param  array $arr
 * @return array
 */
function parseArr($target, $arr){
	$tempArr = array();
	foreach ($target as $k => $v) {
		if(in_array($k, $arr)){
			$tempArr[$k] = $target[$k];
		}
	}
	return $tempArr;
}

/**
 * 递归无限级数组
 * @param  array  $arr
 * @param  integer $pid
 * @param  integer $level
 * @return array
 */
function tree($arr, $pid=0, $level=0, $pidname='parent_id', $idname='id'){
	static $res =array();
	foreach ($arr as $v) {
		if($v[$pidname] == $pid){
			$v['level'] = $level;
			$res[] = $v;
			tree($arr, $v[$idname], $level+1, $pidname, $idname);
		}
	}
	return $res;
}