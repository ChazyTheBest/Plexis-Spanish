<?php	
/* 
| --------------------------------------------------------------
| 
| Plexis CMS
|
| --------------------------------------------------------------
|
| Author:       Steven (Wilson212)
| Author:       Tony (Syke)
| Copyright:    Copyright (c) 2011-2012, Plexis Dev Team
| License:      GNU GPL v3
|
*/

// Define CMS versions
define('CMS_VERSION', 'Alpha 5');
<<<<<<< HEAD
define('CMS_BUILD', 170);
=======
define('CMS_BUILD', 172);
>>>>>>> d808b5d161c3619ad12405ba3a9ab359676e794e
define('REQ_DB_VERSION', '0.15');

// Define a smaller Directory seperater and ROOT path
define('DS', DIRECTORY_SEPARATOR);
define('ROOT', dirname(__FILE__));

// Define full paths to the APP and System Folders
define('APP_PATH', ROOT . DS . 'application');
define('SYSTEM_PATH', ROOT . DS . 'system');

// Include required scripts to run the system
require (SYSTEM_PATH . DS . 'core' . DS . 'Common.php');
require (SYSTEM_PATH . DS . 'core' . DS . 'Registry.php');

// Initiate the system start time
<<<<<<< HEAD
$Benchmark = load_class('Benchmark');
$Benchmark->start('system');

// Initiate the framework and let it do the rest ;)
$Frostbite = load_class('Frostbite');
$Frostbite->Init();
=======
load_class('Benchmark')->start('system');

// Initiate the framework and let it do the rest ;)
load_class('Plexis')->Init();
>>>>>>> d808b5d161c3619ad12405ba3a9ab359676e794e
?>