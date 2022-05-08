<?php

define('LLERROR', 0);
define('LLWARN', 1);
define('LLINFO_OWACTION', 2);
define('LLINFO_ACTION', 3);
define('LLINFO', 4);
define('LLDEBUG', 5);

$log_levels = array(LLERROR => 'Error', LLWARN => 'Warn', LLINFO_OWACTION => 'OW Action Info', LLINFO_ACTION => 'Action Info',
	LLINFO => 'All Info', LLDEBUG => 'Debug');

//set default
$log_level = LLWARN;

//init log
if ($cfg['use_syslog'])
	openlog($cfg['log_file'], LOG_ODELAY, LOG_LOCAL0);
elseif ($cfg['log_file'] != '')
	$log_fh = fopen($cfg['log_file'], 'at');


function logEvent($msg, $msg_log_level=LLWARN) {
	global $cfg, $log_fh, $log_level;
	//check if logging is turned on at all
	if (empty($cfg['log_file']))
		return;

	//check whether current log_level wants this particular Event to be logged
	if ($msg_log_level > $log_level)
		return;

	//send msg either to syslog or to file
	if ($cfg['use_syslog'])
		syslog(LOG_INFO, $msg);
	elseif (isset($log_fh)) {
		$msg = date('Y-m-d H:i:s') . " [".getmypid()."] {$msg_log_level} - {$msg}\n";
		flock($log_fh, LOCK_EX);
		fseek($log_fh, SEEK_END);
		fwrite($log_fh, $msg);
		fflush($log_fh);
		flock($log_fh, LOCK_UN);
	}
} //function logEvent
