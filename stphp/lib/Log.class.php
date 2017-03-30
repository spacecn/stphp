<?php
/**
 * STPHP FRAMEWORK v1.0
 * shengdi_lin <www.i3ui.com>
 * 
 * 日志类
 */
namespace lib;

class Log{
	/**
	 * 写入日志
	 * @param  string $msg  
	 * @param  string $file 
	 * @param  string $extra
	 */
	static public function log($msg, $file='', $extra=''){
		$now = date('[Y-m-d H:i:s]');
		if(empty($file)){
			$file = RUNTIME_PATH . 'log' . DS . date('Y-m-d') . '.log';
		}
		if(file_exists($file)){
			if(filesize($file) >= 1024*1024){
				$fnamearr = explode('.',basename($file));
				$bak = dirname($file) . DS . $fnamearr[0] . mt_rand(10000,99999) . '.bak';
				rename($file, $bak);
			}
			error_log("{$now} {$msg}\r\n\n", 3, $file, $extra);
		} else {
			if(createDir(dirname($file))){
				error_log("{$now} {$msg}\r\n\n", 3, $file, $extra);
			} else {
				throw new \Exception("创建目录失败" . dirname($file));
			}
		}
	}
}