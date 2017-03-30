<?php
/**
 * STPHP FRAMEWORK v1.0
 * shengdi_lin <www.i3ui.com>
 * 
 * 视图
 */
namespace core;

class View{
	protected $assign = array();

	/**
	 * 模板赋值
	 * @param  mixed $name 
	 * @param  mixed $value
	 */
	public function assign($name, $value=''){
		if(is_array($name)) {
            $this->assign   =  array_merge($this->assign, $name);
        }else {
            $this->assign[$name] = $value;
        }
	}

	/**
	 * 加载视图模板
	 * @param  string $filename
	 * @return void
	 */
	public function display($filename){
		$file = CUR_VIEW_PATH . $filename;
		if(is_file($file)){
			extract($this->assign);	// 将变量数组打散，变为单独的变量
			include CUR_VIEW_PATH . $filename;
		}
	}


	/**
	 * 加载视图模板
	 * @param  string $filename
	 */
	// public function display($filename){
	// 	$path = CUR_VIEW_PATH . $filename;
	// 	if(is_file($path)){
	// 		\Twig_Autoloader::register();
	// 		$loader = new \Twig_Loader_Filesystem(CUR_VIEW_PATH);
	// 		$twig = new \Twig_Environment($loader, array(
	// 		    'cache' => RUNTIME_PATH . 'twig\\',
	// 		    'debug' => APP_DEBUG
	// 		));
	// 		echo $twig->render($filename, $this->assign);
	// 	} else {
	// 		throw new \Exception('模板文件不存在：' . $path);
	// 	}
	// }



	
}