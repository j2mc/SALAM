<?php
/*
-----------------------------------
SSI.php for SALAM v2
By Jacob McEntire
Copyright 2014
------------------------------------
*/
$lifetime = 60*60*24*365;
session_set_cookie_params($lifetime);

session_start();

extract(parse_ini_file(dirname(__FILE__) . '/settings.ini'));

date_default_timezone_set($time_zone);

//Configuration and Connection to DB:
$dsn = "mysql:host=$dbhost;dbname=$db";
$opt = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
	$dbh = new PDO($dsn, $dbuser, $dbpass, $opt);
}
catch (Exception $e) {
	echo '<html><head><title>SALAM - Error</title></head><body>
	<h2>For Initial Setup <a href="setup.php">Click Here</a></h2>
	<p>If setup has already been completed there seems to be a problem with SQL: <strong>', $e->getMessage(), '</strong>
	</body></html>';
	exit(1);
}

function db_prepare($query) {
	global $dbh;
	$db_stmt = $dbh->prepare($query);
	return $db_stmt;
}

function db_execute($db_stmt, $values = FALSE) {
	global $dbh;
	if (!$values)
		$result = $db_stmt->execute();
	else
		$result = $db_stmt->execute($values);
	$results = array(
		"result" => $result,
		"count" => $db_stmt->rowCount(),
		"id" => $dbh->lastInsertId(),
		);
	return $results;
}

function db_fetch($db_stmt, $type = 'all') {
	if ($type == 'all')
		$result = $db_stmt->fetchAll(PDO::FETCH_BOTH);
	elseif ($type == 'row')
		$result = $db_stmt->fetch(PDO::FETCH_BOTH);
	elseif ($type == 'col')
		$result = $db_stmt->fetchColumn();
	$db_stmt->closeCursor();
    return $result;
}

function nmap_cmd($cmd) {
	global $nmappath, $use_sudo;
	if (!file_exists($nmappath))
		die("Nmap Not Found");
	$fullcmd = $nmappath . ' ' . $cmd;
	$output = shell_exec($fullcmd);
	$xmloutput = new SimpleXMLElement($output);
	return $xmloutput;
}

function menu_item($page, $name) {
    if (trim($_SERVER["PHP_SELF"], "/") == $page)
    	echo '<li class="active"><a href="', $page, '" >', $name, '</a></li>';
    else
    	echo '<li><a href="', $page, '" >', $name, '</a></li>';
}

function page_start($pagename, $sidebar = FALSE, $script = '') {
	$pagetitle = strip_tags($pagename);
	echo '
	<!DOCTYPE html>
	<html lang="en">
	<head>
	<meta http-equiv="content-type" content="text/html; charset=UTF-8">
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
	<title>', $pagetitle, ' - SALAM</title>
	<link rel="stylesheet" href="css/bootstrap.min.css" />
	<link rel="stylesheet" href="css/dashboard.css" />
	', $script, '	
	</head>
	<body>
	<div class="navbar navbar-inverse navbar-fixed-top" role="navigation">
      <div class="container-fluid">
        <div class="navbar-header">
          <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-collapse">
            <span class="sr-only">Toggle navigation</span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
          </button>
          <a class="navbar-brand" href="#">SALAM</a>
        </div>
        <div class="navbar-collapse collapse">
          <ul class="nav navbar-nav navbar-right">
            <li><a href="index.php">Dashboard</a></li>
            <li><a href="settings.php">Settings</a></li>
            <li class="disabled"><a href="help.php">Help</a></li>
          </ul>
          <form class="navbar-form navbar-right">
            <input class="form-control" disabled="disabled" placeholder="Search..." type="text">
          </form>
        </div>
      </div>
    </div>
    <div class="container-fluid">
      <div class="row">';
		if (!$sidebar)
			echo '<div class="main">';
		else
		{
			echo '
			<div class="col-sm-3 col-md-2 sidebar">
			  <ul class="nav nav-sidebar">
				<li class="active"><a href="#">Overview</a></li>
			  </ul>
			</div>
			<div class="col-sm-9 col-sm-offset-3 col-md-10 col-md-offset-2 main">';
		}
		echo '
		<h1 class="page-header">', $pagename, '</h1>';
	if (!empty($_SESSION['alert'])) {
		$alert = $_SESSION['alert'];
		unset($_SESSION['alert']);
		if (!empty($_SESSION['alert_type'])) {
			$alerttype = $_SESSION['alert_type'];
			unset($_SESSION['alert_type']);
		}
		else
			$alerttype = 'info';
		echo '<div class="alert alert-', $alerttype, '"><button type="button" class="close"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>', $alert, '</div>';
	}
}

function page_end($endscript = '') {
	echo '
			</div>
		</div>
	</div>
	<script src="js/jquery-2.1.4.min.js" type="text/javascript"></script>
	<script src="js/bootstrap.min.js" type="text/javascript"></script>
	';
	echo $endscript;
	echo '
	</body>
	</html>';
}

function check_xss($input) {
	//$input = utf8_decode($input);
	$input = htmlentities($input, ENT_QUOTES);
	if(!get_magic_quotes_gpc())
		$input  = addslashes($input);
	return $input;
}

function checked($a) {
	if ($a)
		return 'checked="checked"';
}

function redirect($url, $alert = '', $alert_type = 'info') {
	$_SESSION['alert'] = $alert;
	$_SESSION['alert_type'] = $alert_type;
	header("Location: $url");
}
?>