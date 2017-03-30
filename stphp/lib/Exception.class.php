<?php
/**
 * STPHP FRAMEWORK v1.0
 * shengdi_lin <www.i3ui.com>
 * 
 * 错误处理
 */
namespace lib;

class Exception{
	static public $errortype  = array(
		'0'		=>	'Exception : 错误',
		'1'     =>	'E_ERROR : 致命错误',
		'2'     =>  'E_WARNING : 警告',
		'4'     =>  'E_PARSE : 解析错误',
		'8'     =>  'E_NOTICE : 通知',
		'16'    =>  'E_CORE_ERROR : PHP致命错误',
		'32'    =>  'E_CORE_WARNING : PHP警告',
		'64'    =>  'E_COMPILE_ERROR : Zend错误',
		'128'   =>  'E_COMPILE_WARNING : Zend警告',
		'256'   =>  'E_USER_ERROR : User致命错误',
		'512'   =>  'E_USER_WARNING : User警告',
		'1024'  =>  'E_USER_NOTICE : User通知',
		'2048'	=>  'E_STRICT',
		'4096'	=>  'E_RECOVERABLE_ERROR',
		'8192'	=>	'E_DEPRECATED'
	);

	/**
	 * 错误处理
	 */
	static public function appError($errno, $errstr, $errfile, $errline){
		$e = array(
			'type' 		=>	$errno,
			'message' 	=>	$errstr,
			'file' 		=>	$errfile,
			'line' 		=>	$errline,
			'tip' 		=>	self::$errortype[$errno]
		);

		switch ($errno) {
			case E_ERROR:
			case E_PARSE:
			case E_CORE_ERROR:
			case E_COMPILE_ERROR:
			case E_USER_ERROR:
				ob_end_clean();
				self::err($e, 'FATAL');
				break;
			default:
				self::err($e, 'APP');
				break;
		}
	}

	/**
	 * 致命错误
	 */
	static public function fatalError(){
		if($e = error_get_last()){
			switch ($e['type']) {
				case E_ERROR:
				case E_PARSE:
				case E_CORE_ERROR:
				case E_COMPILE_ERROR:
				case E_USER_ERROR:
					//ob_end_clean();		// 清空输出缓冲, 其实就是把php默认的错误输出给清除掉
				 	$e['tip'] = self::$errortype[$e['type']];
					self::err($e, 'FATAL');
					break;
			}
		}
	}

	/**
	 * 自定义异常处理
	 */
	static public function appException($error) {
		$e = array(
			'type' 		=>	$error->getCode(),
			'message' 	=>	$error->getMessage(),
			'file' 		=>	$error->getFile(),
			'line' 		=>	$error->getLine(),
			'tip' 		=>	self::$errortype[$error->getCode()]
		);
		self::err($e, 'Exception');
	}

	/**
	 * 输出错误信息
	 */
	static public function err($e, $type='Exception'){
		$info = $e['file'] . ' :: ' . $e['line'] . PHP_EOL . '[' . $e['type'] . '] ' . $e['tip'] . ' => ' . $e['message'];
		Log::log($info);
		if(APP_DEBUG == true || $type == 'FATAL' || $type == 'Exception'){
			if(APP_DEBUG == false){
				header("Location:" . '/404.html');
			} else {
				$skin = config('SYS_SKIN');
				$msg = $e['tip'] . ' | ' . $e['message'];
				$content = "<div class='msg-body'><h4>错误位置</h4><p><span>异常文件:</span> {$e['file']}</p><p><span>异常行:</span> {$e['line']}</p><p><span>异常类型:</span> {$e['type']}</p></div>";
			}
			$title = "<div class='msg-head'><div class='msg-title'>{$msg}</div></div>";
			require_once TPL_PATH . '/st_error.html';
			exit;
		}
	}
}