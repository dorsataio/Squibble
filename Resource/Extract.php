<?php
namespace Dorsataio\Squibble\Resource;

/**
 * 
 */
class Extract{

	public static function fromColumn($s){
		$s = (string) $s;
		$extract = [
			'parent' => self::extractParent($s),
			'column' => self::extractColumn($s),
			'cast' => self::extractCast($s),
			'alias' => self::extractAlias($s),
			'function' => self::extractFunctions($s),
			'operator' => self::extractOperator($s),
		];
		$extract = array_diff($extract, [false]);
		return $extract;
	}

	public static function fromTable($t){
		$t = (string) $t;
		$extract = [
			'table' => self::extractTable($t),
			'joinType' => self::extractJoinType($t),
			'alias' => self::extractAlias($t),
		];
		$extract = array_diff($extract, [false]);
		return $extract;
	}

	public static function fromCondition($andOr, $condition){
		$andOr = (string) $andOr;
		$conditions = [];
		$leftOperand = null;
		$rightOperand = null;
		// Must be a column
		if(!preg_match('/^(?:AND|OR)/', $andOr)){
			$leftOperand = $andOr;
			$rightOperand = $condition;
			$condition = self::fromColumn($leftOperand);
			$condition['value'] = $rightOperand;
			if(is_string($condition['value']) && preg_match('/\[[a-zA-Z0-9_\-]+\.[a-zA-Z0-9_\-]+\]/', $condition['value'])){
				$condition['value'] = self::fromColumn($condition['value']);
				if(!isset($condition['operator'])){
					$condition['operator'] = '[=]';
				}
			}
			if(!isset($condition['operator'])){
				if(is_array($condition['value'])){
					$condition['operator'] = '[in]';
				}else{
					$condition['operator'] = '[=]';
				}
			}
			$conditions[$andOr] = $condition;
		}else{
			$conditions[$andOr] = [];
			foreach($condition as $r_andOr => $r_condition){
				$conditions[$andOr] = array_merge($conditions[$andOr], self::fromCondition($r_andOr, $r_condition));
			}
		}
		return $conditions;
	}

	public static function extractParent($s){
		$s = (string) $s;
		// parent.column OR [parent.column]
		preg_match_all('/((?:\[[a-zA-Z0-9_\-]+\.[a-zA-Z0-9_\-\*]+\])|(?:^[a-zA-Z0-9_\-]+\.[a-zA-Z0-9_\-\*]+))/', $s, $matches);
		if(isset($matches[1][0]) && !empty($matches[1][0])){
			$p = explode('.', $matches[1][0]);
			$p = array_shift($p);
			$p = ltrim($p, '[');
			// return "parent"
			return $p;
		}
		return false;
	}

	public static function extractColumn($s){
		$s = (string) $s;
		$plainOlColumn = !(boolean)(self::extractParent($s) || self::extractFunctions($s) || self::extractCast($s) || self::extractAlias($s) || self::extractOperator($s));
		$matches = [];
		if($plainOlColumn !== true){
			// [parent.column] OR [column]
			preg_match_all('/(?:(?:[a-zA-z0-9_\-]+\.)|\[)?([a-zA-z0-9_\-\*]+)(?:(?=\])|\[[^a-zA-Z0-9_\-\.\*]+\]|$)/', $s, $matches);
		}
		// return "column"
		return (!empty($matches[1][0]) ? rtrim($matches[1][0], ']') : str_replace(array('[', ']'), array('', ''), $s));
	}

	public static function extractCase(array $case){
		$temp = $case;
		$case = [];
		foreach($temp as $v => $conditions){
			$case[$v] = [];
			if($conditions === 'END'){
				$case[$v] = $conditions;
				continue;
			}
			foreach($conditions as $andOr => $condition){
				$condition = self::fromCondition($andOr, $condition);
				$case[$v] = array_merge($case[$v], $condition);
			}
		}
		return $case;
	}

	public static function extractFunctions($s){
		$s = (string) $s;
		// callable([column]::cast)
		// if(preg_match('/\w+\(/', $s) && preg_match('/((?:(?:\[[a-zA-Z0-9_\-]+\.)?[a-zA-Z0-9_\-]+\])|(?:(?:^[a-zA-Z0-9_\-]+\.)?[a-zA-Z0-9_\-]+)(?:::[a-zA-Z\s]+)?)/', $s)){
		if(preg_match('/\w+\(/', $s) && preg_match('/((?:\[(?:[a-zA-Z0-9_\-]+\.)?[a-zA-Z0-9_\-\*]+\]))/', $s)){
			// if contains ~~alias
			$alias = self::extractAlias($s);
			if($alias !== false){
				// then remove it
				$s = str_replace("~~{$alias}", '', $s);
			}
			// If contains operator [*]
			$operator = self::extractOperator($s);
			if($operator !== false){
				// then remove it
				$s = str_replace($operator, '', $s);
			}
			// If contains [column]::cast
			$cast = self::extractCast($s);
			if($cast !== false){
				// then remove it
				$s = str_replace("]::{$cast}", "]", $s);
			}
			// callable(%%column%%)
			// $s = preg_replace('/((?:(?:\[[a-zA-Z0-9_\-]+\.)?[a-zA-Z0-9_\-]+\])|(?:(?:^[a-zA-Z0-9_\-]+\.)?[a-zA-Z0-9_\-]+)(?:::[a-zA-Z\s]+)?)/', '%%column%%', $s);
			$s = preg_replace('/((?:\[(?:[a-zA-Z0-9_\-]+\.)?[a-zA-Z0-9_\-\*]+\]))/', '%%column%%', $s);
			// Fix scenarios where we have a type cast with precission such as "character varying(10)"
			// where the (10) gets interpret as a function due to the parenthesis.
			if($s === '%%column%%'){
				$s = false;
			}
			// return callable(%%column%%)
			return $s;
		}
		return false;
	}

	public static function extractAlias($s){
		$s = (string) $s;
		// ~~alias
		preg_match_all('/~~([a-zA-Z0-9_\-]+)(?:\[.*?\]$|$)/', $s, $matches);
		// return "alias"
		return (!empty($matches[1][0]) ? $matches[1][0] : false);
	}

	public static function extractCast($s){
		$s = (string) $s;
		// [column]::cast
		preg_match_all('/\[.*?\]::([a-zA-Z\s]+(?:\(.*\))?)/', $s, $matches);
		// return "cast"
		return (!empty($matches[1][0]) ? $matches[1][0] : false);
	}

	public static function extractOperator($s){
		$s = (string) $s;
		// column[=]
		preg_match_all('/(\[[^a-zA-Z0-9_\-\.]+\])$/', $s, $matches);
		// return "[=]"
		return (!empty($matches[1][0]) ? $matches[1][0] : false);
	}

	public static function extractTable($t){
		$t = (string) $t;
		// if contains ~~alias
		$alias = self::extractAlias($t);
		if($alias !== false){
			// then remove it
			$t = str_replace("~~{$alias}", '', $t);
		}
		$joinType = self::extractJoinType($t);
		if($joinType === false){
			return $t;
		}
		return str_replace($joinType, '', $t);
	}

	public static function extractJoinType($t){
		$t = (string) $t;
		// [>]table
		preg_match_all('/(^\[.*\])/', $t, $matches);
		// return "[>]"
		return (!empty($matches[1][0]) ? $matches[1][0] : false);
	}
}
