<?php
namespace Dorsataio\Squibble;

/**
 * 
 */
class Squibble{

	private $_dbh = null;
	private static $_driver = null;
	private $_pdo = null;
	private $_type = null;
	private $_exception = null;

	// pgsql:host=localhost;port=5432;dbname=testdb;user=bruce;password=mypass
	public function __construct(array $conf = array()){
		$this->_type = $conf['type'] ? (string) $conf['type'] : 'Pgsql';
		self::$_driver = "\\Dorsataio\\Squibble\\Driver\\{$this->_type}";
		if(!class_exists(self::$_driver)){
			throw new \Exception("Invalid Squibble.type '{$this->_type}'.", 500);
		}
		// Set our params
		$params = [
			'host' => $conf['host'] ? (string) $conf['host'] : 'localhost',
			'port' => $conf['port'] ? (string) $conf['port'] : null,
			'dbname' => $conf['dbname'] ? (string) $conf['dbname'] : 'No.dbname.supplied',
			'user' => $conf['user'] ? (string) $conf['user'] : 'No.user.supplied',
			'password' => $conf['password'] ? (string) $conf['password'] : 'No.password.supplied',
		];
		// Attempt a connection
		$driver = self::$_driver;
		$this->_pdo = $driver::connect($params['host'], $params['port'], $params['dbname'], $params['user'], $params['password']);
		// Did we get a PDO object? Could just be an exception...
		if(($this->_pdo instanceof \PDO) === false){
			$e = $this->_pdo;
			$this->_pdo = false;
			throw new \Exception("Unable to establish a database connection: {$e->getMessage()}", $e->getCode());
		}
	}

	public function __toString(){
		return (string) $this->_dbh;
	}

	public function __call($method, array $arguments){
		$method = (string) $method;
		if(!$this->_dbh || $method === 'table'){
			$this->_dbh = new self::$_driver($this->_pdo);
		}
		return call_user_func_array(array($this->_dbh, $method), $arguments);
	}

	public static function __callStatic($method, $arguments){
		$method = (string) $method;
		return call_user_func_array(array(self::$_driver, $method), $arguments);
	}
}