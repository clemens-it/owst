<?php
	if ($_REQUEST['subaction'] == 'show') {
		$filter_loglevel = LLINFO;
		if (isset($_REQUEST['filter_loglevel'])) 
			$filter_loglevel = intval($_REQUEST['filter_loglevel']);
		//override if invalid value
		if ($filter_loglevel < LLERROR || $filter_loglevel > LLDEBUG)
			$filter_loglevel = LLINFO;

		if (!$cfg['use_syslog']) {
			$fh = fopen($cfg['log_file'], 'r');
			if ($fh === FALSE) 
				$smarty->assign('errormsg', "Can't open log file");
			else {

				flock($fh, LOCK_SH);
				$fs = filesize($cfg['log_file']);
				if ($fs > $cfg['showlog_maxsize']) {
					//seek to read the more current lines, and read to next newline using fgets
					fseek($fh, $fs - $cfg['showlog_maxsize']);
					fgets($fh);
				}
				$log = fread($fh, $fs);
				flock($fh, LOCK_UN);
				$log = array_reverse(explode("\n", $log));

				//filter
				foreach($log as $k => $v) {
					$pos = strpos($v, '] ');
					$ll = trim(substr($v, $pos+2, 2));
					if ($ll > $filter_loglevel)
						unset($log[$k]);
				}

			$smarty->assign('logdata', $log);
			}

			//loglevels defined in include/log.php
			$smarty->assign('log_levels', $log_levels);
			$smarty->assign('filter_loglevel', $filter_loglevel);
			$smarty->error_reporting = E_ALL & ~E_NOTICE;
			$smarty_view = 'log.tpl';

		}
	
	}
