<?php
	require_once 'wi_helpers.php';

	if ($_REQUEST['subaction'] == 'list') {
		@$sid = intval($_GET['sid']);
		($sid > 0) or die("Switch ID is $sid");

		$sql = "SELECT tp.id AS tpid, s.name AS sname, s.mode, s.ow_address, s.ow_pio, tp.* "
			."FROM switch s LEFT OUTER JOIN time_program tp ON tp.switch_id = s.id "
			."WHERE s.id = $sid";

		$sth = $dbh->query($sql);
		($sth === FALSE) and
			die('Query failed in '.__FILE__.' before line '.__LINE__.'! Error description: ' . implode('; ', $dbh->errorInfo()));

		//one wire: init
		if (!init($cfg['ow_adapter'])) {
			logEvent("cannot initialize 1-wire bus", LLERROR);
			$errormsg .= "setmode: cannot initialize 1-wire bus.\n";
		}

		$data = array();
		$sw_status = -999;
		$tp_count = 0;
		while ($result = $sth->fetch(PDO::FETCH_ASSOC)) {
			if ($sw_status == -999)
				$sw_status = get("/{$result['ow_address']}/{$result['ow_pio']}");
			if ($result['tpid'] != "")
				$tp_count++;
			$result['forever_valid_from'] = ($result['valid_from'] == $cfg['forever_valid_from']);
			$result['forever_valid_until'] = ($result['valid_until'] == $cfg['forever_valid_until']);
			$data[] = $result;
		}


		$smarty->assign('data', $data);
		$smarty->assign('tp_count', $tp_count);
		$smarty->assign('sw_status', $sw_status);
		$smarty->assign('sw_modes', array('on'=>'On', 'off'=>'Off', 'timer'=>'Timer'));
		$smarty->assign('immediate_opt', array('switch_on_for'=>'Switch on for', 'switch_off_in'=>'Switch off in'));
		$smarty->assign('sid', $sid);
		$smarty_view = 'timeprogram.tpl';
	} // subaction == list



	if ($_REQUEST['subaction'] == 'edit') {
		@$tpid = intval($_GET['tpid']);
		($tpid > 0) or die("Time Program ID is $tpid");

		$sql = "SELECT tp.id AS tpid, s.id AS sid, s.name AS sname, tp.* "
			."FROM time_program tp INNER JOIN switch s ON tp.switch_id = s.id "
			."WHERE tp.id = $tpid";

		$sth = $dbh->query($sql);
		($sth === FALSE) and
			die('Query failed in '.__FILE__.' before line '.__LINE__.'! Error description: ' . implode('; ', $dbh->errorInfo()));

		$data = array();
		//there's only one record
		if ($result = $sth->fetch(PDO::FETCH_ASSOC)) {
			$result['forever_valid_from'] = ($result['valid_from'] == $cfg['forever_valid_from']);
			$result['forever_valid_until'] = ($result['valid_until'] == $cfg['forever_valid_until']);
			$result['time_switched_on_f'] = ($result['time_switched_on'] == 0 ? 'never' :
				date('Y-m-d H:i:s', $result['time_switched_on']));
			$runtimed = intval( (time()-$result['time_switched_on']) / 86400);
			$result['runtime'] = ($runtimed > 0 ? $runtimed .' days ' : ''). gmdate('H:i:s', time()-$result['time_switched_on']);
			$data = $result;

			$smarty->assign('data', $data);
			$smarty->assign('sid', $data['switch_id']);
			$smarty->assign('form_mode', 'edit');
			$smarty->assign('so_priorities', array('runtime'=>'Runtime', 'time'=>'Switch off time'));
			$smarty->assign('cfg_forever_valid_from', $cfg['forever_valid_from']);
			$smarty->assign('cfg_forever_valid_until', $cfg['forever_valid_until']);
			$smarty_view = 'timeprogram_edit.tpl';
		}
		else
			die("no data");

	} // subaction == edit



	if ($_REQUEST['subaction'] == 'update') {
		@$data = $_REQUEST['tp'];
		//print_r($_REQUEST);
		is_array($data) or die("tp / data is not an array");
		@$tpid = intval($data['id']);
		($tpid > 0) or die("Time Program ID is $tpid");

		//sid: needed for redirect
		@$sid = intval($data['switch_id']);
		($sid > 0) or die("Switch ID is $sid");

		//handle checkboxes
		@$data['override_other_programs_when_turning_off'] = ($data['override_other_programs_when_turning_off'] == 'on' ?  1 : 0);
		@$data['delete_after_becoming_invalid'] = ($data['delete_after_becoming_invalid'] == 'on' ?  1 : 0);
		$columns = $cfg['dd']['time_program'];
		//we do not need to change the following columns
		unset($columns['switch_id']);

		$errormsg = "";
		$rv1 = $rv2 = TRUE;

		//check data integrity
		foreach ($columns as $k => $v) {
			if (isset($data[$k])) {
				$data[$k] = trim($data[$k]);
				if (!isset($v['name']))
					$v['name'] = str_replace('_', ' ', $k);
				if (isset($v['regex']) && !preg_match('/'.$v['regex'].'/', $data[$k])) {
					$errormsg .= "'{$v['name']}' does not meet format or requirements: {$v['format']}\n";
					continue;
				}
				if (isset($v['checkfunction']) && !$v['checkfunction']($data[$k]))
					$errormsg .= "'{$v['name']}' did not pass validity check\n";
			}
		} //foreach

		//compile and execute SQL update statement only if there are no errors
		if (empty($errormsg)) {
			$sql = "UPDATE time_program SET ";
			foreach ($columns as $k => $v) {
				$sql .= "$k = ". $dbh->quote($data[$k]) .", ";
			}
			$sql = substr($sql, 0, -2);
			$sql .= " WHERE id = {$tpid}";

			$ra = $dbh->exec($sql);
			($ra === FALSE) and
				die('Query failed in '.__FILE__.' before line '.__LINE__.'! Error description: ' . implode('; ', $dbh->errorInfo()));

			if ($ra == 1)
				logEvent("Time program ID $tpid has been updated", LLINFO_ACTION);
			else
				$errormsg .= "Rows affected by query does not equal to 1, but is $ra\n";

			if (empty($errormsg)) {
				//TODO: possible optimization: depending on the changed properties it may (or not) be required
				//to empty the at-queue and to reprogram it. for now we just do it after every change
				logEvent("Reprogramming AT queue after update of time program", LLINFO_ACTION);
				$rv1 = emptyAtQueue();
				$rv2 = reprogramAt($dbh);
			}
		} //if errormsg empty -> UPDATE

		if (!$rv1 || !$rv2)
			$errormsg .= "Problem occurred during AT-reprogramming. Please check the log\n";

		//if no error, after saving, redirect to edit
		if (empty($errormsg)) {
			$redirect = TRUE;
			$redirect_param_str = "action=timeprogram&subaction=list&sid=$sid";
		}
		else {
			//in case of error just show error messages and some additional information from the database
			$sql = "SELECT tp.id AS tpid, s.id AS sid, s.name AS sname, tp.* "
				."FROM time_program tp INNER JOIN switch s ON tp.switch_id = s.id "
				."WHERE tp.id = $tpid";
			$sth = $dbh->query($sql);
			($sth === FALSE) and
				die('Query failed in '.__FILE__.' before line '.__LINE__.'! Error description: ' . implode('; ', $dbh->errorInfo()));
			//there's only one record
			if ($result = $sth->fetch(PDO::FETCH_ASSOC)) {
				//set calculated values - based from 'read-only' columns from the query result
				$data['time_switched_on_f'] = ($result['time_switched_on'] == 0 ? 'never' :
					date('Y-m-d H:i:s', $result['time_switched_on']));
				$runtimed = intval( (time()-$result['time_switched_on']) / 86400);
				$data['runtime'] = ($runtimed > 0 ? $runtimed .' days ' : ''). gmdate('H:i:s', time()-$result['time_switched_on']);
				$data['sname'] = $result['sname'];
				$data['sid'] = $result['sid'];
				$data['tpid'] = $result['tpid'];
			}

			//set checkboxes 'no limit' again - based on submitted data
			$data['forever_valid_from'] = ($data['valid_from'] == $cfg['forever_valid_from']);
			$data['forever_valid_until'] = ($data['valid_until'] == $cfg['forever_valid_until']);

			$smarty->error_reporting = E_ALL & ~E_NOTICE;
			$smarty->assign('errormsg', $errormsg);
			$smarty->assign('data', $data);
			$smarty->assign('sid', $data['switch_id']);
			$smarty->assign('form_mode', 'edit');
			$smarty->assign('so_priorities', array('runtime'=>'Runtime', 'time'=>'Switch off time'));
			$smarty->assign('cfg_forever_valid_from', $cfg['forever_valid_from']);
			$smarty->assign('cfg_forever_valid_until', $cfg['forever_valid_until']);
			$smarty_view = 'timeprogram_edit.tpl';
		} //not empty errormsg
	} // subaction == save



	if ($_REQUEST['subaction'] == 'addnew') {
		@$sid = intval($_GET['sid']);
		($sid > 0) or die("Switch ID is $sid");


		$smarty->assign('sid', $sid);
		$smarty->assign('form_mode', 'insert');
		$smarty->assign('so_priorities', array('runtime'=>'Runtime', 'time'=>'Switch off time'));
		$smarty->assign('cfg_forever_valid_from', $cfg['forever_valid_from']);
		$smarty->assign('cfg_forever_valid_until', $cfg['forever_valid_until']);
		$smarty->error_reporting = E_ALL & ~E_NOTICE;
		$smarty_view = 'timeprogram_edit.tpl';
	} // subaction == addnew



	if ($_REQUEST['subaction'] == 'insert') {
		@$data = $_REQUEST['tp'];
		is_array($data) or die("tp / data is not an array");

		@$sid = intval($data['switch_id']);
		($sid > 0) or die("Switch ID is $sid");

		//handle checkboxes
		@$data['override_other_programs_when_turning_off'] = ($data['override_other_programs_when_turning_off'] == 'on' ?  1 : 0);
		@$data['delete_after_becoming_invalid'] = ($data['delete_after_becoming_invalid'] == 'on' ?  1 : 0);
		$daysum = 0;
		for ($i=0; $i<=6; $i++) {
			@$data['d'.$i] = (empty($data['d'.$i]) ? 0 : 1);
			$daysum += $data['d'.$i];
		}
		$columns = $cfg['dd']['time_program'];

		$errormsg = "";
		//check data integrity
		if ($daysum == 0)
			$errormsg = "Time program needs to be active for at least one weekday\n";
		foreach ($columns as $k => $v) {
			if (isset($data[$k])) {
				$data[$k] = trim($data[$k]);
				if (isset($v['regex']) && !preg_match('/'.$v['regex'].'/', $data[$k])) {
				if (!isset($v['name']))
					$v['name'] = str_replace('_', ' ', $k);
					$errormsg .= "'{$v['name']}' does not meet format or requirements: {$v['format']}\n";
					continue;
				}
				if (isset($v['checkfunction']) && !$v['checkfunction']($data[$k]))
					$errormsg .= "'{$v['name']}' did not pass validity check\n";			}
		} //foreach

		//compile SQL statement
		if (empty($errormsg)) {
			$sql = "INSERT INTO time_program (". implode(', ', array_keys($columns)) .") VALUES (";
			foreach ($columns as $k => $v) {
				$sql .= $dbh->quote($data[$k]) .", ";
			}
			$sql = substr($sql, 0, -2);
			$sql .= ")";

			$ra = $dbh->exec($sql);
			($ra === FALSE) and
				die('Query failed in '.__FILE__.' before line '.__LINE__.'! Error description: ' . implode('; ', $dbh->errorInfo()));
			if ($ra <> 1)
				$errormsg .= "Rows affected by query does not equal to 1, but is $ra\n";
			else {
				$nid = $dbh->lastInsertId();
				logEvent("New time program has been inserted. ID $nid", LLINFO_ACTION);

				//reprogram at for net time program and call owSwitchTimerControl in case the time program should get active immediately
				logEvent("Programming AT queue after inserting time program", LLINFO_ACTION);
				$rv1 = reprogramAt($dbh, $nid);
				owSwitchTimerControl($dbh);
			}
		}

		if (empty($errormsg)) {
			$redirect = TRUE;
			$redirect_param_str = "action=timeprogram&subaction=list&sid=$sid";
		}
		else {
			$smarty->assign('errormsg', $errormsg);
			$smarty->assign('sid', $sid);
			$smarty->assign('form_mode', 'insert');

			$smarty->assign('data', $data);
			$smarty->assign('so_priorities', array('runtime'=>'Runtime', 'time'=>'Switch off time'));
			$smarty->assign('cfg_forever_valid_from', $cfg['forever_valid_from']);
			$smarty->assign('cfg_forever_valid_until', $cfg['forever_valid_until']);
			$smarty->error_reporting = E_ALL & ~E_NOTICE;
			$smarty_view = 'timeprogram_edit.tpl';
		}
	} // subaction == insert



	if ($_REQUEST['subaction'] == 'delete') {
		@$tpid = intval($_GET['tpid']);
		($tpid > 0) or die("Time Program ID is $tpid");
		@$sid = intval($_GET['sid']);
		($sid > 0) or die("Switch ID is $sid");

		$errormsg = $msg = '';
		$sql = "DELETE FROM time_program WHERE id = " .$dbh->quote($tpid);
		$ra = $dbh->exec($sql);
		($ra === FALSE) and
			die('Query failed in '.__FILE__.' before line '.__LINE__.'! Error description: ' . implode('; ', $dbh->errorInfo()));
		if ($ra <> 1)
			$errormsg .= "Delete: Rows affected by query does not equal to 1, but is $ra\n";
		else {
			logEvent("Time program ID $tpid has been deleted", LLINFO_ACTION);
			//emptying and repogramming of the at queue isn't really necessary
			//at jobs of deleted time program will execute the script and nothing
			//will happen
		}

		if (empty($errormsg)) {
			$redirect = TRUE;
			$redirect_param_str = "action=timeprogram&subaction=list&sid=$sid";
		}
		else {
			$smarty->assign('msg', $msg);
			$smarty->assign('errormsg', $errormsg);
			$smarty_view = 'messages.tpl';
		}
	} // subaction == addnew



	if ($_REQUEST['subaction'] == 'clone') {
		@$tpid = intval($_GET['tpid']);
		($tpid > 0) or die("Time Program ID is $tpid");

		$sql = "SELECT tp.id AS tpid, s.id AS sid, s.name AS sname, tp.* "
			."FROM time_program tp INNER JOIN switch s ON tp.switch_id = s.id "
			."WHERE tp.id = $tpid";

		$sth = $dbh->query($sql);
		($sth === FALSE) and
			die('Query failed in '.__FILE__.' before line '.__LINE__.'! Error description: ' . implode('; ', $dbh->errorInfo()));

		$data = array();
		//there's only one record
		if ($result = $sth->fetch(PDO::FETCH_ASSOC)) {
			$result['forever_valid_from'] = ($result['valid_from'] == $cfg['forever_valid_from']);
			$result['forever_valid_until'] = ($result['valid_until'] == $cfg['forever_valid_until']);
			$result['time_switched_on_f'] = ($result['time_switched_on'] == 0 ? 'never' :
				date('Y-m-d H:i:s', $result['time_switched_on']));
			$runtimed = intval( (time()-$result['time_switched_on']) / 86400);
			$result['runtime'] = ($runtimed > 0 ? $runtimed .' days ' : ''). gmdate('H:i:s', time()-$result['time_switched_on']);
			unset($result['tpid']);
			$data = $result;

			$smarty->assign('data', $data);
			$smarty->assign('sid', $data['switch_id']);
			$smarty->assign('form_mode', 'insert');
			$smarty->assign('so_priorities', array('runtime'=>'Runtime', 'time'=>'Switch off time'));
			$smarty->assign('cfg_forever_valid_from', $cfg['forever_valid_from']);
			$smarty->assign('cfg_forever_valid_until', $cfg['forever_valid_until']);
			$smarty_view = 'timeprogram_edit.tpl';
		}
		else
			die("no data");
	} // subcation == clone



	if ($_REQUEST['subaction'] == 'interrupt') {
		@$tpid = intval($_GET['tpid']);
		($tpid > 0) or die("Time Program ID is $tpid");
		@$sid = intval($_GET['sid']);
		($sid > 0) or die("Switch ID is $sid");

		@$intr_from = $_GET['intr_from'];
		@$intr_until = $_GET['intr_until'];

		$errormsg = '';
		wrapCheckDate($intr_from)  or $errormsg .= "'Interrupt from' must be a valid date in the format yyyy-mm-dd\n";
		wrapCheckDate($intr_until) or $errormsg .= "'Interrupt until' must be a valid date in the format yyyy-mm-dd\n";
		(empty($errormsg) && $intr_from >= $intr_until) and $errormsg .= "'Interrupt from' must be earlier then 'Interrupt until'\n";
		/* TODO: get data from database and check existing valid_from and valid_until dates
			valid_from < intr_from,intr_until < valid_until,
		*/
		if (empty($errormsg)) {
			$columns = $cfg['dd']['time_program'];
			$intr_from = $dbh->quote($intr_from);
			$intr_until = $dbh->quote($intr_until);
			unset($columns['valid_from']);
			$sql = "INSERT INTO time_program (". implode(', ', array_keys($columns)) .", valid_from, active, time_switched_on) ".
					"SELECT ". implode(', ', array_keys($columns)) .", {$intr_until}, 0, 0 FROM time_program WHERE id = {$tpid}";
			$ra = $dbh->exec($sql);
			($ra === FALSE) and
				die('Query failed in '.__FILE__.' before line '.__LINE__.'! Error description: ' . implode('; ', $dbh->errorInfo()));
			if ($ra <> 1)
				$errormsg .= "Rows affected by query does not equal to 1, but is $ra\n";
			else {
				$nid = $dbh->lastInsertId();
				logEvent("Interrupt from {$intr_from} until {$intr_until}: New time program has been inserted. ID $nid", LLINFO_ACTION);
				//reprogram at for net time program and call owSwitchTimerControl in the unlikely case
				//the time program should get active immediately
				logEvent("Programming AT queue after inserting time program", LLINFO_ACTION);
				$rv1 = reprogramAt($dbh, $nid);
				owSwitchTimerControl($dbh);
			}
		}
		if (empty($errormsg)) {
			$sql = "UPDATE time_program SET valid_until = {$intr_from}, delete_after_becoming_invalid = 1 WHERE id = {$tpid}";
			$ra = $dbh->exec($sql);
			($ra === FALSE) and
				die('Query failed in '.__FILE__.' before line '.__LINE__.'! Error description: ' . implode('; ', $dbh->errorInfo()));
			if ($ra <> 1)
				$errormsg .= "Rows affected by query does not equal to 1, but is $ra\n";
			else
				logEvent("Interrupt from {$intr_from} until {$intr_until}: Existing time program has been updated", LLINFO_ACTION);
			//can not think of a case where it should be necessary to reprogram AT jobs
		}
		if (empty($errormsg)) {
			$redirect = TRUE;
			$redirect_param_str = "action=timeprogram&subaction=list&sid=$sid";
		}
		else {
			$smarty->assign('errormsg', $errormsg);
			$smarty_view = 'messages.tpl';
		}
	} // subaction == interrupt



	if ($_REQUEST['subaction'] == 'immediate') {
		@$sid = intval($_GET['sid']);
		($sid > 0) or die("Switch ID is $sid");

		@$immtime = $_GET['immtime'];
		@$immaction = $_GET['immaction'];
		$errormsg = '';

		//time input is possible in two formats: hh:mm and a decimal value which is interpreted in hours
		//hh:mm format: hh or mm can be given also in a single digit, like 2:30 (=02:30) or 1:5 (=01:05)
		// hh or mm can be also omitted if zero: e.g. :30 (=00:30), 4: (=04:00), : (=00:00)
		//
		// decimal number: decimal separator is either comma or dot. e.g. 1.5 (=01:30), 2,75 (=02:45), 4 (=04:00)
		$format_hhmm = preg_match('/^([01]?\d|2[0-3])?:([0-5]?\d)?$/', $immtime);
		$format_hdec = preg_match('/^([01]?\d|2[0-3])?([,.]\d{1,2})?$/', $immtime);

		if (!$format_hhmm && !$format_hdec)
			$errormsg .= "Time for immediate action must be in the format hh:mm or ".
				"a decimal number (in hours) and in any case less than 24hours\n";

		preg_match('/^switch_(off_in|on_for)$/', $immaction) or
			$errormsg .= "Immediate action must be either 'switch_off_in' or 'switch_on_for'\n";

		if ($errormsg == '') {
			//convert given run time to minutes
			$immmin = userTimeToMin($immtime);

			$t = time();
			$wd = date('w', $t);
			$ontime = date('G', $t)*60 + date('i', $t);
			$offtime = ($ontime + $immmin) % 1440;

			//calculations are done, format ontime and offtime for database
			$ontime = date('H:i', $t);
			$offtime = sprintf('%02d:%02d',floor($offtime/60), $offtime%60);
			$data = array(
				'switch_id'         => $sid,
				'name'              => 'Immediate: '. ($immaction == 'switch_off_in' ? 'OFF in' : 'ON for'). " {$immtime}",
				'switch_on_time'    => $ontime,
				'switch_off_time'   => $offtime,
				'valid_from'        => date('Y-m-d', $t),
				'valid_until'       => date('Y-m-d', $t),
				"d{$wd}"            => 1,                //default value for dx is 0, so just set the one we need
				'active'            => ($immaction == 'switch_off_in' ? 1 : 0),
				'time_switched_on'  => $t,               //time_switched_on is not considered when switching on
				'switch_off_priority'                      => 'runtime',
				'delete_after_becoming_invalid'            => 1,
				'override_other_programs_when_turning_off' => ($immaction == 'switch_off_in' ? 1 : 0),
			);

			immediateInsertAndActivate($dbh, $data, $immaction);
		} //if empty errormsg

		if ($errormsg == '') {
			$redirect = TRUE;
			$redirect_param_str = "action=timeprogram&subaction=list&sid=$sid";
		}
		else {
			$smarty->assign('errormsg', $errormsg);
			$smarty_view = 'messages.tpl';
		}
	} //subaction == immediate



	if ($_REQUEST['subaction'] == 'immediate_str') {
		@$sid = intval($_GET['sid']);
		($sid > 0) or die("Switch ID is $sid");

		@$immstr = $_GET['immstr'];
		$errormsg = '';

		$rxhhmm = '([01]?\d|2[0-3])?:([0-5]?\d)?';
		$rxhdec = '([01]?\d|2[0-3])?([,.]\d{1,2})?';
		$rxtm = "($rxhhmm|$rxhdec)";
		$immstr = strtolower($immstr);
		preg_match("/^(on)\s+in\s+$rxtm\s+for\s+$rxtm\$|^(off)\s+in\s+$rxtm\s*(for.*)?$/", $immstr, $match) or
			$errormsg .= htmlspecialchars("Invalid command. Must be either 'on in <tm> for <tm>' or 'off in <tm>'. ".
				"Time must be in the format hh:mm or a decimal number (in hours) and in any case less than 24hours\n");

		if ($errormsg == '') {
			if($match[1] == 'on') {
				$tm_start = userTimeToMin($match[2]);
				$tm_duration = userTimeTomin($match[7]);
				$immaction = 'switch_on_for';
				$name = "Immediate: ON in {$match[2]} for {$match[7]}";
			}
			elseif($match[12] == 'off') {
				$tm_duration = userTimeToMin($match[13]);
				$immaction = 'switch_off_in';
				$name = "Immediate: OFF in {$match[13]}";
			}

			$tday = $t = time();
			$ontime = date('G', $t)*60 + date('i', $t);
			if ($immaction == 'switch_on_for') {
				$ontime = $ontime + $tm_start;
				//check if carry over to next day is required
				if ($ontime >= 1440) {
					$tday += 86400;
					$ontime %= 1440;
				}
			}
			$wd = date('w', $tday);
			$offtime = ($ontime + $tm_duration) % 1440;

			//calculations are done, format ontime and offtime for database
			$ontime  = sprintf('%02d:%02d',floor($ontime/60),  $ontime%60);
			$offtime = sprintf('%02d:%02d',floor($offtime/60), $offtime%60);
			$data = array(
				'switch_id'         => $sid,
				'name'              => $name,
				'switch_on_time'    => $ontime,
				'switch_off_time'   => $offtime,
				'valid_from'        => date('Y-m-d', $tday),
				'valid_until'       => date('Y-m-d', $tday),
				"d{$wd}"            => 1,                //default value for dx is 0, so just set the one we need
				'active'            => ($immaction == 'switch_off_in' ? 1 : 0),
				'time_switched_on'  => ($immaction == 'switch_off_in' ? $t : 0),  //time_switched_on is not considered when switching on
				'switch_off_priority'                      => 'runtime',
				'delete_after_becoming_invalid'            => 1,
				'override_other_programs_when_turning_off' => ($immaction == 'switch_off_in' ? 1 : 0),
			);

			immediateInsertAndActivate($dbh, $data, $immaction);
		} //if empty errormsg

		if ($errormsg == '') {
			$redirect = TRUE;
			$redirect_param_str = "action=timeprogram&subaction=list&sid=$sid";
		}
		else {
			$smarty->assign('errormsg', $errormsg);
			$smarty_view = 'messages.tpl';
		}
	} //subaction == immediate_str
