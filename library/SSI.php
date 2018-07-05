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
	global $nmappath, $use_sudo, $use_privileged;
	if (!file_exists($nmappath))
		die("Nmap Not Found");
	if ($use_privileged)
		$cmd = '--privileged ' . $cmd;
	if ($use_sudo)
		$fullcmd = 'sudo ' . $nmappath . ' ' . $cmd;
	else
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

function page_start($breadcrumb, $tv = FALSE, $script = '', $column_count = 2) {
	$pagetitle = $breadcrumb['active'];
	echo '
	<!DOCTYPE html>
	<html lang="en">
	<head>
	<meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
	<title>', $pagetitle, ' - SALAM</title>
	<link rel="stylesheet" href="css/bootstrap.min.css" />
	<link rel="stylesheet" href="css/open-iconic-bootstrap.min.css" />';
	if ($pagetitle == 'Dashboard') {
		echo '
	<style>
	@media (min-width: 576px) {
		.card-columns {
			column-count: 1;
		}
	}
	@media (min-width: 768px) {
		.card-columns {
			column-count: 2;
		}
	}
	@media (min-width: 992px) {
		.card-columns {
			column-count: ', $column_count, ';
		}
	}
	</style>';
	}
	echo 
	$script, '	
	</head>
	<body>
	<nav class="navbar navbar-expand-md navbar-dark bg-dark">
      <a class="navbar-brand" href="/">SALAM</a>
      <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#mainNavBar" aria-controls="mainNavBar" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="mainNavBar">
        <ul class="navbar-nav ml-auto">
          <li class="nav-item">
            <a class="nav-link" href="\">Dashboard</a>
          </li>
		  <li class="nav=item">
			<a class="nav-link" href="settings.php"><span class="oi oi-cog" style="top:2px" title="Settings" aria-hidden="true"></span></a>
		  </li>
        </ul>
      </div>
    </nav>
    <main role="main" class="container-fluid">';
	//if (!$tv)
	echo '
		<nav aria-label="breadcrumb">
			<ol class="breadcrumb">';
	foreach ($breadcrumb as $name => $url) {
		if ($name != 'active')
			echo '<li class="breadcrumb-item"><a href="', $url, '">', $name, '</a></li>';
		else
			echo '<li class="breadcrumb-item active" aria-current="page">', $url, '</li>';
	}
	echo '
			</ol>
		</nav>';
	if (!empty($_SESSION['alert'])) {
		$alert = $_SESSION['alert'];
		unset($_SESSION['alert']);
		if (!empty($_SESSION['alert_type'])) {
			$alerttype = $_SESSION['alert_type'];
			unset($_SESSION['alert_type']);
		}
		else
			$alerttype = 'info';
		echo '<div class="alert alert-', $alerttype, ' alert-dismissible fade show" role="alert">', $alert, '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>';
	}
}

function page_end($endscript = '') {
	echo '
	</main>
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
