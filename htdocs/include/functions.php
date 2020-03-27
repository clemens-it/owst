<?php
	require_once 'at.php'; //needed by reprogramAt
	require_once 'log.php';
	require_once 'lock.php';

	function checkOWType($addr, $type, $info) {
		$ow_type = get($addr.'type');

		if ($ow_type == null) {
			logEvent("OW device $addr is not present, or {$addr}type does not exist. ".
				"Program id: {$info['tpid']}, switch id: {$info['sid']}", LLWARN);
			return FALSE;
		}

		if ($ow_type != $type) {
			logEvent("unknown type $ow_type for program id: {$info['tpid']}, switch id: {$info['sid']}", LLWARN);
			return FALSE;
		}

		return TRUE;

	} //checkOWType



	function displayHelp() {
		global $argv;
		print "Usage: ".basename($argv[0])." [OPTION] \n";
		print "\n";
		print "\tOPTIONS:\n";
		print "\t-m, --modechange     Call after the mode (on/off/timer) of a switch has been changed in database\n";
		print "\t-r, --reprogram-all  Reprogram all AT jobs, usually all jobs should be deleted before by 'atq | cut -f1 | xargs -r atrm'\n";
		print "\t-h, --help           This help text\n";
		print "\n";
	} //displayHelp



	function owSwitchTimerControl($dbh, $opt = array()) {
		//lock exclusively - only one instance of this program at a time
		lockEx();

		//init vars
		$new_ow_state = array();					//new status for ow-device
		$time_programs_to_delete = array();		//list of time programs to delete from table
		$switch_mode = array();						//mode of switch (on/off/timer)

		if (isset($opt['modechange'])) {
			logEvent('modechange requested. Calling modeChangeTimer', LLDEBUG);
			modeChangeTimer($dbh, $new_ow_state, $switch_mode);
		}

		handleTimePrograms($dbh, $new_ow_state, $switch_mode, $time_programs_to_delete, isset($opt['reprogram_at']));

		if (isset($opt['reprogram_at'])) {
			logEvent('reprogram-at requested. Calling reprogramAt', LLDEBUG);
			reprogramAt($dbh);
		}

		if (isset($opt['modechange'])) {
			logEvent('modechange requested. Calling modeChangeOnOff', LLDEBUG);
			modeChangeOnOff($dbh, $new_ow_state, $switch_mode);
		}

		//take action on 1wire
		setOWSwitch($switch_mode, $new_ow_state);

		//delete Expired Time programs
		deleteExpiredTimePrograms($dbh, $time_programs_to_delete);

		//remove exclusive lock
		unLockEx();
	} //owSwitchTimerControl



	function modeChangeTimer($dbh, &$new_ow_state, &$switch_mode) {
		//in case a switch is in mode 'timer' there could be the following scenario: its mode was
		//previously 'on' or 'off'. When switching back to 'timer' we need to decide how to set the OW
		//state depending on whether there are any time programs with active=1 or active=0 respectively.
		//This functions sets a corresponding state into the new_ow_state-array, which can be overridden
		//in case a corresponding time program is about to change at the same call.
		//This part of modechange (s.mode = timer) must be processed before cycling through the time programs.
		//It checks all the switches which are in mode 'timer' and determines and sets a new state according to
		//the time programs

		logEvent('ModeChangeTimer Checking for active time_programs', LLDEBUG);

		//query explanation: "LEFT OUTER JOIN" to get all the switches. "now BETWEEN valid_from AND valid_until" to get only valid time program
		// this however makes the LEFT OUTER JOIN ineffective therefore the "OR tp.id IS NULL". In case a switch has no time programs at all, if will
		// be shown, in case a switch has only invalid time programs (i.e. all laying in the past and/or future) it will not be shown yet. Therefore
		// the effort with the subquery and reapplied left outer join
		$sql = "
			SELECT s2.*, sq.sumactive
			FROM switch s2 LEFT OUTER JOIN (
				SELECT s.id, s.ow_address, s.ow_pio, SUM(tp.active) AS sumactive
				FROM switch s LEFT OUTER JOIN time_program tp ON tp.switch_id = s.id
				WHERE s.mode = 'timer'
					AND (strftime('%Y-%m-%d', 'now', 'localtime') BETWEEN tp.valid_from AND tp.valid_until OR tp.id IS NULL)
				GROUP BY s.id
			) AS sq ON s2.id = sq.id
		";

		$sth = $dbh->query($sql);
		if ($sth === FALSE) {
			logEvent("Query failed in ".__FILE__." before line ".__LINE__."! Error description: " . implode('; ', $dbh->errorInfo()) ."; Terminating.",
				LLERROR);
			die("query failed in ".__FILE__." before line ".__LINE__);
		}
		while ($result = $sth->fetch(PDO::FETCH_ASSOC)) {
			$ow_path = "/{$result['ow_address']}/{$result['ow_pio']}";

			//check type
			if (!checkOWType("/{$result['ow_address']}/", $result['ow_type'], array('sid'=>$result['id'], 'tpid'=>'NA')))
				continue;

			//values for sumactive may be: NULL, 0, any number > 0. The first two will be converted to 0, the last to 1
			$new_ow_state[$ow_path] = ($result['sumactive'] >= 1 ? 1 : 0);
			$switch_mode[$ow_path] = $result['mode'];
			logEvent("Modechange: Switch $ow_path in timer mode. Time programs suggest new state: {$new_ow_state[$ow_path]}", LLINFO);
		}
		$sth->closeCursor();
		$sth = null;
	} //modeChangeTimer



	function modeChangeOnOff($dbh, &$new_ow_state, &$switch_mode) {
		//Saves the mode for all switches in non-timer mode and create also a corresponding entry in array $new_ow_state
		// as the next code block below will cycle through $new_ow_state
		//All the due time programs need to be cycled through anyway, because they need to have their AT jobs reprogrammed.
		//This part of modechange needs to be after cycling through the due time programs, in order to be able to overwrite
		//$new_ow_state

		logEvent('modeChangeOnOff Checking for switches in mode ON or OFF', LLDEBUG);
		$sql = "SELECT * FROM switch WHERE mode <> 'timer'";
		$sth = $dbh->query($sql);
		if ($sth === FALSE) {
			logEvent("Query failed in ".__FILE__." before line ".__LINE__."! Error description: " . implode('; ', $dbh->errorInfo()) ."; Terminating.",
				LLERROR);
			die("query failed in ".__FILE__." before line ".__LINE__);
		}

		while ($result = $sth->fetch(PDO::FETCH_ASSOC)) {
			$ow_path = "/{$result['ow_address']}/{$result['ow_pio']}";

			//check type
			if (!checkOWType("/{$result['ow_address']}/", $result['ow_type'], array('sid'=>$result['id'], 'tpid'=>'NA')))
				continue;

			//save mode of switch in array - overriding any possible previous values
			$switch_mode[$ow_path] = $result['mode'];
			//with switch_mode limited to 'on' or 'off' due to the sql query any value will do for new_ow_state
			// - the next code block below reevaluates $switch_mode
			$new_ow_state[$ow_path] = -1;
		} //while switch modes

		$sth->closeCursor();
		$sth = null;

	} //modeChangeOnOff



	function emptyAtQueue() {
		//empties the at queue - to be called before using the function reprogramAt
		global $cfg;

		if (!isset($cfg['atempty_cmd_line']) || empty($cfg['atempty_cmd_line'])) {
			logEvent("EmptyAtQueue: corresponding command is empty!", LLERROR);
			return FALSE;
		}
		$output = array(); //contains array with all output
		unset ($retval); //return value
		$lloutput = exec($cfg['atempty_cmd_line'], $output, $retval);
		if ($retval == 0)
			logEvent("Emptying of at queue. Return value: ".$retval, LLINFO_ACTION);
		else {
			logEvent("Emptying of at queue failed. Return value: $retval. Output: ".implode("--", $output), LLERROR);
		}
		return ($retval == 0);
	} //emptyAtQueue



	function reprogramAt($dbh, $limit_to_tpid="") {
		//Reprogram AT for a single time program. If $limit_to_tpid is empty get all time programs which are still valid and
		//all time programs which will become valid in the future.
		//Goes through next 7 days and checks whether one of those match the criteria of the time program
		//For future time programs cycle through 7 days from the starting date

		global $cfg;
		$at_retval = TRUE;

		logEvent('Reprogramming at for '.(intval($limit_to_tpid)>0 ? 'single':'all').' valid time programs', LLDEBUG);
		$sql = "SELECT tp.id AS tpid, s.id AS sid, *
			FROM time_program tp INNER JOIN switch s ON tp.switch_id = s.id
			WHERE strftime('%Y-%m-%d', 'now', 'localtime') <= tp.valid_until ";
		//limit to a single time program
		if (intval($limit_to_tpid)>0)
			$sql .= " AND tp.id = {$limit_to_tpid}";

		$sth = $dbh->query($sql);
		if ($sth === FALSE) {
			logEvent("Query failed in ".__FILE__." before line ".__LINE__."! Error description: " .
				implode('; ', $dbh->errorInfo()) ."; Terminating.", LLERROR);
			die("query failed in ".__FILE__." before line ".__LINE__);
		}

		while ($result = $sth->fetch(PDO::FETCH_ASSOC)) {
			logEvent("Reprogramming time program ID {$result['tpid']}", LLINFO_ACTION);
			logEvent("Dataset: ".implode(';', $result), LLDEBUG);

			$found = false;

			//determine starting day to go through next 7 days. default now, except for
			//time programs which are not yet valid (i.e. laying in the future)

			$ts = time();
			$timenow = date('H:i', $ts);

			//depending on whether a job is active or not we need to consider either switch_off_time or switch_on_time
			if (!$result['active']) {
				$time_to_consider = $result['switch_on_time'];
			}
			else {
				if ($result['switch_off_priority'] != 'runtime') {
					$time_to_consider = $result['switch_off_time'];
				}
				else {
					//calc runtime
					$offtime = substr($result['switch_off_time'], 0, 2)*60 + substr($result['switch_off_time'], 3, 2);
					$ontime = substr($result['switch_on_time'], 0, 2)*60 + substr($result['switch_on_time'], 3, 2);
					if ($offtime < $ontime)
						$runtime = 1440 - ($ontime - $offtime);
					else
						$runtime = $offtime - $ontime;
					//calc time to switch off - add another 60 to be sure no to act too early.
					$time_to_consider = date('H:i', $result['time_switched_on']+$runtime*60+60);
				}
			}

			//if current time is less than time_to_consider start from the current day. otherwise start from the next day
			$i = ($timenow < $time_to_consider ? 0 : 1);

			//if time program will be valid only in the future
			if ($result['valid_from'] > date('Y-m-d')) {
				//start from the first valid day
				$ts = strtotime($result['valid_from']); //future
				$timenow = date('H:i', $ts);
				$i = 0;
			}

			//cycle through next 7 days to find the next 'action-day'
			while ($i<=7) {
				$ts_i = $ts + $i*86400;
				$day_i = date('w', $ts_i);
				$date_i = date('Y-m-d', $ts_i);
				//if time program is active we don't care about d0,d1.., valid_until, valid_from - we just need to switch off
				if ($result['active'] ||
						($result["d$day_i"] && $result['valid_until'] >= $date_i && $result['valid_from'] <= $date_i)) {
					$found = true;
					break;
				}
				$i++;
			} //while

			if ($found) {
				logEvent("Reprogram-All: Reprogramming to switch " .($result['active'] ? 'off':'on').
					" at {$time_to_consider} on $date_i. Time program id {$result['tpid']}", LLINFO_ACTION);
				//reprog at
				$atcmd = $cfg['at_cmd_line'] . " {$time_to_consider} {$date_i}";
				$tmprv = programAt($atcmd);
				$at_retval = $at_retval && $tmprv;
			}
			else
				logEvent("Reprogram-All: Time program id {$result['tpid']} is not getting reprogrammed.", LLINFO_ACTION);
		} //while
		$sth->closeCursor();
		$sth = null;

		return $at_retval;
	} //reprogramAt



	function handleTimePrograms($dbh, &$new_ow_state, &$switch_mode, &$time_programs_to_delete, $reprogramming_at) {

		global $cfg;

		//get all time programs where an action is due. i.e. all for switching on and all for switching off
		//off: with tolerance of 59secs
		//query: time programs to be switched on
		$wday = date('w');
		$sql = "SELECT 'on' AS qry, tp.id AS tpid, s.id AS sid, *, -1 AS time_beeing_active
			FROM time_program tp INNER JOIN switch s ON tp.switch_id = s.id
			WHERE NOT tp.active AND tp.d$wday
				AND strftime('%Y-%m-%d', 'now', 'localtime') BETWEEN tp.valid_from AND tp.valid_until
				AND ( (tp.switch_on_time <= tp.switch_off_time AND
						tp.switch_on_time <= strftime('%H:%M', 'now', 'localtime') AND strftime('%H:%M', 'now', 'localtime') < tp.switch_off_time)
					OR (tp.switch_on_time > tp.switch_off_time AND
						(tp.switch_on_time <= strftime('%H:%M', 'now', 'localtime') OR strftime('%H:%M', 'now', 'localtime') < tp.switch_off_time))
				)";
		//query: time programs to be switched off - all where runtime is greater than actually programmed, independed of switch_off_prio
		$sql .= "UNION
			SELECT 'off' AS qry, tp.id AS tpid, s.id AS sid, *, strftime('%s', 'now') - time_switched_on AS time_beeing_active
			FROM time_program tp INNER JOIN switch s ON tp.switch_id = s.id
			WHERE tp.active
				AND ( (tp.switch_on_time <= tp.switch_off_time AND strftime('%s', 'now') - tp.time_switched_on + 59 >
						strftime('%s', tp.switch_off_time)-strftime('%s', tp.switch_on_time) )
					OR (tp.switch_on_time > tp.switch_off_time AND strftime('%s', 'now') - tp.time_switched_on + 59 >
						strftime('%s', tp.switch_off_time)+86400 - strftime('%s', tp.switch_on_time) )
				)
			";
		//query: time programs to be switched off - switch_off_priority = 'time', meaning that runtime is less than actually programmed
		$sql .= "UNION
			SELECT 'off' AS qry, tp.id AS tpid, s.id AS sid, *, strftime('%s', 'now') - time_switched_on AS time_beeing_active
			FROM time_program tp INNER JOIN switch s ON tp.switch_id = s.id
			WHERE tp.active AND switch_off_priority = 'time'
				AND ( (tp.switch_on_time <= tp.switch_off_time AND
						(strftime('%H:%M', 'now', 'localtime') < tp.switch_on_time OR strftime('%H:%M', 'now', 'localtime') >= tp.switch_off_time))
					OR (tp.switch_on_time > tp.switch_off_time AND
						(strftime('%H:%M', 'now', 'localtime') >= tp.switch_off_time AND strftime('%H:%M', 'now', 'localtime') < tp.switch_on_time))
				)
			ORDER BY qry ASC";

		$sth = $dbh->query($sql);
		if ($sth === FALSE) {
			logEvent("Query failed in ".__FILE__." before line ".__LINE__."! Error description: " . implode('; ', $dbh->errorInfo()) ."; Terminating.",
				LLERROR);
			die("query failed in ".__FILE__." before line ".__LINE__);
		}

		while ($result = $sth->fetch(PDO::FETCH_ASSOC)) {
			logEvent('Loaded time program ID '.$result['tpid'], LLINFO);
			logEvent('Dataset: '.implode(';', $result), LLDEBUG);

			$ow_addr = $result['ow_address'];
			$ow_path_base = "/$ow_addr/";

			//check type
			//todo: if switch is temporarily not present, the corresponding time program will not be reprogrammed
			//this needs to be changed -
			if (!checkOWType($ow_path_base, $result['ow_type'], $result))
				continue;

			//check status on 1wire
			$ow_path = "{$ow_path_base}{$result['ow_pio']}";
			$ow_state = get($ow_path);
			logEvent("OW Status for $ow_path: $ow_state - program id: {$result['tpid']}", LLINFO);

			//save switch mode for later use
			$switch_mode[$ow_path] = $result['mode'];

			//time program to switch on, i.e. active==0
			if ($result['qry'] == 'on') {
				//save new state for 1wire and update database entry
				$new_ow_state[$ow_path] = 1;
				$sql = "UPDATE time_program SET time_switched_on=strftime('%s', 'now'), active=1 WHERE id = {$result['tpid']} ";
				$ts = time();
				$ra = $dbh->exec($sql);
				if ($ra === FALSE) {
					logEvent("Query failed in ".__FILE__." before line ".__LINE__."! Error description: " . implode('; ', $dbh->errorInfo()), LLERROR);
				}

				logEvent("Updated time_program to active. Id {$result['tpid']}, Rows affected by query: $ra", LLINFO_ACTION);

				//decide how/when to switch off again
				if ($result['switch_off_priority'] == 'runtime') {
					//reprogram at - priority given to runtime
					$offtime = substr($result['switch_off_time'], 0, 2)*60 + substr($result['switch_off_time'], 3, 2);
					$ontime = substr($result['switch_on_time'], 0, 2)*60 + substr($result['switch_on_time'], 3, 2);
					if ($offtime < $ontime)
						$runtime = 1440 - ($ontime - $offtime);
					else
						$runtime = $offtime - $ontime;
					$switch_off_time = date('H:i Y-m-d', time()+$runtime*60);
					$runtime = sprintf('%02d:%02d',floor($runtime/60), $runtime%60);
					logEvent("Reprogramming (prio runtime) to switch off at $switch_off_time (runtime: $runtime). Time program id {$result['tpid']}",
						LLINFO_ACTION);
					$atcmd = $cfg['at_cmd_line'] .' '. $switch_off_time;
					programAt($atcmd);
				}
				else {
					//reprogram at - priority given to switch_off_time, not total runtime (i.e. switch_off_time-switch_on_time)
					$timenow = date('H:i', $ts);
					if ($timenow > $result['switch_off_time'])
						$ts += 86400;
					$date_i = date('Y-m-d', $ts);
					logEvent("Reprogramming (prio time off) to switch off at {$result['switch_off_time']} on $date_i. Time program id {$result['tpid']}",
						LLINFO_ACTION);
					$atcmd = $cfg['at_cmd_line'] . " {$result['switch_off_time']} $date_i";
					programAt($atcmd);
				}
			} //mode==timer and qry=on

			//time programs to switch off, i.e. active==1
			if ($result['qry'] == 'off') {
				//check if there are other active programs, and whether they should be turned of by this current time program
				//save new state for 1wire
				if ($result['override_other_programs_when_turning_off']) {
					//just override in any case
					logEvent("Override flag set. Will turn off path $ow_path in any case.", LLINFO);
					$new_ow_state[$ow_path] = 0;
				} //if override
				else {
					//if not overriding, check if there are other active time programs for the same switch. if
					//there are any, do not turn off
					$sql = "SELECT COUNT(*) AS ct FROM time_program WHERE id != {$result['tpid']} AND active AND switch_id = {$result['switch_id']}";
					$sth_ovr = $dbh->query($sql);
					if ($sth_ovr === FALSE) {
						logEvent("Query failed in ".__FILE__." before line ".__LINE__."! Error description: " .
							implode('; ', $dbh->errorInfo()) ."; Terminating.", LLERROR);
						die("query failed in ".__FILE__." before line ".__LINE__);
					}
					$ct = $sth_ovr->fetch(PDO::FETCH_ASSOC);
					logEvent("Checking whether there are any other active programs for path $ow_path: {$ct['ct']} active.", LLDEBUG);
					$sth_ovr->closeCursor();
					$sth_ovr = null;
					$new_ow_state[$ow_path] = ($ct['ct'] > 0 ? 1 : 0);
				}
				//update database entry of time_program
				$sql = "UPDATE time_program SET active=0 WHERE id = {$result['tpid']}";
				$ra = $dbh->exec($sql);
				if ($ra === FALSE) {
					logEvent("Query failed in ".__FILE__." before line ".__LINE__."! Error description: " . implode('; ', $dbh->errorInfo()), LLERROR);
				}

				logEvent("Updated time_program to inactive. Id {$result['tpid']}, Rows affected by query: $ra", LLINFO_ACTION);

				//reprogram at, go through next 7 days and check whether one of those match the criteria of the time program
				$ts = time();
				$timenow = date('H:i', $ts);
				$found = false;
				//if current time is less than switch_on_time it means that the time program was started the day before
				//therefore check if it should be reprogrammed starting from the current day. otherwise start from the next day
				$i = ($timenow < $result['switch_on_time'] ? 0 : 1);
				while ($i<=7) {
					$ts_i = $ts + $i*86400;
					$day_i = date('w', $ts_i);
					$date_i = date('Y-m-d', $ts_i);
					if ($result["d$day_i"] && $result['valid_until'] >= $date_i && $result['valid_from'] <= $date_i) {
						$found = true;
						break;
					}
					$i++;
				} //for
				//reprogram only if found and if -r/-reprogram-all is not given
				if ($found) {
					if (!$reprogramming_at) {
						logEvent("Reprogramming to switch on at {$result['switch_on_time']} on $date_i. Time program id {$result['tpid']}", LLINFO_ACTION);
						//reprog at
						$atcmd = $cfg['at_cmd_line'] . " {$result['switch_on_time']} $date_i";
						programAt($atcmd);
					}
				}
				else {
					//no next possible day has been found on which to execute. i.e. time program
					//is getting invalid. check flag no whether or not to delete
					logEvent("Time program id {$result['tpid']} is not getting reprogrammed.", LLINFO_ACTION);
					if ($result['delete_after_becoming_invalid'])
						$time_programs_to_delete[$result['tpid']] = $result;
				}

			} //mode==timer and qry==off

		} //while time programs

		$sth->closeCursor();
		$sth = null;

	} //handleTimePrograms



	function setOWSwitch($switch_mode, $new_ow_state) {
		//take action on 1wire, cycle through $new_ow_state
		foreach ($new_ow_state as $k => $v) {
			//check mode for switches - in case of 'on' or 'off' override the value from $new_ow_state
			if ($switch_mode[$k] == 'on')
				$v = 1;
			if ($switch_mode[$k] == 'off')
				$v = 0;

			$ow_state = get($k);
			if ($ow_state == null) {
				logEvent("Setting new OW state: cannot get current state for $k. Omitting this switch.", LLWARN);
				continue;
			}

			logEvent("Switch is in mode {$switch_mode[$k]}. Current state: $ow_state. Setting new OW state for $k: $v", LLINFO_OWACTION);
			if ($ow_state != $v)
				put($k, $v);
		} //foreach - take action on OW
	} //function setOWSwitch



	function deleteExpiredTimePrograms($dbh, $time_programs_to_delete) {
		if (empty($time_programs_to_delete))
			return;

		foreach($time_programs_to_delete as $k => $v)
			logEvent('Queuing time program for deletion after it became invalid: '. implode(',', array_keys($v)) .' - '. implode(',', $v), LLINFO);

		$sql = "DELETE FROM time_program WHERE id IN (".implode(', ', array_keys($time_programs_to_delete)) .")
			AND delete_after_becoming_invalid";
		$ra = $dbh->exec($sql);
		if ($ra === FALSE) {
			logEvent("Query failed in ".__FILE__." before line ".__LINE__."! Error description: " . implode('; ', $dbh->errorInfo()), LLERROR);
			return FALSE;
		}

		logEvent("Deleted time programs with IDs (".implode(', ', array_keys($time_programs_to_delete)) ."). Rows affected by query: $ra",
			LLINFO_ACTION);
		return TRUE;
	} //deleteExpiredTimePrograms
