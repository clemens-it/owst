<?php

$lock_fh = FALSE;

	function lockEx() {
		global $lock_fh, $cfg;
		$lock_fh = fopen($cfg['lock_file'], 'w+') or die("can not create or open lock file {$cfg['lock_file']}\n");
		flock($lock_fh, LOCK_EX) or die("can not lock lock file\n");
	}

	function unLockEx() {
		global $lock_fh;
		if ($lock_fh !== FALSE) {
			flock($lock_fh, LOCK_UN);
			fclose($lock_fh);
		}
	}
