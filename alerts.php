<?php

/* 
alerts.php for SALAM v2
By Jacob McEntire
Copyright 2018
*/

require("library/SSI.php");

function status_class($status) {
	switch ($status) {
		case 0:
			return 'success';
			break;
		case 1:
			return 'warning';
			break;
		case 2:
			return 'danger';
			break;
	}
}

function convert_seconds($seconds) {
	$out = '';
	if ($seconds > 86400) {
		$days = floor($seconds / 86400);
		$seconds = $seconds % 86400;
		$out = $days;
		if ($days > 1)
			$out .= ' days, ';
		else
			$out .= ' day, ';
	}
	if ($seconds > 3600) {
		$hours = floor($seconds / 3600);
		$seconds = $seconds % 3600;
		$out .= $hours;
	if ($hours > 1 || $hours == 0)
			$out .= ' hours, ';
		else
			$out .= ' hour, ';
	}
	if ($seconds > 60) {
		$min = floor($seconds / 60);
		$seconds = $seconds % 60;
		$out .= $min;
		if ($min > 1 || $min == 0)
			$out .= ' minutes, ';
		else
			$out .= ' minute, ';
	}
	$out .= $seconds;
	if ($seconds > 1 || $seconds == 0)
		$out .= ' seconds';
	else
		$out .= ' second';
	return $out;
}

if (isset($_GET['type']) && isset($_GET['level']) && isset($_GET['id'])) {
	$type = check_xss($_GET['type']);
	$level = check_xss($_GET['level']);
	$id = check_xss($_GET['id']);
	
	if ($level == 1)
		$levelname = 'Warning';
	else
		$levelname = 'Critical';
	
	if ($type == 'site') {
		$name_stmt = db_prepare("SELECT name FROM sites WHERE id = ?");
		db_execute($name_stmt, array($id));
		$breadcrumb = array(
			"Dashboard" => "/",
			db_fetch($name_stmt, 'col') => 'detail.php?type=site&amp;id=' . $id,
			"active" => $levelname . ' Alerts'
		);
	}
	elseif ($type == 'host') {
		$name_stmt = db_prepare("SELECT hosts.name AS host_name, sites.id AS site_id, sites.name AS site_name FROM hosts, sites WHERE hosts.id = ? AND hosts.site_id = sites.id");
		db_execute($name_stmt, array($id));
		$result = db_fetch($name_stmt, 'row');
		$breadcrumb = array(
			"Dashboard" => "/",
			$result['site_name'] => 'detail.php?type=site&amp;id=' . $result['site_id'],
			$result['host_name'] => 'detail.php?type=host&amp;id=' . $id,
			"active" => $levelname . ' Alerts'
		);
	}
	elseif ($type == 'service') {
		$name_stmt = db_prepare("SELECT services.name, hosts.id AS host_id, hosts.name AS host_name, sites.id AS site_id, sites.name AS site_name FROM services, hosts, sites WHERE services.id = ? AND services.host_id = hosts.id AND hosts.site_id = sites.id");
		db_execute($name_stmt, array($id));
		$result = db_fetch($name_stmt, 'row');
		$breadcrumb = array(
			"Dashboard" => "/",
			$result['site_name'] => 'detail.php?type=site&amp;id=' . $result['site_id'],
			$result['host_name'] => 'detail.php?type=host&amp;id=' . $result['host_id'],
			$result['name'] => 'detail.php?type=service&amp;id=' . $id,
			"active" => $levelname . ' Alerts'
		);
	}
	
	page_start($breadcrumb, FALSE);
	
	$alert_stmt = db_prepare("SELECT active, start, lastcheck FROM alerts WHERE type = ? AND type_id = ? AND level = ? ORDER BY lastcheck DESC");
	db_execute($alert_stmt, array($type, $id, $level));
	$result = db_fetch($alert_stmt);
	if (!empty($result)) {
		echo '
		<table class="table table-striped">
			<thead>
			<tr><th>Start Time</th><th>End Time</th><th>Duration</th></tr>
			</thead>
			<tbody>';
		foreach ($result as $alert) {
			echo '
			<tr><td>', date("r", $alert['start']), '</td><td>', date("r", $alert['lastcheck']), '</td><td>', convert_seconds($alert['lastcheck'] - $alert['start']), '</td></tr>';
		}
		echo '</tbody></table>';
	}
	else
		echo '<h2 class="text-center">No Alerts Found</h2>';
	
	
}
	
page_end();

?>