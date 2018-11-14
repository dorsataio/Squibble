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

// This is a simple DELETE example
try{
	// Delete an existing entry in the database table FRUITS.
	// We're deleting any fruit entry with an ID greater than or
	// equal to 10.
	$s = $squibble->table('FRUITS')
		->delete([
			'[ID][>=]' => 10
		]);
	// Execute the delete query.
	$result = $s->execute();
}catch(PDOException $e){
	echo "Error, {$e->getCode()}, {$e->getMessage()}";
}catch(Exception $e){
	echo "Error, {$e->getCode()}, {$e->getMessage()}";
}