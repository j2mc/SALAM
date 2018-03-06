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
	
	if ($type == 'site')
		$name_stmt = db_prepare("SELECT name FROM sites WHERE id = ?");
	elseif ($type == 'host')
		$name_stmt = db_prepare("SELECT name FROM hosts WHERE id = ?");
	elseif ($type == 'service') {
		$name_stmt = db_prepare("SELECT name FROM services WHERE id = ?");
		$host_stmt = db_prepare("SELECT hosts.id, hosts.name FROM hosts JOIN services on hosts.id = services.host_id WHERE services.id = ?");
		db_execute($host_stmt, array($id));
		$host_result = db_fetch($host_stmt, 'row');
	}
	db_execute($name_stmt, array($id));
	$name = '<a href="detail.php?type=' . $type . '&amp;id=' . $id . '">' . db_fetch($name_stmt, 'col') . '</a>';
	if (isset($host_result))
		$name .= ' on <a href="detail.php?type=host&amp;id=' . $host_result['id'] . '">' . $host_result['name'] . '</a>';
	
	if ($level == 1)
		$levelname = 'Warning';
	else
		$levelname = 'Critical';
	
	page_start($name . ' ' . $levelname . ' Alerts', FALSE);
	
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
	
	
}
	
page_end();

?>