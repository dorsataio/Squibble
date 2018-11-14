<?php
require_once('../../vendor/autoload.php');

// Your Connection settings
$connection = [
	'type' 		=> 'Pgsql',
	'host' 		=> 'your.sql.host',
	'port' 		=> 5432,
	'dbname' 	=> 'your.database.name',
	'user' 		=> 'your.username',
	'password' 	=> 'your.password',
];

// Create a new instance of Squibble with your connection settings
$squibble = new \Dorsataio\Squibble\Squibble($connection);

// This is a simple SELECT example
try{
	// We're selection various columns from the FRUITS table
	// and we're only interested in fruits that are harvested
	// starting the month of January.
	$s = $squibble->table('FRUITS')->select([
		'[NAME]',
		'[REGION]',
		'[DESCRIPTION]',
		'[HARVEST_MONTH_START]',
		'[HARVEST_MONTH_END]'
	])->where([
		'[HARVEST_MONTH_START][=]' => '01',
	]);
	// collect() return an array of the result sets. This is the
	// same as PDO $query->fetchAll(PDO::FETCH_ASSOC)
	$fruits = $s->collect();
	// var_export($fruits);
}catch(PDOException $e){
	echo "Error, {$e->getCode()}, {$e->getMessage()}";
}catch(Exception $e){
	echo "Error, {$e->getCode()}, {$e->getMessage()}";
}