<?php
namespace Dorsataio\Squibble\Driver;

/**
 *
 * 
 */
class Pgsql extends \Dorsataio\Squibble\SquibbleDriver{

	protected static function getDsn($password){
		// Default to port 5432
		if(!isset(self::$_port) || empty(self::$_port)) self::$_port = 5432;
		// Return a Pgsql format DSN string
		return "pgsql:host=" . self::$_host . ";port=" . self::$_port . ";dbname=" . self::$_dbname . ";user=" . self::$_user . ";password={$password}";
	}

	protected function operatorTypes(){
		return [];
	}

	protected function joinTypes(){
		return [];
	}

	protected function formatWithQuote($s, $type = 'column'){
		$s = (string) $s;
		if(preg_match('/SELECT.*FROM/', $s)){
			$type = null;
		}
		switch(strtolower($type)){
			case 'column':
			case 'table':
			case 'alias':
				$s = str_replace('"', '', $s);
				$s = "\"{$s}\"";
				break;
			
			default:
				$s = $s;
				break;
		}
		return $s;
	}

	protected function formatArrayValue(array $value){
		// [A, B, C]
		$value = '{' . join(', ', array_values($value)) . '}';
		// {A, B, C}
		return $value;
	}

	protected function formatColumnAsCase(array $params){
		$column = 'CASE';
		$cast = (isset($params['cast']) ? $params['cast'] : null);
		unset($params['cast']);
		foreach($params['cases'] as $then => $conditions){
			if(!is_numeric($then) && $then !== 'null' && $then !== 'NULL'){
				$then = "'{$then}'";
			}
			if($conditions !== 'END'){
				$test = $this->getWhereClause($conditions);
				$column .= " WHEN {$test} THEN {$then}" . ($cast ? "::{$cast}" : "");
				continue;
			}
			$column .= " ELSE {$then}" . ($cast ? "::{$cast}" : "");
		}
		$column .= " END AS {$this->columnToString($params)}";
		// 
		return $column;
	}

	protected function getSqlStatement(){
		$sql = $this->_sql;
		if(!empty($this->_distinctClause)){
			$sql = preg_replace('/(^SELECT)/', '\1 ' . $this->_distinctClause, $sql);
		}
		if(!empty($this->_joinClause)){
			$sql .= " {$this->_joinClause}";
		}
		if(!empty($this->_whereClause)){
			$sql .= " WHERE {$this->_whereClause}";
		}
		if(!empty($this->_groupByClause)){
			$sql .= " GROUP BY {$this->_groupByClause}";
		}
		if(!empty($this->_orderByClause)){
			$sql .= " ORDER BY {$this->_orderByClause}";
		}
		if(!empty($this->_limitOffsetClause)){
			$sql .= " {$this->_limitOffsetClause}";
		}
		if(!empty($this->_returningClause)){
			$sql .= " {$this->_returningClause}";
		}
		return $sql;
	}

	protected function getSelectStatement(array $columns){
		$statement = "SELECT ";
		foreach($columns as $i => $params){
			$column = '';
			if(isset($params['cases'])){
				$column = $this->formatColumnAsCase($params);
			}else{
				$column = $this->columnToString($params);
			}
			if($i > 0){
				$statement = "{$statement}, ";
			}
			$statement = "{$statement}{$column}";
		}
		$statement .= " FROM {$this->formatWithQuote($this->_table, 'table')}";
		return $statement;
	}

	protected function getInsertStatement(array $columnSets){
		$columnsAsStr = '';
		$valuesAsStr = '';
		foreach($columnSets as $i => $columns){
			$placeholders = [];
			foreach($columns as $j => $params){
				if($i === 0){
					$column = $this->columnToString($params);
					if($j > 0){
						$columnsAsStr .= ", ";
					}
					$columnsAsStr .= "{$column}";
				}
				$placeholder = $this->valueToPlaceholder($params['value']);
				array_push($placeholders, $placeholder);
			}
			if($i > 0){
				$valuesAsStr .= ", ";
			}
			$valuesAsStr .= "(" . join(', ', $placeholders) . ")";
		}
		// Putting it all together
		$statement = "INSERT INTO {$this->formatWithQuote($this->_table, 'table')} ({$columnsAsStr}) VALUES {$valuesAsStr}";
		// Complete statement
		return $statement;
	}

	protected function getUpdateStatement(array $columns){
		$statement = "UPDATE {$this->formatWithQuote($this->_table, 'table')} SET ";
		foreach($columns as $i => $params){
			$column = $this->columnToString($params);
			if($i > 0){
				$statement .= ", ";
			}
			$placeholder = $this->valueToPlaceholder($params['value']);
			$statement .= "{$column} = {$placeholder}";
		}
		return $statement;
	}

	protected function getDeleteStatement(){
		$statement = "DELETE FROM {$this->formatWithQuote($this->_table, 'table')}";
		return $statement;
	}

	protected function getDistinctStatement(array $columns){
		$statement = "DISTINCT ON(";
		foreach($columns as $i => $params){
			$column = $this->columnToString($params);
			if($i > 0){
				$statement = "{$statement}, ";
			}
			$statement = "{$statement}{$column}";
		}
		$statement .= ")";
		return $statement;
	}

	protected function getJoinClause(array $joins){
		$clause = '';
		foreach($joins as $joining){
			$statement = "{$joining['joinType']} {$this->formatWithQuote($joining['table'], 'table')}";
			if(isset($joining['alias']) && !empty($joining['alias'])){
				$statement .= " AS {$this->formatWithQuote($joining['alias'], 'alias')}";
			}
			$statement .= " ON(";
			$statement .= $this->getWhereClause($joining['joinOn']);
			$statement .= ")";
			if(!empty($clause)){
				$clause .= " ";
			}
			$clause .= "{$statement}";
		}
		return $clause;
	}

	protected function getWhereClause(array $conditions, $r_andOr = 'AND'){
		$clause = "";
		foreach($conditions as $key => $condition){
			$statement = '';
			if(!preg_match('/^AND|OR/', $key)){
				$conditionStatement = $this->conditionToString($condition);
				if(!empty($clause)){
					$statement .= " {$r_andOr} ";
				}
				$statement .= "{$conditionStatement}";
			}else{
				preg_match_all('/^(AND|OR)/', $key, $matches);
				$andOr = (!empty($matches[1][0]) ? $matches[1][0] : 'AND');
				if(!empty($clause)){
					$statement .= " {$r_andOr} ";
				}
				$statement .= "({$this->getWhereClause($condition, $andOr)})";
			}
			$clause .= "{$statement}";
		}
		return $clause;
	}

	protected function getGroupByClause(array $columns){
		$clause = "";
		foreach($columns as $i => $params){
			$column = $this->columnToString($params);
			if($i > 0){
				$clause .= ", ";
			}
			$clause .= "{$column}";
		}
		return $clause;
	}

	protected function getOrderByClause(array $sorts){
		$clause = "";
		foreach($sorts as $orderBy){
			if(isset($orderBy['sort']) && !empty($orderBy['sort'])){
				$column = $this->columnToString($orderBy);
				if(!empty($clause)){
					$clause .= ", ";
				}
				$clause .= "{$column} {$orderBy['sort']}";
			}
		}
		return $clause;
	}

	protected function getLimitOffsetClause($limit, $offset = 0){
		$limit = intval($limit);
		$offset = intval($offset);
		$clause = "";
		if($limit > 0){
			$clause .= "LIMIT {$limit}";
			if($offset > 0){
				$clause .= ", {$offset}";
			}
		}elseif($offset > 0){
			$clause = "OFFSET {$offset}";
		}
		return $clause;
	}

	protected function getReturningClause(array $columns){
		$clause = "";
		foreach($columns as $i => $params){
			$column = $this->columnToString($params);
			if($i > 0){
				$clause .= ", ";
			}
			$clause .= "{$column}";
		}
		return "RETURNING {$clause}";
	}

	public function count(array $conditions = array()){
		if(!empty($conditions)){
			$this->where($conditions);
		}
		$driver = get_called_class();
		$squibble = new $driver(self::$_pdo);
		$count = $squibble->table(['c' => $this])->get('COUNT([c.*])~~total');
		return (isset($count[0]['total']) ? $count[0]['total'] : 0);
	}

	public function sum($c, array $conditions = array()){
		if(!empty($conditions)){
			$this->where($conditions);
		}
		$total = 0;
		if(is_string($c)){
			$c = "SUM({$c})~~total";
			$this->select($c);
			$sum = $this->collect();
			$total = (isset($sum[0]['total']) ? $sum[0]['total'] : 0);
		}
		return $total;
	}

	public function max($c, array $conditions = array()){
		if(!empty($conditions)){
			$this->where($conditions);
		}
		$total = 0;
		if(is_string($c)){
			$c = "MAX({$c})~~total";
			$this->select($c);
			$max = $this->collect();
			$total = (isset($max[0]['total']) ? $max[0]['total'] : 0);
		}
		return $total;
	}

	public function min($c, array $conditions = array()){
		if(!empty($conditions)){
			$this->where($conditions);
		}
		$total = 0;
		if(is_string($c)){
			$c = "MIN({$c})~~total";
			$this->select($c);
			$min = $this->collect();
			$total = (isset($min[0]['total']) ? $min[0]['total'] : 0);
		}
		return $total;
	}

	public function avg($c, array $conditions = array()){
		if(!empty($conditions)){
			$this->where($conditions);
		}
		$total = 0;
		if(is_string($c)){
			$c = "AVG({$c})~~total";
			$this->select($c);
			$avg = $this->collect();
			$total = (isset($avg[0]['total']) ? $avg[0]['total'] : 0);
		}
		return $total;
	}
}
