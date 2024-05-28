#!/usr/bin/php
<?php
/*
You will be needing the following deb packages: owserver libow-php5 php5-cli sqlite3 php5-sqlite at
Optional: ow-shell owfs owhttp
*/

if (!isset($argc)) {
    die("not running from the command line\n");
}

chdir(__DIR__);

//define variable with current filename to pass to config.php
$cfg_executable = __FILE__;
require_once 'include/config.php';
require_once 'include/log.php';
require_once 'include/functions.php';
//require_once 'include/load_php_OW.php'; //loads OW extension and/or checks if its loaded

//log_levels 0.. none, 1.. some, 5.. all. error, warn, info, debug
$log_level = LLDEBUG;
//$log_level = LLINFO;

logEvent("program " . __FILE__ . " started. Cmd line: " . implode(" ", $argv), LLINFO);

//handle command line options
$shopt = 'mrh';
$longopt = [
    'modechange', 'reprogram-all', 'help'
];
$opt = getopt($shopt, $longopt);

if (isset($opt['h']) || isset($opt['help'])) {
    displayHelp();
    exit(0);
}
$copt = array();
if (isset($opt['r']) || isset($opt['reprogram-all'])) {
    $copt['reprogram_at'] = 1;
}
if (isset($opt['m']) || isset($opt['modechange'])) {
    $copt['modechange'] = 1;
}

//one wire: init
if (!init($cfg['ow_adapter'])) {
    logEvent("cannot initialize 1-wire bus", LLWARN);
    print("cannot initialize 1-wire bus.\n");
    exit(9);
}

try {
    //get connection to database and call routine for owSwitchTimerControl
    $dbh = new PDO($cfg['dsn'], $cfg['dbuser'], $cfg['dbpass']);
    owSwitchTimerControl($dbh, $copt);
} catch (PDOException $e) {
    logEvent('Connection to database failed: ' . $e->getMessage(), LLWARN);
} catch (Exception $e) {
    logEvent("Error: {$e->getMessage()}", LLWARN);
}

logEvent("program " . __FILE__ . " finished", LLINFO);
