<?php
	error_reporting(E_ALL);
	ini_set('display_errors', 1);

	// Ning -> Pleio converter
	// Load Ning export into /import folder and run script
		
	// require and instantiate classes
    require_once "src/PleioImport.php";
	$importer = new PleioImport("target","import");
	
	// call to convert function
	try { 
		$importer->convert();
	} catch(Exception $e) {
	    echo "Oops! Something went wrong!" . PHP_EOL . $e->getMessage();
	}
?>