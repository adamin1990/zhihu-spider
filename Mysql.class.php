<?php
/**
 * mysql类库
 *
 * @license 	General Public License
 * @author		junlong.yang at 2012-9-24 PM3:21:42 build
 * @link		http://dev.crossyou.cn/
 * @version		$Id$
 */

class Mysql{
	/**
	 * 将dbh声明为私有成员
	 * 
	 * @access private
	 * @var resource 
	 */
	private $dbh;
	
	/**
	 * 查询(query)结果
	 *
	 * @access private
	 * @var resource
	 */
	private $result;
	
	/**
	 * Sql查询语句
	 *
	 * @author crossyou
	 * @since 1.0.0
	 * @var String
	 */
	private $query;
	
	/**
	 * 执行 query 的次数
	 *
	 * @author crossyou
	 * @since 1.0.0
	 * @var int
	 */
	private $num_query			= 0;
	
	/**
	 * 上一次执行 query 所影响的行数
	 *
	 * @author crossyou
	 * @since 1.0.0
	 * @var int
	 */
	private $rows_affected		= 0;
	
	/**
	 * 上一次INSERT INTO语句的id
	 *
	 * @author crossyou
	 * @since 1.0.0
	 * @var int
	 */
	private $insert_id			= 0;
	
	/**
	 * 上一次查询 所影响的函数 包括 SELECT~~
	 * 
	 * @access private
	 * @var int
	 */
	private $num_results 		= 0;
	
	/**
	 * PHP5构造函数
	 * 
	 * 函数原型：resource mysql_connect ([ string $server [, string $username [, string $password [, bool $new_link [, int $client_flags ]]]]] ) 
	 */
	public function __construct($host, $user, $pass,$name){
		if(!$this->dbh = @mysql_connect($host, $user, $pass)){
			$this->error('Can not connect to MySQL server');
		}
		
		$this->select_db($name);

		register_shutdown_function(array(&$this, "close"));
	}
	
	/**
	 * 设置mysql字符编码 UTF8/GBK
	 * 
	 * @param String $charset
	 */
	public function set_char($charset = 'UTF8'){
		@mysql_query('SET NAMES '.$charset, $this->dbh);
	}
	
	/**
	 * 选择数据库
	 *
	 * 函数原型：bool mysql_select_db ( string $database_name [, resource $link_identifier ] )
	 * 如果成功则返回 TRUE，失败则返回 FALSE。
	 * 设定与指定的连接标识符所关联的服务器上的当前激活数据库。
	 * 如果没有指定连接标识符，则使用上一个打开的连接。如果没有打开的连接，
	 * 本函数将无参数调用 mysql_connect() 来尝试打开一个并使用之。
	 *
	 * @author crossyou
	 * @since 1.0.0
	 * @param String $name
	 */
	public function select_db($name, $dbh = ''){
		$_dbh = empty($dbh) ? $this->dbh : $dbh;
		if(!@mysql_select_db($name, $_dbh)){
			$this->error("Can not link to '$name' Database");
		}
	}
	
	/**
	 * 发送一条 MySQL 查询
	 * 返回影响的行数~~	
	 *
	 * 函数原型：resource mysql_query ( string $query [, resource $link_identifier ] )
	 * 仅对 SELECT，SHOW，EXPLAIN 或 DESCRIBE 语句返回一个资源标识符，
	 * 如果查询执行不正确则返回 FALSE。对于其它类型的 SQL 语句，mysql_query()在执行成功时返回TRUE，
	 * 出错时返回 FALSE。非 FALSE 的返回值意味着查询是合法的并能够被服务器执行。
	 * 这并不说明任何有关影响到的或返回的行数。
	 * 很有可能一条查询执行成功了但并未影响到或并未返回任何行。
	 *
	 * @author crossyou
	 * @since 1.0.0
	 * @param string $sql
	 */
	public function query($query, $dbh = null){
		$this->query = $query;
		$_dbh = empty($dbh) ? $this->dbh : $dbh;
		
		if(!($this->result = @mysql_query($query, $_dbh))) {
			$this->error('MySQL Query Error,Sql:'.$query);
		}
		
		$this->num_query++;
		
		if (preg_match("/^\\s*(insert|delete|update|replace|alter) /i", $query )) {
			//取得最近一次与 link_identifier 关联的 INSERT，UPDATE 或 DELETE 查询所影响的记录行数
			$this->rows_affected = $this->affected_rows($_dbh);
		
			if (preg_match( "/^\\s*(insert|replace) /i", $query )) {
				$this->insert_id = $this->insert_id($_dbh);
			}
			$this->num_results = $this->rows_affected;
		}
		
		if (preg_match( "/^\\s*(select) /i", $query )){
			$this->num_results = $this->num_rows($this->result);
		}
		
		return $this->result;
	}
	
	//~~
	public function num_results(){
		return $this->num_results;
	}
	
	/**
	 * 以数组格式将数据插入到表中，数据键值对应表字段和值
	 * 封装的一个简单的数据插入函数~~
	 * 
	 * @author crossyou
	 * @final
	 * @param String $table
	 * @param Array  $data
	 * @return int
	 */
	public function insert($table, $data) {
		$sql = "INSERT INTO `".$table."` ( `";
		$sql.= implode('`,`', array_keys($data)).'` ) VALUES ( ';
		$sql.= "'".implode("','",$data)."' ) ";
		
		$this->query($sql);
		return $this->insert_id;
	}
	
	/**
	 * 根据输入的条件删除某条数据行
	 * 返回影响的行数
	 * 
	 * @param String $table
	 * @param Mix $where
	 * @return number
	 */
	public function delete($table, $where){
		$sql = "DELETE FROM ". $table;
		$sql.= "WHERE".$this->get_where_sql($where);
		
		$this->query($sql);
		return $this->rows_affected;
	}
	
	/**
	 * 将键值对数组数据更新到指定表中
	 * 一个简单封装的方法~~
	 * 
	 * @param string $table
	 * @param array $data
	 * @param array||string $where
	 * @return int
	 */
	public function update($table, $data, $where){
		if(!is_array( $data )){
			$this->error('update() param 2th is error');
		}
			
		foreach($data as $k=>$v){
			$datas[] = '`'.$k."` = '$v'";
		}
		
		$sql = "UPDATE ". $table ." SET ";
		$sql.= implode(' , ', $datas);
		$sql.= " WHERE ".$this->get_where_sql($where);
		
		$this->query($sql);
		return $this->rows_affected;
	}
	
	/**
	 * 如果没有此数据则插入，否则就更新~~
	 * 
	 * @since 2013/04/10
	 */
	public function save($table, $data, $where){
		$sql = "SELECT * FROM ". $table;
		$sql.= " WHERE ".$this->get_where_sql($where);
		
		$this->query($sql);
		
		if(($this->num_results()) > 0){
			$this->update($table, $data, $where);
		} else {
			$this->insert($table, $data);
		}
	}
	
	
	//tools ~~ fun
	
	/**
	 * 生成sql条件语句
	 * 
	 * Enter description here ...
	 * @param unknown_type $where
	 * @return string
	 */
	public function get_where_sql($where){
		if(is_array($where)){
			foreach($where as $k=>$v){
				$wheres[] = $k." = '$v'";
			}
		}else{
			$wheres[] = $where;
		}
		return implode(' AND ', $wheres);
	}
	
	/**
	 * 从结果集中取得一行作为对象
	 *
	 * 函数原型：object mysql_fetch_object ( resource $result )
	 * 返回根据所取得的行生成的对象，如果没有更多行则返回 FALSE。
	 * mysql_fetch_object() 和 mysql_fetch_array() 类似，
	 * 只有一点区别 - 返回一个对象而不是数组。
	 * 间接地也意味着只能通过字段名来访问数组，
	 * 而不是偏移量（数字是合法的属性名）。
	 * 速度上，本函数和  mysql_fetch_array()一样，也几乎和  mysql_fetch_row()一样快（差别很不明显）。
	 * 本函数返回的字段名是区分大小写的。
	 *
	 * @final
	 * @return object
	 */
	public function fetch_object($result){
		$_result = empty($result) ? $this->result : $result;
		return mysql_fetch_object($_result);
	}
	
	/**
	 * 从结果集中取得一行作为关联数组，或数字数组，或二者兼有
	 *
	 * 函数原型：array mysql_fetch_array ( resource $result [, int $ result_type ] )
	 * 返回根据从结果集取得的行生成的数组，如果没有更多行则返回 FALSE。
	 * mysql_fetch_array() 是 mysql_fetch_row() 的扩展版本。
	 * 除了将数据以数字索引方式储存在数组中之外，还可以将数据作为关联索引储存，用字段名作为键名。
	 * 如果结果中的两个或以上的列具有相同字段名，最后一列将优先。
	 * 要访问同名的其它列，必须用该列的数字索引或给该列起个别名。
	 * 对有别名的列，不能再用原来的列名访问其内容
	 * mysql_fetch_array() 中可选的第二个参数 result_type 是一个常量，可以接受以下值：
	 * MYSQL_ASSOC，MYSQL_NUM 和 MYSQL_BOTH。
	 * 本特性是 PHP 3.0.7 起新加的。
	 * 本参数的默认值是 MYSQL_BOTH。 
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function fetch_array($result, $type = MYSQL_BOTH){
		$_result = empty($result) ? $this->result : $result;
		return mysql_fetch_array($_result, $type);
	}
	
	/**
	 * 从结果集中取得一行作为关联数组
	 * 返回根据从结果集取得的行生成的关联数组，如果没有更多行则返回 FALSE
	 * 
	 * 函数原型：array mysql_fetch_assoc ( resource $result )
	 * mysql_fetch_assoc() 和用 mysql_fetch_array() 加上第二个可选参数 MYSQL_ASSOC 完全相同。
	 * 它仅仅返回关联数组。这也是 mysql_fetch_array() 起初始的工作方式。
	 * 如果在关联索引之外还需要数字索引，用 mysql_fetch_array()。
	 * 
	 * @final
	 * @param resource $result
	 * @return multitype:
	 */
	public function fetch_assoc($result = null){
		$_result = empty($result) ? $this->result : $result;
		return mysql_fetch_assoc($_result);
	}
	
	/**
	 * 从结果集中取得一行作为枚举数组
	 * 返回根据所取得的行生成的数组，如果没有更多行则返回 FALSE。 
	 * mysql_fetch_row() 从和指定的结果标识关联的结果集中取得一行数据并作为数组返回。
	 * 每个结果的列储存在一个数组的单元中，偏移量从 0 开始。
	 * 依次调用 mysql_fetch_row() 将返回结果集中的下一行，如果没有更多行则返回 FALSE。 
	 * 
	 * @final
	 * @param resource $result
	 * @return multitype:
	 */
	public function fetch_row($result){
		$_result = empty($result) ? $this->result : $result;
		return mysql_fetch_row($_result);
	}

	public function fetch_all($result, $type = MYSQLI_ASSOC){
        while ( $row = $this->fetch_array($result, $type) ){
            $rows[] = $row;
        }
        mysql_free_result($result);
      
        return empty($rows) ? false : $rows;
    }
	
	/**
	 * 取得结果集中行的数目
	 * 
	 * 函数原型：int mysql_num_rows ( resource $result )
	 * mysql_num_rows() 返回结果集中行的数目。此命令仅对 SELECT 语句有效。
	 * 要取得被 INSERT，UPDATE 或者 DELETE 查询所影响到的行的数目，
	 * 用 mysql_affected_rows()。 
	 * 
	 * @final
	 * @param resource $dbh
	 * @return int
	 */
	public function num_rows($result){
		$_result = empty($result) ? $this->result : $result;
		return mysql_num_rows($_result);
	}
	
	
	/**
	 * 取得前一次 MySQL 操作所影响的记录行数
	 *
	 * 函数原型：int mysql_affected_rows ([ resource $link_identifier ] )
	 * 取得最近一次与 link_identifier 关联的 INSERT，UPDATE 或 DELETE 查询所影响的记录行数。
	 * 
	 * @final
	 * @param resource $dbh
	 * @return int
	 */
	public function affected_rows($dbh){
		$_dbh = empty($dbh) ? $this->dbh : $dbh;
		return mysql_affected_rows($_dbh);
	}
	
	/**
	 * 取得上一步 INSERT 操作产生的 ID 
	 * 
	 * 函数原型：int mysql_insert_id ([ resource $link_identifier ] )
	 * mysql_insert_id() 返回给定的 link_identifier 中上一步 INSERT 查询中产生的 AUTO_INCREMENT 的 ID 号。
	 * 如果没有指定 link_identifier ，则使用上一个打开的连接。 
	 * 如果上一查询没有产生 AUTO_INCREMENT 的值，则 mysql_insert_id() 返回 0。
	 * 如果需要保存该值以后使用，要确保在产生了值的查询之后立即调用 mysql_insert_id()。 
	 * 
	 * @final
	 * @param resource $dbh
	 * @return number
	 */
	public function insert_id($dbh){
		$_dbh = empty($dbh) ? $this->dbh : $dbh;
		return mysql_insert_id($_dbh);
	}
	
	/**
	 * 一个到 MySQL 服务器的连接资源符
	 * 
	 * @final
	 * @return resource
	 */
	public function get_dbh(){
		return $this->dbh;
	}
	
	/**
	 * 获取上次query查询的SQL语句
	 * 
	 * @access public
	 * @return String
	 */
	public function get_sql(){
		return $this->query;
	}
	
	public function close($dbh = ''){
		$_dbh = empty($dbh) ? $this->dbh : $dbh;
		return mysql_close($_dbh);
	}
	
	private function error($msg, $exit = true){
		file_put_contents('sql_error', $msg);
		//TODO 使用MVC中Router类中的error方法~~
		R::getInstance()->error($msg, $exit);
	}
	
	private function dbIN($ids){
		if(is_array($ids)) {
			return "IN ('" . join("','", $ids) . "')";
		}
		return "IN ('" . str_replace(',', "','", $ids) . "')";
	}
}

/* End of file: Mysql.class.php*/
/* Location: ./Mysql.class.php*/