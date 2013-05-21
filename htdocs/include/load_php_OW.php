<?php

$extensionFile = $cfg['php_lib_path'].'/libowphp.so';

//make sure that we are ABLE to load libraries
if ( !(bool)ini_get("enable_dl") || (bool)ini_get("safe_mode") ) {
  die( "dl_local(): Loading extensions is not permitted.\n" );
}


//check to make sure the file exists
if ( !file_exists($extensionFile) ) {
  die( "dl_local(): File '$extensionFile' does not exist.\n" );
}

global $OWPHP_LOADED__;
if ($OWPHP_LOADED__)
	return;
$OWPHP_LOADED__ = true;

/* if our extension has not been loaded, do what we can */
if (!extension_loaded("libowphp")) {
	if (!dl("libowphp.so"))
		die( "dl(): File '$extensionFile' could not be loaded.\n" );
}

?>
