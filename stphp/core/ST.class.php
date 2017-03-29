<?php
/**
 * STPHP FRAMEWORK v1.0
 * shengdi_lin <www.i3ui.com>
 * 
 * 引导类
 */
class ST{
	/**
	 * 启动框架
	 */
	static public function run(){
		self::init();
		self::_autoload();
		self::_dispatch();
	}

	/**
	 * 应用程序初始化
	 */
	static public function init(){
		define('APP_DEBUG', true);			// true 开发模式 | false 上线模式 

		// 定义路径常量
		define('DS', DIRECTORY_SEPARATOR);	//路径分割符
		define('ROOT', getcwd() . DS);		//根目录
		define('APP_PATH', ROOT . 'app' . DS);
		define('FRAMEWORK_PATH', ROOT . 'stphp' . DS);
		define('PUBLIC_PATH', ROOT . 'public' . DS);
		define('CONFIG_PATH', APP_PATH . 'config' . DS);
		define('CONTROLLER_PATH', APP_PATH . 'controllers' . DS);
		define('MODEL_PATH', APP_PATH . 'models' . DS);
		define('VIEW_PATH', APP_PATH . 'views' . DS);
		define('RUNTIME_PATH', APP_PATH . 'runtime' . DS);
		define('CORE_PATH', FRAMEWORK_PATH . 'core' . DS);
		define('DB_PATH', FRAMEWORK_PATH . 'db' . DS);
		define('LIB_PATH', FRAMEWORK_PATH . 'lib' . DS);
		define('TPL_PATH', FRAMEWORK_PATH . 'tpl' . DS);
		define('UPLOAD_PATH', PUBLIC_PATH . 'uploads' . DS);

		// 加载配置文件
		$GLOBALS['config'] = require_once CONFIG_PATH . 'constants.php';

		// 加载核心类
		include CORE_PATH . 'Functions.php';
		include LIB_PATH . 'Exception.class.php';
		include LIB_PATH . 'Log.class.php';
		include CORE_PATH . 'Controller.class.php';
		include ROOT . 'vendor/autoload.php';

		// 设置时区
		date_default_timezone_set('PRC');

		// 开启 session
		session_start();

		//错误处理函数
		//error_reporting(E_ALL);
		error_reporting(0);
		register_shutdown_function(array('lib\Exception','fatalError'));
		set_error_handler(array('lib\Exception','appError'));
		set_exception_handler(array('lib\Exception','appException'));
	}

	/**
	 * 自动加载
	 */
	static private function _autoload(){
		spl_autoload_register(array(__CLASS__, '_load'));
	}

	/**
	 * 加载文件
	 * @param  string $class
	 * @return bool
	 */
	static private function _load($class){
		$class = ltrim(str_replace('\\', DS, $class),'/');
		if(class_exists($class) || interface_exists($class)){
			return true;
		} elseif(substr(strtolower($class), 0, 3) == 'app'){
			$path = sprintf(ROOT . '%s.class.php', $class);
		} else {
			$path = sprintf(FRAMEWORK_PATH . '%s.class.php', $class);
		}
		if(file_exists($path)){
			include_once($path);
			return true;
		}
		throw new \Exception("不存在的类文件：" . $path);
	}

	/**
	 * 路由
	 */
	static private function _dispatch(){
		$varPlat		=	config('VAR_PLAT');
        $varController	=	config('VAR_CONTROLLER');
        $varAction		=	config('VAR_ACTION');
        
        if(isset($_SERVER['PATH_INFO'])){
        	$pathinfostr = $_SERVER['PATH_INFO'];
        } elseif(isset($_SERVER['ORIG_PATH_INFO'])){
        	$pathinfostr = $_SERVER['ORIG_PATH_INFO'];
        } else {
        	$pathinfostr = '';
        }

        if($pathinfostr){
        	// 获取URL数组
        	$urlArr = preg_replace('/\.' . strtolower(pathinfo($pathinfostr,PATHINFO_EXTENSION)) . '$/i', '', explode("/",trim(strtolower(trim($pathinfostr,'/')))));
			$paths = array_slice($urlArr,0,3);
			// URL参数按变量名绑定变量
			preg_replace_callback('/(\w+)\/([^\/]+)/', function($match) use(&$vars){$vars[$match[1]]=strip_tags($match[2]);}, implode('/',array_slice($urlArr,3)));
        } else {
        	$paths = array_values(array_slice($_GET,0,3));
			$vars = array_slice($_GET,3);
        }

        if(isset($paths)){
        	unset($_GET);
        	$allowplat = config('ALLOW_PLAT');
			if(empty($allowplat) || in_array(implode('/',array_slice($paths,0,1)),$allowplat)){
				$_GET[$varPlat] = array_shift($paths);
			}
			if(!empty($paths)){
				$_GET[$varController] = ucfirst(trim(array_shift($paths)));
			}
			if(!empty($paths)){
				$_GET[$varAction] = trim(array_shift($paths));
			}
        }

        // 获取路径参数
		define('PLATFORM', !empty($_GET[$varPlat]) ? $_GET[$varPlat] : config('DEFAULT_PLAT'));
		define('CONTROLLER', !empty($_GET[$varController]) ? $_GET[$varController] : config('DEFAULT_CONTROLLER'));
		define('ACTION', !empty($_GET[$varAction]) ? $_GET[$varAction] : config('DEFAULT_ACTION'));
		define('__CONTROLLER__', CONTROLLER);
		define('__ACTION__', ACTION);

		// 设置前后台视图目录
		define('CUR_VIEW_PATH', VIEW_PATH . PLATFORM . DS);

		// 变量绑定
		$_GET = !empty($vars) ? $vars : array();
		C(PLATFORM . DS . __CONTROLLER__, ACTION);
	}
}
// 启动
ST::run();