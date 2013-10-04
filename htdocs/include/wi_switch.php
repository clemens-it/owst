<?php
	if ($_REQUEST['subaction'] == 'list') {
		$sql = "SELECT * FROM switch";
		$sth = $dbh->query($sql);
		($sth === FALSE) and 
			die('Query failed in '.__FILE__.' before line '.__LINE__.'! Error description: ' . implode('; ', $dbh->errorInfo()));

		//one wire: init
		if (!init($cfg['ow_adapter'])) {
			logEvent("cannot initialize 1-wire bus", LLERROR);
			$errormsg .= "setmode: cannot initialize 1-wire bus.\n";
		}

		$data = array();
		while ($result = $sth->fetch(PDO::FETCH_ASSOC)) {
			$result['status'] = get("/{$result['ow_address']}/{$result['ow_pio']}");
			$data[] = $result;
		}

		$smarty->assign('data', $data);
		$smarty_view = 'switch.tpl';

	} //subaction == list



	if ($_REQUEST['subaction'] == 'setmode') {
		@$sid = intval($_GET['sid']);
		($sid > 0) or die("Switch ID is $sid");
		
		$allowed_modes = array('on'=>'On', 'off'=>'Off', 'timer'=>'Timer');
		if (isset($_GET['switch_mode']) && isset($allowed_modes[$_GET['switch_mode']]))
			$switch_mode = $_GET['switch_mode'];
		else
			@die("Invalid switch mode: '{$_GET['switch_mode']}'");

		$sql = "UPDATE switch SET mode = ". $dbh->quote($switch_mode) ." WHERE id = {$sid}";
		$ra = $dbh->exec($sql);
		($ra === FALSE) and 
			die('Query failed in '.__FILE__.' before line '.__LINE__.'! Error description: ' . implode('; ', $dbh->errorInfo()));

		if ($ra <> 1)
			$errormsg .= "Rows affected by query does not equal to 1, but is $ra\n";
		else {
			//change mode on OW
			//one wire: init
			if (!init($cfg['ow_adapter'])) {
				logEvent("cannot initialize 1-wire bus", LLERROR);
				$errormsg .= "setmode: cannot initialize 1-wire bus.\n";
			}
			else
				owSwitchTimerControl($dbh, $opt = array('modechange' => 1));
		} //if $ra == 1

		if (empty($errormsg)) {
			$redirect = TRUE;
			$redirect_param_str = "action=timeprogram&subaction=list&sid=$sid";
		}
		else {
			$smarty->assign('errormsg', $errormsg);
			$smarty_view = 'messages.tpl';
		}
	}

?>
