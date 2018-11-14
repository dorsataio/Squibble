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

// This is a simple INSERT example
try{
	// Insert a new entry into the database table FRUITS.
	// Using returning(*) will return the data that was just
	// inserted.
	// 
	// NOTE: Not all databases support returning(*), but PostgreSQL in particular
	// does support the feature.
	$s = $squibble->table('FRUITS')
			->insert([
				'[NAME]' => 'Dragon Fruit',
				'[REGION]' => 'Southeast Asia',
				'[DESCRIPTION]' => 'Dragon fruit (Hylocereus undatus) is a tropical fruit that belongs to the climbing cacti (Cactaceae) family. Widely cultivated in Vietnam, the fruit is popular in Southeast Asia and is. Apart from being refreshing and tasty, the dragon fruit is a rich source of vitamin C, calcium and phosphorus, and is known to aid digestion.',
				'[HARVEST_MONTH_START]' => '06',
				'[HARVEST_MONTH_END]' => '11'
			])
			->returning('*');
	// Execute the insert query and return the inserted data
	// into $fruit.
	$fruit = $s->execute();
}catch(PDOException $e){
	echo "Error, {$e->getCode()}, {$e->getMessage()}";
}catch(Exception $e){
	echo "Error, {$e->getCode()}, {$e->getMessage()}";
}