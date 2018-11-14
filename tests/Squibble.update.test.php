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

// This is a simple UPDATE example
try{
	// Update an existing entry in the database table FRUITS.
	// Using returning(*) will return the data that was just
	// updated.
	// 
	// NOTE: Not all databases support returning(*), but PostgreSQL in particular
	// does support the feature.
	$s = $squibble->table('FRUITS')
			->update([
				'[REGION]' => 'Southeast Asia',
			])
			->where([
				'[NAME][=]' => 'Dragon Fruit'
			])
			->returning('*');
	// Execute the update query and return the updated data
	// into $fruit.
	$fruit = $s->execute();
}catch(PDOException $e){
	echo "Error, {$e->getCode()}, {$e->getMessage()}";
}catch(Exception $e){
	echo "Error, {$e->getCode()}, {$e->getMessage()}";
}