<?php
/**
 * STPHP FRAMEWORK v1.0
 * shengdi_lin <www.i3ui.com>
 * 
 * 数据库操作类
 */
namespace db;

class Mysql{
	static private $_instance;
	protected $pdo;
	protected $stmt;
	protected $selectSql = 'SELECT %FIELD% FROM %TABLE%%JOIN%%WHERE%%GROUP%%HAVING%%ORDER%%LIMIT%';

	/**
	 * 连接数据库
	 */
	public function __construct(){
		if(!class_exists('PDO')) throw new \Exception('不支持PDO，请先开启');
		$dsn = config('DB.DB_TYPE') . ':host=' . config('DB.DB_HOST') . ';dbname=' . config('DB.DB_NAME') . ';port=' . config('DB.DB_POST');
		$user = config('DB.DB_USER');
		$pwd = config('DB.DB_PASSWORD');
		try{
			$this->pdo = new \PDO($dsn, $user, $pwd, array(
				\PDO::MYSQL_ATTR_INIT_COMMAND => 'set names ' . config('DB.DB_CHARSET')
			));
			$this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
			$this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE,\PDO::FETCH_ASSOC);
			$this->pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);	//预处理
		} catch(\PDOException $e){
			throw new \PDOException($e->getMessage());
		}
	}

	/**
	 * 实例化
	 * @return object
	 */
	static public function getInstance(){
		if(!self::$_instance instanceof self){
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * __call方法
	 * @param  string $method
	 * @param  array $args
	 */
	public function __call($method, $args){
		throw new \Exception('您访问的方法' . $method . '()不存在！');
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
		if ($debug === false) $this->_debug($sql);
		try{
			$this->stmt = $this->pdo->prepare($sql);
		} catch(\PDOException $e){
			throw new \Exception('预处理语句出错: ' . $e->getMessage() . '<br> [sql] ' . $sql);
		}
		if(!empty($params)) $this->_bind($params, $sql);
		$this->stmt->execute();
		$result = $mode === 0 ? $this->stmt->fetchAll() : $this->stmt->fetch();
		return $result;
	}

	/**
	 * 运行sql语句
	 */
	public function exec($sql){
		return $this->pdo->exec($sql);
	}

	/**
	 * 查找多条记录
	 * @param  array  $options
	 * @return array
	 */
	public function select($options){
		$sql = $this->parseSql($this->selectSql, $options);
		if ($options['debug'] === false) $this->_debug($sql);
		$this->_log($sql);
		$this->stmt = $this->pdo->query($sql);
		$result = $this->stmt->fetchAll();
		return $result;
	}

	/**
	 * 更新记录 (Medoo)
	 * @param  mixed $data
	 * @return mixed
	 */
	public function update($data, $options){
		$fields = array();
		$data = current($this->_fieldFilter($data, $options['table']));
		foreach ($data as $key => $value){
			preg_match('/([\w]+)(\[(\+|\-|\*|\/)\])?/i', $key, $match);
			if (isset($match[3])){
				if (is_numeric($value)){
					$fields[] = $this->column_quote($match[1]) . ' = ' . $this->column_quote($match[1]) . ' ' . $match[3] . ' ' . $value;
				}
			}
			else{
				$column = $this->column_quote(preg_replace("/^(\(JSON\)\s*|#)/i", "", $key));
				switch (gettype($value)){
					case 'NULL':
						$fields[] = $column . ' = NULL';
						break;
					case 'array':
						preg_match("/\(JSON\)\s*([\w]+)/i", $key, $column_match);
						$fields[] = $column . ' = ' . $this->quote(isset($column_match[0]) ? json_encode($value) : serialize($value));
						break;
					case 'boolean':
						$fields[] = $column . ' = ' . ($value ? '1' : '0');
						break;
					case 'integer':
					case 'double':
					case 'string':
						$fields[] = $column . ' = ' . $this->fn_quote($key, $value);
						break;
				}
			}
		}
		$sql = 'UPDATE ' . $this->parseTable($options['table']) . ' SET ' . implode(', ', $fields) . $this->parseWhere($options['where']);
		$this->_log($sql);
		return $this->pdo->exec($sql);
	}

	/**
	 * 插入数据 （一条或多条 Medoo）
	 * @param  array $datas
	 * @return mixed
	 */
	public function insert($datas, $options=array()){
		$lastId = array();
		$datas = $this->_fieldFilter($datas, $options['table']);
		if (!isset($datas[0])){
			$datas = array($datas);
		}
		foreach ($datas as $data){
			$values = array();
			$columns = array();
			foreach ($data as $key => $value){
				$columns[] = preg_replace("/^(\(JSON\)\s*|#)/i", "", $key);
				switch(gettype($value)){
					case 'NULL':
						$values[] = 'NULL';
						break;
					case 'array':
						preg_match("/\(JSON\)\s*([\w]+)/i", $key, $column_match);
						$values[] = isset($column_match[ 0 ]) ?
							$this->quote(json_encode($value)) :
							$this->quote(serialize($value));
						break;
					case 'boolean':
						$values[] = ($value ? '1' : '0');
						break;
					case 'integer':
					case 'double':
					case 'string':
						$values[] = $this->fn_quote($key, $value);
						break;
				}
			}
			$sql = 'INSERT INTO ' . $this->parseTable($options['table']) . ' (' . implode(', ', $columns) . ') VALUES (' . implode($values, ', ') . ')';
			$this->_log($sql);
			$this->pdo->exec($sql);
			$lastId[] = $this->pdo->lastInsertId();
		}
		return count($lastId) > 1 ? $lastId : $lastId[0];
	}

	/**
	 * 删除记录
	 * @param  array  $options
	 * @return boolean
	 */
	public function delete($options){
		$sql = 'DELETE FROM ' . $this->parseTable($options['table']) . $this->parseWhere($options['where']);
		$this->_log($sql);
		return $this->pdo->exec($sql);
	}

	/**
	 * Sql 语句拼装
	 * @param  string $sql     sql语句模板
	 * @param  array  $options
	 * @return string
	 */
	public function parseSql($sql, $options=array()){
        $sql = str_replace(
            array('%TABLE%','%FIELD%','%JOIN%','%WHERE%','%GROUP%','%HAVING%','%ORDER%','%LIMIT%'),
            array(
            	$this->parseTable($options['table']),
                !empty($options['field']) ? $this->parseField($options['field']) : '*',
                !empty($options['join']) ? $this->parseJoin($options['join']) : '',
                !empty($options['where']) ? $this->parseWhere($options['where']) : '',
                !empty($options['group']) ? $this->parseGroup($options['group']) : '',
                !empty($options['having']) ? $this->parseHaving($options['having']) : '',
                !empty($options['order']) ? $this->parseOrder($options['order']) : '',
                !empty($options['limit']) ? $this->parseLimit($options['limit']) : '',
            ),$sql);
        return $sql;
    }

	/**
     * 表名拼装
     * @param  array $table
     * @return string
     */
    protected function parseTable($table){
    	$tableStr = '';
    	foreach ($table as $key => $value) {
    		list($dbName, $tableName) = explode('.', $key);
    		$dbName = $this->_parseKey($dbName);
    		$tableName = $this->_parseKey($tableName);
    		$value = $this->_parseKey($value);
    		if(empty($value)){
    			$tableStr .= $dbName . '.' . $tableName . ',';
    		} else {
    			$tableStr .= $dbName . '.' . $tableName . ' AS ' . $value . ',';
    		}
    	}
    	return rtrim($tableStr, ',');
    }

    /**
     * 字段拼装
     * @param  mixed $fields
     * @return string
     */
    protected function parseField($fields){
    	if(is_string($fields) && '' !== $fields){
    		$fields = explode(',', $fields);
    	}
    	if(is_array($fields)) {
            $array = array();
            foreach ($fields as $key => $field){
                if(!is_numeric($key)){
                    $array[] =  $this->_parseKey($key).' AS '.$this->_parseKey($field);
                } else {
                    $array[] =  $this->_parseKey($field);
                }
            }
            $fieldsStr = implode(',', $array);
        }else{
            $fieldsStr = '*';
        }
        return $fieldsStr;
    }

    /**
     * Join拼装
     * @param  array $join
     * @return string
     */
    protected function parseJoin($join) {
    	$joinStr = '';
    	$conditionStr = '';
    	foreach ($join as $key => $value) {
    		if(!empty($value[1])){
    			if(strpos($value[1], '=')){
					$conditionStr = ' ON (' . $this->_parseKey($value[1]) . ') ';
				} else {
					$conditionStr = ' USING (' . $this->_parseKey($value[1]) . ') ';
				}
    		}
    		$joinStr .= strtoupper($value[2]) . ' JOIN ' . $value[0] . $conditionStr;
		}
        return ' ' . $joinStr;
    }

    /**
     * Where拼装 （Medoo）
     * @param  mixed $where
     * @return string
     */
    protected function parseWhere($where){
    	$whereStr = '';
		if(is_array($where)){
			$where_keys = array_keys($where);
			$where_AND = preg_grep("/^AND\s*#?$/i", $where_keys);
			$where_OR = preg_grep("/^OR\s*#?$/i", $where_keys);

			$single_condition = array_diff_key($where, array_flip(		// 排除以下类型的数组，获取单句
				array('AND', 'OR', 'GROUP', 'ORDER', 'HAVING', 'LIMIT', 'LIKE', 'MATCH')
			));
			if($single_condition != array()){
				$condition = $this->data_implode($single_condition, '');	// 拼装单句
				if($condition != ''){
					$whereStr = ' WHERE ' . $condition;
				}
			}
			if (!empty($where_AND)){
				$value = array_values($where_AND);
				$whereStr = ' WHERE ' . $this->data_implode($where[$value[0]], ' AND');		// 将$where里面的AND组去拼装
			}
			if(!empty($where_OR)){
				$value = array_values($where_OR);
				$whereStr = ' WHERE ' . $this->data_implode($where[$value[0]], ' OR');
			}
			if(isset($where['MATCH'])){
				$MATCH = $where['MATCH'];
				if(is_array($MATCH) && isset($MATCH['columns'], $MATCH['keyword'])){
					$whereStr .= ($whereStr != '' ? ' AND ' : ' WHERE ') . ' MATCH ("' . str_replace('.', '"."', implode($MATCH[ 'columns' ], '", "')) . '") AGAINST (' . $this->quote($MATCH[ 'keyword' ]) . ')';
				}
			}
			if(isset($where['GROUP'])){
				$whereStr .= ' GROUP BY ' . $this->column_quote($where['GROUP']);	// 加上字段引号
				if(isset($where['HAVING'])){
					$whereStr .= ' HAVING ' . $this->data_implode($where['HAVING'], ' AND');
				}
			}
			if(isset($where['ORDER'])){
				$ORDER = $where['ORDER'];
				if(is_array($ORDER)){
					$stack = array();
					foreach ($ORDER as $column => $value){
						if(is_array($value)){
							$stack[] = 'FIELD(' . $this->column_quote($column) . ', ' . $this->array_quote($value) . ')';
						} else if($value === 'ASC' || $value === 'DESC'){
							$stack[] = $this->column_quote($column) . ' ' . $value;
						} else if (is_int($column)){
							$stack[] = $this->column_quote($value);
						}
					}
					$whereStr .= ' ORDER BY ' . implode($stack, ',');
				}else{
					$whereStr .= ' ORDER BY ' . $this->column_quote($ORDER);
				}
			}
			if(isset($where['LIMIT'])){
				$LIMIT = $where[ 'LIMIT' ];
				if (is_numeric($LIMIT)){
					$whereStr .= ' LIMIT ' . $LIMIT;
				}
				if(is_array($LIMIT) && is_numeric($LIMIT[0]) && is_numeric($LIMIT[1])){
					$whereStr .= ' LIMIT ' . $LIMIT[ 0 ] . ',' . $LIMIT[ 1 ];
				}
			}
		}else{
			if($where != null){
				$where = $this->_parseKey($where);
				$whereStr .= ' WHERE ' . $where;
			}
		}
		return $whereStr;
    }

    /**
     * Medoo数据拼装
     * @param  mixed $data
     * @param  string $conjunctor
     * @param  string $outer_conjunctor
     * @return string
     */
    protected function data_implode($data, $conjunctor, $outer_conjunctor = null){
		$wheres = array();
		foreach($data as $key => $value){
			$type = gettype($value);
			if(preg_match("/^(AND|OR)(\s+#.*)?$/i", $key, $relation_match) && $type == 'array'){
				$wheres[] = 0 !== count(array_diff_key($value, array_keys(array_keys($value)))) ?
					'(' . $this->data_implode($value, ' ' . $relation_match[ 1 ]) . ')' :
					'(' . $this->inner_conjunct($value, ' ' . $relation_match[ 1 ], $conjunctor) . ')';
			}else{
				preg_match('/(#?)([\w\.\-]+)(\[(\>|\>\=|\<|\<\=|\!|\<\>|\>\<|\!?~)\])?/i', $key, $match);
				$column = $this->column_quote($match[2]);
				if(isset($match[4])){
					$operator = $match[4];
					if($operator == '!'){
						switch($type){
							case 'NULL':
								$wheres[] = $column . ' IS NOT NULL';
								break;
							case 'array':
								$wheres[] = $column . ' NOT IN (' . $this->array_quote($value) . ')';
								break;
							case 'integer':
							case 'double':
								$wheres[] = $column . ' != ' . $value;
								break;
							case 'boolean':
								$wheres[] = $column . ' != ' . ($value ? '1' : '0');
								break;
							case 'string':
								$wheres[] = $column . ' != ' . $this->fn_quote($key, $value);
								break;
						}
					}
					if($operator == '<>' || $operator == '><'){
						if ($type == 'array'){
							if ($operator == '><'){
								$column .= ' NOT';
							}
							if (is_numeric($value[0]) && is_numeric($value[1])){
								$wheres[] = '(' . $column . ' BETWEEN ' . $value[0] . ' AND ' . $value[1] . ')';
							} else{
								$wheres[] = '(' . $column . ' BETWEEN ' . $this->quote($value[0]) . ' AND ' . $this->quote($value[1]) . ')';
							}
						}
					}
					if($operator == '~' || $operator == '!~'){
						if($type != 'array'){
							$value = array($value);
						}
						$likeStr = array();
						foreach ($value as $item){
							$item = strval($item);
							$suffix = mb_substr($item, -1, 1);
							if(preg_match('/^(?!(%|\[|_])).+(?<!(%|\]|_))$/', $item)){
								$item = '%' . $item . '%';
							}
							$likeStr[] = $column . ($operator === '!~' ? ' NOT' : '') . ' LIKE ' . $this->fn_quote($key, $item);
						}
						$wheres[] = implode(' OR ', $likeStr);
					}
					if(in_array($operator, array('>', '>=', '<', '<='))){
						if (is_numeric($value)){
							$wheres[] = $column . ' ' . $operator . ' ' . $value;
						} elseif (strpos($key, '#') === 0) {
							$wheres[] = $column . ' ' . $operator . ' ' . $this->fn_quote($key, $value);
						} else {
							$wheres[] = $column . ' ' . $operator . ' ' . $this->quote($value);
						}
					}
				} else {
					switch ($type){
						case 'NULL':
							$wheres[] = $column . ' IS NULL';
							break;
						case 'array':
							$wheres[] = $column . ' IN (' . $this->array_quote($value) . ')';
							break;
						case 'integer':
						case 'double':
							$wheres[] = $column . ' = ' . $value;
							break;
						case 'boolean':
							$wheres[] = $column . ' = ' . ($value ? '1' : '0');
							break;
						case 'string':
							$wheres[] = $column . ' = ' . $this->fn_quote($key, $value);
							break;
					}
				}
			}
		}
		return implode($conjunctor . ' ', $wheres);
	}

	/**
	 * Medoo字段名加引号
	 * @param  string $string
	 * @return string
	 */
	protected function column_quote($string){
		preg_match('/(\(JSON\)\s*|^#)?([a-zA-Z0-9_]*)\.([a-zA-Z0-9_]*)/', $string, $column_match);
		if (isset($column_match[2], $column_match[3])){
			return '`' . $this->prefix . $column_match[2] . '`.`' . $column_match[3] . '`';
		}
		return '`' . $string . '`';
	}

	/**
	 * Medoo，Mysql内置方法
	 * @param  [type] $data            
	 * @param  [type] $conjunctor      
	 * @param  [type] $outer_conjunctor
	 * @return [type]                  
	 */
	protected function inner_conjunct($data, $conjunctor, $outer_conjunctor){
		$haystack = array();
		foreach ($data as $value){
			$haystack[] = '(' . $this->data_implode($value, $conjunctor) . ')';
		}
		return implode($outer_conjunctor . ' ', $haystack);
	}

	/**
	 * Medoo，Mysql内置方法
	 * @param  string $column
	 * @param  string $string
	 * @return string
	 */
	protected function fn_quote($column, $string){
		return (strpos($column, '#') === 0 && preg_match('/^[A-Z0-9\_]*\([^)]*\)$/', $string)) ? $string : $this->quote($string);
	}

	/**
	 * Medoo 字符串数组处理
	 * @param  array $array
	 * @return string
	 */
	protected function array_quote($array){
		$temp = array();
		foreach($array as $value){
			$temp[] = is_int($value) ? $value : $this->pdo->quote($value);
		}
		return implode($temp, ',');
	}

	/**
     * group 拼装
     * @param  string $group
     * @return string
     */
    protected function parseGroup($group){
    	if(is_string($group)){
    		return ' GROUP BY ' . $this->_parseKey($group);
    	}
    }

    /**
     * having 拼装
     * @param  string $having
     * @return string
     */
    protected function parseHaving($having){
    	return ' HAVING ' . $having;
    }

    /**
     * order 拼装
     * @param  string $order
     * @return string
     */
    protected function parseOrder($order){
    	if(is_array($order)){
            $array   =  array();
            foreach($order as $key=>$val){
                if(is_numeric($key)){
                    $array[] =  $this->_parseKey($val);
                }else{
                    $array[] =  $this->_parseKey($key).' '.$val;
                }
            }
            $order   =  implode(',',$array);
        }
        return !empty($order)?  ' ORDER BY '.$order:'';
    }

    /**
     * limit 拼装
     * @param  mixed $limit
     * @return string
     */
    protected function parseLimit($limit) {
        return !empty($limit)?   ' LIMIT '.$limit.' ':'';
    }

    /**
     * 获取数据表字段
     * @param  string $table
     * @return array
     */
    public function getFields($table){
    	$result = $this->_getDbFields($table);
    	return array_keys($result);
    }

    /**
	 * 获取主键
	 * @param  string $table
	 * @return array
	 */
	public function getPk($table){
		$fields = $this->_getDbFields($table);
		$pk = array();
    	foreach ($fields as $key => $value) {
    		$val = array_change_key_case($value);
    		if($val['key'] === 'PRI'){
    			array_push($pk, $val['field']);
    		}
    	}
    	return $pk;
	}

	/**
	 * PDO 字符串转义加引号 
	 * @param  string $string
	 * @return string
	 */
	public function quote($string){
		return $this->pdo->quote($string);
	}

	/**
     * 获取错误信息
     * @return string
     */
    public function error(){
		return $this->pdo->errorInfo();
	}

	/**
	 * 预处理绑定值
	 * @param  mixed $params
	 * @param  string $sql
	 * @return void
	 */
	private function _bind($params, $sql){
		if(is_numeric($params) || is_string($params)){
			$this->stmt->bindValue(1,$params);
		}
		if(is_array($params)){
			// 如果传入索引数组，因为?绑定索引从1开始，所以加个0下标占位
			if(array_search(current($params), $params) === 0){
				$res[0] = array('?');
				$params = array_merge($res[0],$params);
			}
			foreach ($params as $key => $value) {
				if($key !== 0){
					$this->stmt->bindValue($key, $value);
				}
			}
		}
	}

	/**
	 * 数据字段过滤
	 * @param  array $data
	 * @return array
	 */
	private function _fieldFilter($data, $table){
		$field = $this->getFields($table);
		if (!isset($data[0])){
			$data = array($data);
		}
		foreach ($data as $key => $value) {
			$unsetname = array_diff(array_keys($value), $field);
			foreach ($unsetname as $k) {
				unset($value[$k]);
			}
			$data[$key] = $value;
		}
		return $data;
	}

	/**
     * 获取数据表字段详细信息
     * @param  string $table
     * @return array
     */
    private function _getDbFields($table){
    	$table = $this->parseTable($table);
    	$sql = 'SHOW COLUMNS FROM ' . $table;
    	$result = $this->query($sql);
    	$info = array();
    	foreach ($result as $key => $value) {
    		$info[$value['Field']] = $value;
    	}
    	return $info;
    }

    /**
     * 字段处理
     * @param  string  $str
     * @param  boolean $value
     * @return string
     */
    private function _parseKey($str, $value=false){
    	$parseStr = '';
		$str = preg_replace('/[`\'\"]/','',$str);	//先去掉所有引号，再重新处理
		$strArr = explode('=', $str);
		if(count($strArr) > 1){
			$strArr[0] = preg_replace('/(\w+)/','`$1`',$strArr[0]);
			if(strpos($strArr[1], '.') === false){
				$strArr[1] = $this->quote($strArr[1]);
			} else {
				$strArr[1] = preg_replace('/(\w+)/','`$1`',$strArr[1]);
			}
			$parseStr = implode('=', $strArr);
		} else{
			if($value == true){
				$strArr[0] = $this->quote($strArr[0]);
			} else {
				$parseStr = preg_replace('/(\w+)/','`$1`',$strArr[0]);
			}
		}
		return $parseStr;
    }

    /**
     * 存储SQL记录
     * @param  string $sql
     */
    private function _log($sql){
    	$info = ' :: SQL' . PHP_EOL . $sql;
    	$file = RUNTIME_PATH . 'log' . DS . date('Y-m-d') . 'sql.log';
		\lib\Log::log($info, $file);
    }

    /**
     * 输出信息
     */
    private function _debug($debug){
    	p($debug);
		exit;
	}

	/**
	 * clone
	 */
	private function __clone(){}
}