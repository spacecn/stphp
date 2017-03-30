<?php
/**
 * STPHP FRAMEWORK v1.0
 * shengdi_lin <www.i3ui.com>
 * 
 * 模型类
 */
namespace core;
use db\Mysql;

class Model{
	protected $class;			// 类名称
	protected $dbName;			// 数据库名称
	protected $table;			// 表名称
	protected $tablePrefix;		// 表前缀
	protected $options;			// 条件集合
	protected $allowMethods		=   array('table','join','distinct','field','where','order','limit','group','having');
	protected $db;				// 当前数据库操作对象

	/**
	 * 模型初始化
	 * @param string $table 
	 * @param string $prefix
	 */
	public function __construct($table='', $prefix=null){
		$this->getTable($table, $prefix);
		$this->db = Mysql::getInstance();
		$this->_reset();
	}

	/**
	 * 设置模型表
	 * @param string $table
	 */
	final protected function getTable($table, $prefix){
		$this->class = str_replace('\\','/',get_class($this));
		if(empty($table)){
			$table = strtolower(substr(basename($this->class),0,-strlen('Model')));
			if(empty($table)) throw new \Exception('模型名称不能为空');
		}
		$dbName = config('DB.DB_NAME');
		if(strpos($table, '.')){	// 调用别的数据库
			list($dbName, $table) = explode('.', $table);
		}
		$this->dbName = $dbName;
		$this->tablePrefix = !is_null($prefix) ? $prefix : config('DB.DB_PREFIX');
		$this->table = $dbName .'.' . $this->tablePrefix . $table;
	}

	/**
	 * __call方法实现连贯操作(把条件存储在$options中)
	 * @param  string $method
	 * @param  array $args  
	 * @return $this
	 */
	public function __call($method, $args){
		$method = strtolower($method);
		if(in_array($method, $this->allowMethods)){
			if(!empty($args)){
				$arrlevel =  count($args);
				$this->options[$method] = ($arrlevel === 1) ? $args[0] : $args;
			}
		} else {
			return call_user_func_array(array($this->db, $method), $args);
		}
		return $this;
	}

	/**
	 * 查询（支持预处理）
	 * @param  string  $sql
	 * @param  mixed  $params
	 * @param  mixed $mode
	 * @param  boolean $debug
	 * @return array
	 */
	public function query($sql, $params=null, $mode=0, $debug=true){
		return $this->db->query($sql, $params, $mode, $debug);
	}

	/**
	 * 查找多条记录
	 * @param  array  $options [description]
	 * @return array
	 */
	public function select($options=array()){
		$pk = $this->options['pk'][0];
		if(is_bool($options)){
			$this->options['debug'] = $options;
		}
		if(is_numeric($options) || is_string($options)){
			$this->options['where'] = $pk . '=' . $options;
		}
		if(is_array($options) && !empty($options)){
			$data[$pk] = $options;
			$this->options['where'] = $data;
		}
		$result = $this->db->select($this->options);
		$this->_reset();
		return $result;
	}

	/**
	 * 查找一条记录
	 * @param  mixed  $options
	 * @return mixed
	 */
	public function find($options=array()){
		$this->options['limit'] = 1;
		$result = $this->select($options);
		return !empty($result) ? current($result) : array();
	}

	/**
	 * 插入数据 （一条或多条 Medoo）
	 * @param  array $datas
	 * @return mixed
	 */
	public function insert($datas){
		$result = $this->db->insert($datas, $this->options);
		$this->_reset();
		return $result;
	}

	/**
	 * 更新记录 (Medoo)
	 * @param  mixed $data
	 * @return mixed
	 */
	public function update($data){
		$pk = $this->options['pk'][0];
		if(!isset($this->options['where'][$pk])){
			if(isset($data[$pk])){
				$this->options['where'][$pk] = $data[$pk];
				unset($data[$pk]);
			}
		}
		$result = $this->db->update($data, $this->options);
		$this->_reset();
		return $result;
	}

	/**
	 * 删除记录
	 * @param  array  $options
	 * @return boolean
	 */
	public function delete($options=array()){
		$pk = implode(',', $this->getPk());
		if(empty($options)){
			if(!isset($this->options['where'])){
				throw new \Exception("没有需要删除的记录");
			}
		} else {

			if(is_numeric($options) || is_string($options)){
				$this->options['where'][$pk] = explode(',', $options);
			} else {
				$this->options['where'][$pk] = $options;
			}
		}
		$result = $this->db->delete($this->options);
		$this->_reset();
		return $result;
	}

	/**
	 * 设置操作表名称(调用时有前缀需带前缀)
	 * @param string $table
	 */
	public function table($table){
		$tempTable = array();
		if(is_array($table)){
			$tempTable = $table;		// 标准数组： array('表名'=>'别名');
    	} elseif(!empty($table)) {
    		$item = explode(',', $table);	// 分割多表
    		if(count($item) > 1){
    			foreach ($item as $k => $v) {
	    			$subTable = explode(' ', $v);	// 分割别名
	    			if(count($subTable) > 1){
	    				if(strtoupper($subTable[1]) === 'AS'){
		    				unset($subTable[1]);	
		    				$subTable = array_values($subTable);
		    			} 
		    			$tempTable[$subTable[0]] = $subTable[1];
	    			} else{
	    				$tempTable[$subTable[0]] = '';
	    			}
	    		}
    		} else {
    			$subTable = explode(' ', $item[0]);
    			if(count($subTable) > 1){
    				if(strtoupper($subTable[1]) === 'AS'){
	    				unset($subTable[1]);
	    				$subTable = array_values($subTable);
	    			}
	    			$tempTable[$subTable[0]] = $subTable[1];
    			} else {
    				$tempTable[$item[0]] = '';
    			}
    		}
    	}
    	foreach ($tempTable as $key => $value) {
    		if(strpos($key, '.') === false){
    			$key = $this->dbName . '.' . $key;		// 如果值只有表名称，添加数据库名称
    			$tempTable[$key] = $value;
    			array_shift($tempTable);
    		}
    	}
    	foreach ($tempTable as $k => $v) {
    		list($dbName, $tableName) = explode('.', $k);
    		$sql = "SHOW TABLES FROM " . '`' . $dbName . '`' . " LIKE " . "'" . $tableName . "'";
    		$result = $this->db->query($sql);
    		if(empty($result)){
    			throw new \Exception("待操作的表：`" . $dbName . "`.'" . $tableName . "' 不存在");
    		}
    	}
    	$this->options['table'] = $tempTable;
		return $this;
    }

    /**
     * Join设置
     * @param  mixed $table    	 支持传入数组
     * @param  string $condition	条件
     * @param  string $type     
     * @return array
     */
    public function join($table='', $condition='', $type='INNER'){
    	if(empty($table)) return $table;
    	$joinArr = array();
    	if(!is_array($table)){			// 为传入的是多组join考虑 不用func_get_args()是因为获取不到默认参数
    		$joinArr[0] = array($table,$condition,$type);
    	} else {
    		$joinArr = $table;
    	}
		$this->options['join'] = $joinArr;
		return $this;
    }

    /**
     * 返回总记录数
     * @param  string $where
     * @return int
     */
    public function count(){
    	$total = count($this->select());
    	return $total;
    }

    /**
     * 获取错误信息
     * @return string
     */
    public function error(){
		return $this->db->error();
	}

    /**
     * 获取数据表字段
     * @param  string $table
     * @return array
     */
    public function getFields($table = ''){
    	$table = empty($table) ? $this->options['table'] : $table;
    	return $this->db->getFields($table);
    }

	/**
	 * 获取主键数组
	 * @param  string $table
	 * @return array
	 */
	public function getPk($table = ''){
		$table = empty($table) ? $this->options['table'] : $table;
		return $this->db->getPk($table);
	}

	/**
	 * 重置options
	 * @return void
	 */
	private function _reset(){
		$this->options = array();
		$tableArr[''] = $this->table;
		$this->options['table'] = array_flip($tableArr);
		$this->options['pk'] = $this->getPk();
		$this->options['debug'] = true;
	}
}