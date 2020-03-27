<?php
	if ($_REQUEST['subaction'] == 'showqueue') {
		$output = array();
		unset($retval);
		$lloutput = exec($cfg['atq_cmd_line'], $output, $retval);
		if ($retval != 0) 
			$smarty->assign('errormsg', "Failed to retrieve AT queue. Return value: $retval.\n");
		$smarty->assign('data', implode("\n", $output));
		$smarty_view = 'at.tpl';
	}
