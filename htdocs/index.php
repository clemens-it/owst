<?php
	error_reporting(E_ALL);
	ini_set('display_errors', 1);
	require_once 'smarty3/Smarty.class.php';
	//define variable with filename of timer program to pass to config.php
	$cfg_executable = "/opt/owst/tp.php";
	require_once 'include/config.php';
	require_once 'include/log.php';
	require_once 'include/functions.php';
	require_once 'include/lock.php';
	//OWphp extension must be loaded in php.ini

	$log_level = LLDEBUG;
	//$log_level = LLINFO;
	/*
	// locale
	$charset = "UTF-8";
	mb_internal_encoding($charset);
	setlocale(LC_ALL, $charset);
	*/


	$smarty = new Smarty();
	$smarty->setTemplateDir('smarty/templates');
	$smarty->setCompileDir('smarty/templates_c');
	$smarty->setConfigDir('smarty/config');
	$smarty->setCacheDir('smarty/cache');
	$smarty->caching = FALSE;
	$smarty->assign('scriptname', $_SERVER['SCRIPT_NAME']);

	$redirect = FALSE;
	$redirect_param_str = '';
	$smarty_view = '';
	try {
		$dbh = new PDO($cfg['dsn'], $cfg['dbuser'], $cfg['dbpass']);
	}
	catch (PDOException $e) {
		die('Connection to database failed: ' . $e->getMessage());
	}
	catch (Exception $e) {
		die("Error: {$e->getMessage()}");
	}

	//if action is not defined, set default action
	if (!isset($_REQUEST['action'])) {
		$_REQUEST['action'] = 'switch';
		$_REQUEST['subaction'] = 'list';
	}

	if ($_REQUEST['action'] == 'switch' && isset($_REQUEST['subaction']))
		require_once 'include/wi_switch.php';

	if ($_REQUEST['action'] == 'timeprogram' && isset($_REQUEST['subaction']))
		require_once 'include/wi_timeprogram.php';

	if ($_REQUEST['action'] == 'log' && isset($_REQUEST['subaction']))
		require_once 'include/wi_log.php';

	if ($_REQUEST['action'] == 'at' && isset($_REQUEST['subaction']))
		require_once 'include/wi_at.php';

	if ($redirect) {
		header("Location: {$_SERVER['SCRIPT_NAME']}?$redirect_param_str");
	}
	else {
		$smarty->display('header.tpl');
		foreach(explode(';', $smarty_view) as $v)
			if (!empty($v))
				$smarty->display($v);
		$smarty->display('footer.tpl');
	}
