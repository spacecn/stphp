<?php
/**
 * STPHP FRAMEWORK v1.0
 * shengdi_lin <www.i3ui.com>
 * 
 * 控制器
 */
namespace core;

class Controller{
	static public $trace = array();
	static public $traceType;

	/**
	 * 架构函数
	 */
	public function __construct(){}

    /**
     * 跳转提示
     * @param  string  $msg
     * @param  mixed  $url
     * @param  int $wait
     * @return void
     */
    public function jump($msg, $url, $wait = 3){
    	if($url === 0 || $url === false || empty($url)){
    		$url = "'" . "javascript:location.href=document.referrer;" . "'";
    	}
    	if($wait == 0){
			header("Location:$url");
		}
		include VIEW_PATH . 'admin' . DS . 'st_msg.html';
		exit();
    }

	/**
	 * trace面板
	 * @return void
	 */
	static public function showTrace($type){
		if(APP_DEBUG == false) return null;
		self::$traceType = $type;
	}

	/**
     * 默认空方法
     */
    public function index(){}
}