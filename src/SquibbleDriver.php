<?php
namespace Dorsataio\Squibble;

/**
 * 
 */
abstract class SquibbleDriver{

	// Database connection parameters. The password is not stored and is only
	// used at the time of connection.
	protected static $_host = null;
	protected static $_port = null;
	protected static $_dbname = null;
	protected static $_user = null;
	// Database PDO object
	protected static $_pdo = null;
	// PDO Placeholders protect against SQL injection, since the data never gets inserted 
	// into the SQL query.
	protected $_placeholders = [];
	// A database table to perform the SQL on. It is optional to set another SQL as the
	// table to select from when using the SELECT sql.
	protected $_table = null;
	// Standard sql statement
	protected $_sql = null;
	// The DISTINCT clause is used in the SELECT statement to remove duplicate rows from the result set. 
	protected $_distinctClause = null;
	// JOIN clause to select data from multiple tables.
	protected $_joinClause = null;
	// WHERE clause to filter rows returned from the SELECT statement.
	protected $_whereClause = null;
	// The GROUP BY clause divides the rows returned from the SELECT statement into groups.
	protected $_groupByClause = null;
	// The ORDER BY clause allows you to sort the rows returned from the SELECT statement in ascending or descending order
	protected $_orderByClause = null;
	// LIMIT is an optional clause of the SELECT statement that gets a subset of rows returned by a query.
	protected $_limitOffsetClause = null;
	// Similar to a SELECT statement appended to an INSERT, UPDATE or DELETE statement to return rows
	// that were affected. This is only support by PostgreSQL.
	protected $_returningClause = null;

	// All database specific drivers extending SquibbleDriver must implement the following abstract methods.
	// 
	// Return database specific Database Source Name string used for establishing a connection.
	protected abstract static function getDsn($password);
	// Format table, columns, aliases, etc... with proper quotes
	protected abstract function formatWithQuote($s, $type = 'column');
	// Format a PHP array into a format that is acceptable by the database
	protected abstract function formatArrayValue(array $value);
	// Returns an array of database specific JOIN types; for example: [>] => LEFT JOIN
	protected abstract function joinTypes();
	// Returns an array of database sepcific operator types; for example: [<>] => BETWEEN
	protected abstract function operatorTypes();
	// Returns the final well formatted SQL statement
	protected abstract function getSqlStatement();
	// Returns a well formatted SELECT statement (does not include JOIN, WHERE, etc...)
	protected abstract function getSelectStatement(array $columns);
	// Returns a well formatted INSERT statement
	protected abstract function getInsertStatement(array $columnSets);
	// Returns a well formatted UPDATE statement
	protected abstract function getUpdateStatement(array $columns);
	// Returns a well formatted DELETE statement
	protected abstract function getDeleteStatement();
	// Returns a well formatted DISTINCT statement
	protected abstract function getDistinctStatement(array $columns);
	// Returns a well formatted JOIN clause to be appended to a SELECT statement
	protected abstract function getJoinClause(array $joins);
	// Returns a well formatted WHERE clause to be appended
	protected abstract function getWhereClause(array $conditions);
	// Returns a well formatted GROUP BY clause to be appended to a SELECT statement
	protected abstract function getGroupByClause(array $grouping);
	// Returns a well formatted ORDER BY clause to be appended to a SELECT statement
	protected abstract function getOrderByClause(array $sorts);
	// Returns a well formatted LIMIT,OFFSET clause to be appended to a SELECT statement
	protected abstract function getLimitOffsetClause($limit, $offset = 0);
	// Returns a well formatted RETURNING clause to be appended to an UPDATE, DELETE or INSERT statement
	protected abstract function getReturningClause(array $columns);

	/**
	 * New Driver
	 * Set PDO object if provided
	 *
	 * @method __construct
	 *
	 * @param  object      $pdo PHP Data Objects (PDO) for accessing database.
	 */
	public function __construct($pdo = null){
		self::$_pdo = $pdo;
	}

	/**
	 * Automatically return the SQL string when this class is cast as a string.
	 *
	 * @method __toString
	 *
	 * @return string     Formatted SQL statement.
	 */
	public function __toString(){
		return $this->queryString();
	}

	/**
	 * Translate a proprietary operator pattern to an actual SQL operator. Database
	 * specific drivers have the option of ovveriding standard operators by returning
	 * a matching key (operator pattern) with its own value as the operator within the
	 * method "operatorTypes()".
	 *
	 * @method stdOperatortypes
	 *
	 * @param  string           $s An operator pattern such as "[<>]" translating to BETWEEN.
	 *
	 * @return string              An SQL operator. 
	 */
	protected function stdOperatortypes($s = ''){
		$s = (string) $s;
		// Default to [=]
		if(empty($s)){
			$s = '[=]';
		}
		$operators = [
			'[=]' => '=',
			'[!]' => '!=',
			'[!=]' => '!=',
			'[>]' => '>',
			'[<]' => '<',
			'[>=]' => '>=',
			'[<=]' => '<=',
			'[<>]' => 'BETWEEN',
			'[><]' => 'NOT BETWEEN',
			'[null]' => 'IS NULL',
			'[!null]' => 'IS NOT NULL',
			'[in]' => 'IN',
			'[!in]' => 'NOT IN',
			'[~]' => 'LIKE',
			'[!~]' => 'NOT LIKE',
		];
		$operators = array_merge($operators, (array) $this->operatorTypes());
		return (isset($operators[$s]) ? $operators[$s] : '');
	}

	/**
	 * [stdJoinTypes description]
	 *
	 * @method stdJoinTypes
	 *
	 * @param  [type]       $s [description]
	 *
	 * @return [type]          [description]
	 */
	private function stdJoinTypes($s){
		$s = (string) $s;
		$joinTypes = [
			'[>]' => 'LEFT JOIN',
			'[<]' => 'RIGHT JOIN',
			'[><]' => 'INNER JOIN',
			'[<>]' => 'FULL OUTER JOIN'
		];
		$joinTypes = array_merge($joinTypes, (array) $this->joinTypes());
		return (isset($joinTypes[$s]) ? $joinTypes[$s] : 'LEFT JOIN'); 
	}

	/**
	 * Establish a new database connection (PDO).
	 *
	 * @method connect
	 *
	 * @param  string  $host     Database hostname or IP.
	 * @param  integer $port     Database server port.
	 * @param  string  $dbname   Database name.
	 * @param  string  $user     Database login username.
	 * @param  string  $password Database login password.
	 *
	 * @return object            PHP Data Objects (PDO) for accessing database.
	 */
	public static function connect($host, $port, $dbname, $user, $password){
		$conn = null;
		try{
			self::$_host = trim((string) $host);
			self::$_port = trim((string) $port);
			self::$_dbname = trim((string) $dbname);
			self::$_user = trim((string) $user);
			// create a database connection
			$driver = get_called_class();
			$conn = new \PDO($driver::getDsn((string) $password));
			// Use the following to make it throw an exception
			$conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
		}catch(\PDOException $e){
			$conn = $e;
		}catch(\Exception $e){
			$conn = $e;
		}
		// Return a PDO connection object or an exception object
		return $conn;
	}

	/**
	 * Get specific connection parameters. Unfortunately the password is used during the
	 * connection phase and is not stored therefore not returnable.
	 *
	 * @method getConnection
	 *
	 * @param  string        $filter A database connection parameter (host, port, dbname, user).
	 *                               If none is supplied, the PDO object is returned instead.
	 *
	 * @return mixed                 A database connection parameter (host, port, dbname, user) value.
	 *                               If none is supplied, the PDO object is returned instead.
	 */
	public static function getConnection($filter = ''){
		switch ($filter) {
			case 'host':
				return self::$_host;
				break;

			case 'port':
				return self::$_port;
				break;

			case 'dbname':
				return self::$_dbname;
				break;

			case 'user':
				return self::$_user;
				break;
			
			default:
				return self::$_pdo;
				break;
		}
	}

	/**
	 * Returns a well formatted SQL statement (SELECT, INSERT, UPDATE, DELETE, etc...)
	 *
	 * @method queryString
	 *
	 * @param  boolean     $bind When set to true, the values will be binded to the returning
	 *                           SQL statement. All placeholders would have been removed at this
	 *                           point. It is not recommended to execute a SQL statement with values
	 *                           instead of placeholders.
	 *
	 * @return string            A well formatted SQL statement ready for execution.
	 */
	public function queryString($bind = false){
		// Ensure we have at least a select statement
		if(empty($this->_sql)){
			$this->select(['*']);
		}
		$sql = $this->getSqlStatement();
		if($bind === true){
			foreach($this->_placeholders as $key => $value){
				$sql = str_replace($key, (is_string($value) ? "'{$value}'" : $value), $sql);
			}
		}
		return $sql;
	}

	/**
	 * Returns an array containing NVP for the placeholders used within a SQL statement.
	 *
	 * @method queryData
	 *
	 * @return array    Placeholder NVP array.
	 */
	public function queryData(){
		return $this->_placeholders;
	}

	/**
	 * Set a table or subquery to execute the query on.
	 *
	 * @param mixed $t Can be a string defining the table to execute the query on or an array
	 *                 whose key is a subquery alias and the value is a Squibble object.
	 *
	 * @return object returns the current squibble object.
	 */
	public function table($t){
		if(is_array($t)){
			$tArray = $t;
			$t = $t_alias = array_shift(array_keys($tArray));
			$driver = get_called_class();
			if(($tArray[$t_alias] instanceof $driver) === true){
				$this->_placeholders = array_merge($this->_placeholders, $tArray[$t_alias]->queryData());
				$t = $tArray[$t_alias]->queryString();
				$t = "({$t}) AS {$this->formatWithQuote($t_alias, 'alias')}";
			}
			$t = (string) $t;
			$this->_table = trim($t);
		}else{
			$t = \Dorsataio\Squibble\Resource\Extract::fromTable((string) $t);
			$this->_table = trim($t['table']);
			if(isset($t['alias']) && !empty($t['alias'])){
				$this->_table = "{$this->_table} as {$t['alias']}";
			}
		}
		return $this;
	}

	/**
	 * Start a new SELECT query.
	 *
	 * @param array $c Array containing columns that the SELECT query will return.
	 *
	 * @return object returns the current squibble object.
	 */
	public function select($t, $c = array()){
		if(!is_array($c)){
			$c = array($c);
		}
		if(is_array($t)){
			$c = $t;
		}elseif(is_string($t)){
			$this->table($t);
		}
		$columns = [];
		foreach($c as $i => $s){
			$column = [];
			if(!is_array($s)){
				$column = \Dorsataio\Squibble\Resource\Extract::fromColumn($s);
			}else{
				$column = \Dorsataio\Squibble\Resource\Extract::fromColumn($i);
				$column['cases'] = \Dorsataio\Squibble\Resource\Extract::extractCase($s);
				
			}
			array_push($columns, $column);
		}
		$this->_sql = $this->getSelectStatement($columns);
		return $this;
	}

	/**
	 * Start a new INSERT query.
	 *
	 * @param array $nvp An NVP array containing columns their value to be inserted.
	 *
	 * @return object returns the current squibble object.
	 */
	public function insert($t, $nvp = array()){
		$nvps = $nvp;
		if(is_array($t)){
			$nvps = $t;
		}elseif(is_string($t)){
			$this->table($t);
		}
		if(!isset($nvps[0])){
			$nvps = array($nvps);
		}
		$columns = [];
		foreach($nvps as $i => $nvp){
			$columns[$i] = [];
			foreach(array_keys($nvp) as $s){
				$column = \Dorsataio\Squibble\Resource\Extract::fromColumn($s);
				$column['value'] = $nvp[$s];
				array_push($columns[$i], $column);
			}
		}
		$this->_sql = $this->getInsertStatement($columns);
		return $this;
	}

	/**
	 * Start a new UPDATE query.
	 *
	 * @param array $nvp An NVP array containing columns their value to be updated.
	 *
	 * @return object returns the current squibble object.
	 */
	public function update($t, $nvp = array()){
		if(is_array($t)){
			$nvp = $t;
		}elseif(is_string($t)){
			$this->table($t);
		}
		$columns = [];
		foreach(array_keys($nvp) as $s){
			$column = \Dorsataio\Squibble\Resource\Extract::fromColumn($s);
			$column['value'] = $nvp[$s];
			array_push($columns, $column);
		}
		$this->_sql = $this->getUpdateStatement($columns);
		return $this;
	}

	/**
	 * Start a new DELETE query.
	 *
	 * @param array $conditions An NVP array containing WHERE conditions.
	 *
	 * @return object returns the current squibble object.
	 */
	public function delete($t, $conditions = array()){
		if(is_array($t)){
			$conditions = $t;
		}elseif(is_string($t)){
			$this->table($t);
		}
		if(!empty($conditions)){
			$this->where($conditions);
		}
		$this->_sql = $this->getDeleteStatement();
		return $this;
	}

	/**
	 * [distinct description]
	 *
	 * @param array $c [description]
	 *
	 * @return [type] [description]
	 */
	public function distinct(array $c){
		$columns = [];
		foreach($c as $s){
			array_push($columns, \Dorsataio\Squibble\Resource\Extract::fromColumn($s));
		}
		$this->_distinctClause = $this->getDistinctStatement($columns);
		return $this;
	}

	public function joining(array $joining){
		$joins = [];
		foreach($joining as $table => $joinOnConditions){
			$joins[$table] = \Dorsataio\Squibble\Resource\Extract::fromTable($table);
			// Get the actual join type
			$joins[$table]['joinType'] = $this->stdJoinTypes($joins[$table]['joinType']);
			$joins[$table]['joinOn'] = [];
			foreach(array_keys($joinOnConditions) as $key){
				$condition = $joinOnConditions[$key];
				if(is_numeric($key) && is_string($condition)){
					$k = preg_replace('/' . $joins[$table]['table'] . '\./', '', $condition);
					$k = ltrim(rtrim($k, ']'), '[');
					$k = "{$this->_table}.{$k}";
					$k = "[{$k}]";
					$v = preg_replace('/' . $joins[$table]['table'] . '\./', '', $condition);
					$v = ltrim(rtrim($v, ']'), '[');
					$v = "{$joins[$table]['table']}.{$v}";
					$v = "[{$v}]";
					$joinOnConditions[$k] = $v;
					unset($joinOnConditions[$key]);
				}
			}
			foreach($joinOnConditions as $andOr => $condition){
				$joins[$table]['joinOn'] = array_merge($joins[$table]['joinOn'], \Dorsataio\Squibble\Resource\Extract::fromCondition($andOr, $condition));
			}
		}
		$this->_joinClause = $this->getJoinClause($joins);
		return $this;
	}

	public function where(array $conditions){
		// $this->_params['wheres'] = [];
		$wheres = [];
		foreach($conditions as $andOr => $condition){
			// $this->_params['wheres'] = array_merge($this->_params['wheres'], \Dorsataio\Squibble\Resource\Extract::fromCondition($andOr, $condition));
			$wheres = array_merge($wheres, \Dorsataio\Squibble\Resource\Extract::fromCondition($andOr, $condition));
		}
		$this->_whereClause = $this->getWhereClause($wheres);
		return $this;
	}

	public function group(array $grouping){
		$columns = [];
		foreach($grouping as $c){
			array_push($columns, \Dorsataio\Squibble\Resource\Extract::fromColumn($c));
		}
		$this->_groupByClause = $this->getGroupByClause($columns);
		return $this;
	}

	public function sort(array $sortBy){
		$sorts = [];
		foreach($sortBy as $column => $sortOrder){
			foreach(array_keys($sortBy) as $key){
				$sortOrder = $sortBy[$key];
				if(is_numeric($key) && is_string($sortOrder)){
					$k = $sortOrder;
					if(!preg_match('/\./', $k)){
						$k = "{$this->_table}.{$sortOrder}";
					}
					// Default to descending sort
					$sortBy[$k] = 'DESC';
					// Remove the original key
					unset($sortBy[$key]);
				}
			}
		}
		foreach($sortBy as $column => $sortOrder){
			$params = \Dorsataio\Squibble\Resource\Extract::fromColumn($column);
			$params['sort'] = $sortOrder;
			array_push($sorts, $params);
		}
		$this->_orderByClause = $this->getOrderByClause($sorts);
		return $this;
	}

	public function limitAndOffset($limit, $offset = 0){
		$limit = intval($limit);
		$offset = intval($offset);
		$this->_limitOffsetClause = $this->getLimitOffsetClause($limit, $offset);
		return $this;
	}

	public function returning($c){
		if(!is_array($c)){
			$c = array($c);
		}
		$columns = [];
		foreach($c as $s){
			array_push($columns, \Dorsataio\Squibble\Resource\Extract::fromColumn($s));
		}
		$this->_returningClause = $this->getReturningClause($columns);
		return $this;
	}

	public function execute(){
		$query = $this->prepare($this->queryString());
		$execute = $query->execute($this->queryData());
		if(!empty($this->_returningClause)){
			$execute = $query->fetchAll(\PDO::FETCH_ASSOC);
		}
		return $execute;
	}

	public function get($c = null){
		if(empty($c)){
			$c = '*';
		}
		$this->select($c);
		// Automatically add a limit of 1
		$this->limitAndOffset(1, 0);
		// Collect
		return $this->collect();
	}
	
	public function collect(){
		$query = $this->prepare($this->queryString());
		$query->execute($this->queryData());
		return $query->fetchAll(\PDO::FETCH_ASSOC);
	}

	public function prepare($sql = ''){
		if(!is_string($sql) || empty($sql)){
			$sql = $this->queryString();
		}
		return self::$_pdo->prepare($sql);
	}

	protected function valueToPlaceholder($value){
		$placeholder = false;
		if(!empty($this->_placeholders)){
			$key = array_search($value, $this->_placeholders, true);
			if($key){
				$placeholder = $key;
			}
		}
		if(empty($placeholder)){
			$placeholder = ":Squibble_" . time() . sizeof($this->_placeholders);
			if(is_array($value)){
				$value = $this->formatArrayValue($value);
			}
			$this->_placeholders[$placeholder] = $value;
		}
		return $placeholder;
	}

	protected function columnToString(array $params){
		$column = $params['column'];
		// add quotes
		if($column != '*'){
			$column = $this->formatWithQuote($column, 'column');
		}
		// parent.column
		if(isset($params['parent']) && $params['parent']){
			$column = "{$this->formatWithQuote($params['parent'], 'table')}.{$column}";
		}
		// parent.column::cast?
		if(isset($params['cast']) && $params['cast']){
			$column = "{$column}::{$params['cast']}";
		}
		// function(%%column) ?
		if(isset($params['function']) && $params['function']){
			$column = str_replace('%%column%%', $column, $params['function']);
		}
		// add alias?
		if(isset($params['alias']) && $params['alias']){
			$params['alias'] = $this->formatWithQuote($params['alias'], 'alias');
			$column = "{$column} AS {$params['alias']}";
		}
		return $column;
	}

	protected function conditionToString(array $condition){
		$conditionStatement = $this->columnToString($condition);
		// Handle NULLs with care!
		if($condition['value'] === null){
			if($condition['operator'] == '[!=]'){
				$condition['operator'] = '[!null]';
			}else{
				$condition['operator'] = '[null]';
			}
		}
		// Format value
		$placeholder = false;
		if($condition['value'] !== null){
			$value = $condition['value'];
			$placeholder = $value;
			// Value as an array needs to be specially handled
			if(is_array($value)){
				// Value is another column
				if(isset($value['column'])){
					// so format it as a column
					$placeholder = $this->columnToString($value);
				}else{
					$placeholders = [];
					// c BETWEEN x AND y
					// c NOT BETWEEN x AND y
					if(in_array($condition['operator'], ['[<>]', '[><]'])){
						foreach($value as $v){
							$placeholder = $this->valueToPlaceholder($v);
							array_push($placeholders, $placeholder);
							if(sizeof($placeholders) == 2){
								break;
							}
						}
						$placeholder = join(' AND ', $placeholders);
					}else{
						// c IN(x, y, z)
						// C NOT IN(x, y, z)
						foreach($value as $v){
							$placeholder = $this->valueToPlaceholder($v);
							array_push($placeholders, $placeholder);
						}
						$placeholder = '(' . join(', ', $placeholders) . ')';
					}
				}
			}else{
				$placeholder = $this->valueToPlaceholder($value);
			}
		}
		// Get the actual operator
		$condition['operator'] = $this->stdOperatortypes($condition['operator']);
		$conditionStatement .= " {$condition['operator']}";
		if($placeholder){
			$conditionStatement .= " {$placeholder}";
		}
		// Return the formatted condition string
		return $conditionStatement;
	}
}