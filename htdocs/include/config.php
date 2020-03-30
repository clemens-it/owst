<?php

	$cfg = array();

	//one wire settings
	$cfg['ow_adapter'] = 'localhost:4304';

	//path settings
	$cfg['libdir'] = '/var/lib/owst';
	$cfg['logdir'] = '/var/log';

	//database settings
	$cfg['dsn'] = 'sqlite:' .$cfg['libdir']. '/owst.sq3';
	$cfg['dbuser'] = '';
	$cfg['dbpass'] = '';

	//dates for fields valid_from and valid_until to use to indicate 'forever'
	$cfg['forever_valid_from'] = '1999-01-01';
	$cfg['forever_valid_until'] = '2099-01-01';


	//set if you want logging. if the setting contains no slashes it will be
	//used as a tag and the message will go to syslog. if log_file is empty no logging occurs
	$cfg['log_file']  = $cfg['logdir']. '/owst.log';
	$cfg['lock_file'] = $cfg['libdir']. '/owst.lock';

	//at user - user which ownes the AT queue
	//a corresponding line in /etc/sudoers is required for www-data to show the AT queue:
	// www-data ALL = (pi) NOPASSWD: /usr/bin/atq
	$cfg['at_user'] = 'pi';

	//at command line
	//$cfg['at_cmd_line'] = "echo $cfg_executable | at ";
	//At tries to get back into the current working directory at execution time. if it takes
	//the cwd of the web script, at will fail at execution because the at_user might not have
	//the rights to access the directory of the web script
	$cfg['at_cmd_line'] = "cd /; echo $cfg_executable | sudo -nu ".escapeshellarg($cfg['at_user']) ." at ";

	//$cfg['atq_cmd_line'] = "atq | sort -k6n -k3M -k4n -k5n";
	$cfg['atq_cmd_line'] = "(sudo -nu ".escapeshellarg($cfg['at_user']) ." atq | sort -k6n -k3M -k4n -k5n) 2>&1";

	//$cfg['atempty_cmd_line'] = "atq | cut -f1 | xargs --no-run-if-empty atrm";
	//(sudo -nu pi atq | cut -f1 | xargs --no-run-if-empty sudo -nu pi atrm) 2>&1
	$cfg['atempty_cmd_line'] = "(sudo -nu ".escapeshellarg($cfg['at_user']) ." atq | cut -f1 | ".
			"xargs --no-run-if-empty sudo -nu ".escapeshellarg($cfg['at_user']) ." atrm) 2>&1";

	// ------------ usually there should be no need to make any changes below here ------------ //
	$cfg['use_syslog'] = ($cfg['log_file'] != '' && strstr($cfg['log_file'], '/') === FALSE);

	//maximum of bytes to load from logfile
	$cfg['showlog_maxsize'] = 500*1024;

	//data definition for time program
	$cfg['dd']['time_program'] = array(
		'name' => array('name' => 'Name',
			'regex' => '^.+$',
			'format' => 'required value',
			),
		'switch_on_time' => array(
			'regex' => '^([01]\d|2[0-3]):([0-5]\d)$',
			'format' => 'hh:mm',
			),
		'switch_off_time' => array(
			'regex' => '^([01]\d|2[0-3]):([0-5]\d)$',
			'format' => 'hh:mm',
			),
		'valid_from' => array(
			'regex' => '^(1999|2\d{3})-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$',
			'checkfunction' => 'wrapCheckDate',
			'format' => 'yyyy-mm-dd',
			),
		'valid_until' => array(
			'regex' => '^(1999|2\d{3})-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$',
			'checkfunction' => 'wrapCheckDate',
			'format' => 'yyyy-mm-dd',
			),
		'switch_off_priority' => array(
			'regex' => '^(time|runtime)$',
			'format' => "Must be either 'time' or 'runtime'",
			),
		'd0' => array('name'=>'day0',
			'regex' => '^[01]$',
			'format' => "Must be either 0 or 1, i.e. Off or On",
			),
		'd1' => array('name'=>'day1',
			'regex' => '^[01]$',
			'format' => "Must be either 0 or 1, i.e. Off or On",
			),
		'd2' => array('name'=>'day2',
			'regex' => '^[01]$',
			'format' => "Must be either 0 or 1, i.e. Off or On",
			),
		'd3' => array('name'=>'day3',
			'regex' => '^[01]$',
			'format' => "Must be either 0 or 1, i.e. Off or On",
			),
		'd4' => array('name'=>'day4',
			'regex' => '^[01]$',
			'format' => "Must be either 0 or 1, i.e. Off or On",
			),
		'd5' => array('name'=>'day5',
			'regex' => '^[01]$',
			'format' => "Must be either 0 or 1, i.e. Off or On",
			),
		'd6' => array('name'=>'day6',
			'regex' => '^[01]$',
			'format' => "Must be either 0 or 1, i.e. Off or On",
			),
		'override_other_programs_when_turning_off' => array(
			),
		'delete_after_becoming_invalid' => array(
			),
		'switch_id' => array(
			'regex' => '^\d+$',
			'format' => 'Must be an integer',
			),
		/* not enlisted:
			id, active, time_switched_on
		*/
		);


	function wrapCheckDate($datestr) {
		//wrapper for checkdate - splits a date into year, month, day from a string and
		//feeds it to checkdate
		unset($match);
		if (preg_match('/^(1999|2\d{3})-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/', $datestr, $match)) {
			//checkdate(month, day, year)
			return checkdate($match[2], $match[3], $match[1]);
		}
		else
			return FALSE;
	} //wrapCheckDate
