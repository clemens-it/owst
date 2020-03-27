<?php

//small helper functions

function userTimeToMin($tm_str) {
	//converts user time given in format hh:mm or decimal (hours) to minutes
	//does not perform any check whether $tm_str containts a syntactically valid string
	$colpos = strpos($tm_str, ':');

	//if there is no colon, time format is decimal in hours
	if ($colpos === FALSE)
		$min = (float)str_replace(',', '.', $tm_str)*60;
	else
		$min = (int)substr($tm_str, 0, $colpos)*60 + (int)substr($tm_str, $colpos+1, 2);

	return $min;
} //function userTimeToMin



function immediateInsertAndActivate($dbh, $data, $action) {
	//inserts immediate action into database and activates AT queue as well as one wire switch
	//$action must be either 'switch_on_for' or 'switch_off_in'
	//returns TRUE on success, error messages (string) otherwise

	$errormsg = '';

	//prepare SQL statement
	foreach ($data as $k => $v)
		$data[$k] = $dbh->quote($v);
	$sql = "INSERT INTO time_program (". implode(', ', array_keys($data)) .") VALUES (".
		implode(', ', $data) .")";

	//exec SQL and check results
	$ra = $dbh->exec($sql);
	($ra === FALSE) and
		die('Query failed in '.__FILE__.' before line '.__LINE__.'! Error description: ' . implode('; ', $dbh->errorInfo()));
	if ($ra <> 1)
		$errormsg .= "Rows affected by query does not equal to 1, but is $ra\n";
	else {
		//get id of inserted record for logging and programming AT queue
		$nid = $dbh->lastInsertId();
		logEvent("Immediate time program has been inserted. ID $nid", LLINFO_ACTION);

		//activate job - either switching off or on
		if ($action == 'switch_off_in') {
			//reprogram AT for new job - switching off
			logEvent("Programming AT queue after inserting time program", LLINFO_ACTION);
			$rv1 = reprogramAt($dbh, $nid);
			if (!$rv1)
				$errormsg .= "Problem occurred during AT-reprogramming. Please check the log\n";
		}
		else {
			//switch on by calling owSwitchTimerControl. AT will be also programmed by that routine
			owSwitchTimerControl($dbh);
		}
	}

	//return result
	return $errormsg == '' ? TRUE : $errormsg;
} //function immediateInsertAndActivate
