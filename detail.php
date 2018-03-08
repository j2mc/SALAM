<?php

/* 
detail.php for SALAM v2
By Jacob McEntire
Copyright 2018
*/

require("library/SSI.php");

$jsdata = '';

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
	$min = round($seconds / 60);
	$out .= $min;
	if ($min > 1 || $min == 0)
		$out .= ' minutes';
	else
		$out .= ' minute';
	return $out;
}

function how_long($date) {
	$diff = time() - $date;
	if ($diff > 60)
		return round($diff / 60, 1) . ' minutes ago';
	else
		return $diff . ' seconds ago';
}

if (isset($_GET['type']) && isset($_GET['id'])) {
	$type = check_xss($_GET['type']);
	$id = check_xss($_GET['id']);
	if ($type == 'host') {
		$host_stmt = db_prepare("SELECT name, description, site_id, status, avg_rt, avg_count, lastcheck, uptime, downtime, warntime, last_rt FROM hosts WHERE id = ?");
		$alert_count_stmt = db_prepare("SELECT COUNT(id) FROM alerts WHERE type = ? AND type_id = ? AND level = ?");
		$host_data_stmt = db_prepare("SELECT type, data FROM hostdata WHERE host_id = ?");
		$service_stmt = db_prepare("SELECT id, name, type, port, status, last_rt FROM services WHERE host_id = ? AND enabled = ?");
		$sitename_stmt = db_prepare("SELECT name FROM sites WHERE id = ?");
		db_execute($host_stmt, array($id));
		$result = db_fetch($host_stmt, 'row');
		
		if (!empty($result)) {
			db_execute($sitename_stmt, array($result['site_id']));
			$sitename = db_fetch($sitename_stmt, 'col');
			db_execute($alert_count_stmt, array('host', $id, 1));
			$warn_count = db_fetch($alert_count_stmt, 'col');
			db_execute($alert_count_stmt, array('host', $id, 2));
			$critical_count = db_fetch($alert_count_stmt, 'col');
			db_execute($host_data_stmt, array($id));
			$host_data_result = db_fetch($host_data_stmt);
			db_execute($service_stmt, array($id, 1));
			$service_result = db_fetch($service_stmt);
			$totaltime = $result['uptime'] + $result['downtime'] + $result['warntime'];
			$up_percent = round($result['uptime'] / ($totaltime / 100), 3);
			$down_percent = round($result['downtime'] / ($totaltime / 100), 3);
			$warn_percent = round($result['warntime'] / ($totaltime / 100), 3);
			$checkdata_stmt = db_prepare("SELECT time, rtime FROM checkdata WHERE type = ? AND type_id = ? and time > ?");
			db_execute($checkdata_stmt, array('host', $id, time() - 86400));
			$checkdata = db_fetch($checkdata_stmt);
			if (!empty($checkdata)) {
				$jsdata = '[';
				foreach($checkdata as $d) {
					$jsdata .= '{ t: ' . $d['time'] * 1000 . ', y: ' . $d['rtime'] / 1000 . ' },';
				}
				$jsdata .= ']';
			}
			page_start($result['name'] . ' in <a href="detail.php?type=site&amp;id=' . $result['site_id'] . '">' . $sitename . '</a>', FALSE);
			echo '
				<div class="container-fluid">
					<div class="row">
						<div class="list-group col-md-6">
							<span class="list-group-item"><strong>Average Response Time</strong><small class="pull-right">', round(($result['avg_rt'] / $result['avg_count'])/1000, 3), 'ms</small></span>
							<span class="list-group-item"><strong>Last Response Time</strong><small class="pull-right">', $result['last_rt'] / 1000, 'ms</small></span>
							<span class="list-group-item"><strong>Last Checked</strong><small class="pull-right">', how_long($result['lastcheck']), '</small></span>
							<span class="list-group-item"><strong>Uptime</strong><small class="pull-right">', convert_seconds($result['uptime']), ' | ', $up_percent, '%</small></span>
							<span class="list-group-item"><strong>Downtime</strong><small class="pull-right">', convert_seconds($result['downtime']), ' | ', $down_percent, '%</small></span>
							<span class="list-group-item"><strong>Warntime</strong><small class="pull-right">', convert_seconds($result['warntime']), ' | ', $warn_percent, '%</small></span>
							<a href="alerts.php?type=host&amp;level=2&amp;id=', $id, '" class="list-group-item list-group-item-danger"><strong>Critical Alerts</strong><small class="pull-right">', $critical_count, '</small></a>
							<a href="alerts.php?type=host&amp;level=1&amp;id=', $id, '" class="list-group-item list-group-item-warning"><strong>Warning Alerts</strong><small class="pull-right">', $warn_count, '</small></a>
						</div>';
						if (!empty($service_result)) {
							echo '
						<div class="list-group col-md-6">
							<span class="list-group-item"><strong>Services</strong></span>';
							foreach ($service_result as $service) {
								$badge = '<small class="pull-right">' .  $service['last_rt'] / 1000 . 'ms</small>';
								if ($service['status'] == 2)
									$badge = '<span class="glyphicon glyphicon-warning-sign pull-right" style="font-size:1.4em"></span>';
								echo '<a href="detail.php?type=service&amp;id=', $service['id'], '" class="list-group-item list-group-item-', status_class($service['status']), '"><strong>', $service['name'], '</strong> <small>', $service['type'], ' ', $service['port'], '</small>', $badge, '</a>';
							}
							echo '
						</div>';
						}
						echo '
						<div class="list-group col-md-6">
							<span class="list-group-item"><strong>Description</strong><small class="pull-right">', $result['description'], '</small></span>';
						foreach ($host_data_result as $host_data)
							echo '<span class="list-group-item"><strong>', $host_data['type'], '</strong><small class="pull-right">', $host_data['data'], '</small></span>';
						echo '
						</div>
						<div class="col-md-6">
							<div class="panel panel-default">
								<canvas id="twentyfourchart"></canvas>
							</div>
						</div>
					</div>
				</div>';
		}
	}
	elseif ($type == 'service') {
		$alert_count_stmt = db_prepare("SELECT COUNT(id) FROM alerts WHERE type = ? AND type_id = ? AND level = ?");
		$service_stmt = db_prepare("SELECT host_id, name, type, port, status, avg_rt, avg_count, lastcheck, uptime, downtime, warntime, last_rt FROM services WHERE id = ?");
		$hostname_stmt = db_prepare("SELECT name FROM hosts WHERE id = ?");
		db_execute($service_stmt, array($id));
		$result = db_fetch($service_stmt, 'row');
		
		if (!empty($result)) {
			db_execute($hostname_stmt, array($result['host_id']));
			$hostname = db_fetch($hostname_stmt, 'col');
			db_execute($alert_count_stmt, array('service', $id, 1));
			$warn_count = db_fetch($alert_count_stmt, 'col');
			db_execute($alert_count_stmt, array('service', $id, 2));
			$critical_count = db_fetch($alert_count_stmt, 'col');
			$totaltime = $result['uptime'] + $result['downtime'] + $result['warntime'];
			$up_percent = round($result['uptime'] / ($totaltime / 100), 3);
			$down_percent = round($result['downtime'] / ($totaltime / 100), 3);
			$warn_percent = round($result['warntime'] / ($totaltime / 100), 3);
			$checkdata_stmt = db_prepare("SELECT time, rtime FROM checkdata WHERE type = ? AND type_id = ? and time > ?");
			db_execute($checkdata_stmt, array('service', $id, time() - 86400));
			$checkdata = db_fetch($checkdata_stmt);
			if (!empty($checkdata)) {
				$jsdata = '[';
				foreach($checkdata as $d) {
					$jsdata .= '{ t: ' . $d['time'] * 1000 . ', y: ' . $d['rtime'] / 1000 . ' },';
				}
				$jsdata .= ']';
			}
			page_start($result['name'] . ' on <a href="detail.php?type=host&amp;id=' . $result['host_id'] . '">' . $hostname . '</a>', FALSE);
			echo '
				<div class="container-fluid">
					<div class="row">
						<div class="list-group col-md-6">
							<span class="list-group-item"><strong>Protocol / Port</strong><small class="pull-right">', $result['type'], ' ', $result['port'], '</small></span>
							<span class="list-group-item"><strong>Average Response Time</strong><small class="pull-right">', round(($result['avg_rt'] / $result['avg_count'])/1000, 3), 'ms</small></span>
							<span class="list-group-item"><strong>Last Response Time</strong><small class="pull-right">', $result['last_rt'] / 1000, 'ms</small></span>
							<span class="list-group-item"><strong>Last Checked</strong><small class="pull-right">', how_long($result['lastcheck']), '</small></span>
							<span class="list-group-item"><strong>Uptime</strong><small class="pull-right">', convert_seconds($result['uptime']), ' | ', $up_percent, '%</small></span>
							<span class="list-group-item"><strong>Downtime</strong><small class="pull-right">', convert_seconds($result['downtime']), ' | ', $down_percent, '%</small></span>
							<span class="list-group-item"><strong>Warntime</strong><small class="pull-right">', convert_seconds($result['warntime']), ' | ', $warn_percent, '%</small></span>
							<a href="alerts.php?type=service&amp;level=2&amp;id=', $id, '" class="list-group-item list-group-item-danger"><strong>Critical Alerts</strong><small class="pull-right">', $critical_count, '</small></a>
							<a href="alerts.php?type=service&amp;level=1&amp;id=', $id, '" class="list-group-item list-group-item-warning"><strong>Warning Alerts</strong><small class="pull-right">', $warn_count, '</small></a>
						</div>
						<div class="col-md-6">
							<div class="panel panel-default">
								<canvas id="twentyfourchart"></canvas>
							</div>
						</div>
					</div>
				</div>';
		}
	}
	elseif ($type == 'site') {
		$site_stmt = db_prepare("SELECT name, subnet, hostmethod, status, lastcheck, uptime, downtime FROM sites WHERE id = ?");
		$alert_count_stmt = db_prepare("SELECT COUNT(id) FROM alerts WHERE type = ? AND type_id = ? AND level = ?");
		$host_stmt = db_prepare("SELECT id, name, description, status, last_rt FROM hosts WHERE enabled = ? AND site_id = ?");
		$alert_stmt = db_prepare("SELECT COUNT(id), MAX(level) FROM alerts WHERE type = 'service' AND active = 1 AND type_id IN (SELECT services.id FROM services WHERE host_id = ?)");
		
		db_execute($site_stmt, array($id));
		$result = db_fetch($site_stmt, 'row');
		
		if (!empty($result)) {
			db_execute($alert_count_stmt, array('site', $id, 2));
			$critical_count = db_fetch($alert_count_stmt, 'col');
			
			db_execute($host_stmt, array(1, $id));
			$host_result = db_fetch($host_stmt);
			
			$totaltime = $result['uptime'] + $result['downtime'];
			$up_percent = round($result['uptime'] / ($totaltime / 100), 3);
			$down_percent = round($result['downtime'] / ($totaltime / 100), 3);
			page_start($result['name'], FALSE);
			echo '
				<div class="container-fluid">
					<div class="row">
						<div class="list-group col-md-6">
							<span class="list-group-item"><strong>Subnet or IP Ranges</strong><small class="pull-right">', $result['subnet'], '</small></span>
							<span class="list-group-item"><strong>Last Checked</strong><small class="pull-right">', how_long($result['lastcheck']), '</small></span>
							<span class="list-group-item"><strong>Uptime</strong><small class="pull-right">', convert_seconds($result['uptime']), ' | ', $up_percent, '%</small></span>
							<span class="list-group-item"><strong>Downtime</strong><small class="pull-right">', convert_seconds($result['downtime']), ' | ', $down_percent, '%</small></span>
							<a href="alerts.php?type=site&amp;level=2&amp;id=', $id, '" class="list-group-item list-group-item-danger"><strong>Critical Alerts</strong><small class="pull-right">', $critical_count, '</small></a>
						</div>';
						if(!empty($host_result)) {
							echo '<div class="list-group col-md-6">
								<span class="list-group-item"><strong>Hosts</strong></span>';
									foreach ($host_result as $host) {
										$badge = '<small class="pull-right">' .  $host['last_rt'] / 1000 . 'ms</small>';
										if ($host['status'] == 0) { //Host is up, see if there are service alerts
											db_execute($alert_stmt, array($host['id']));
											$alerts = db_fetch($alert_stmt, 'row');
											if ($alerts[0] > 0) {
												$badge = '<span class="badge">' . $alerts[0] . '</span>';
												$host['status'] = $alerts[1];
											}									
										}
										else
											$badge = '<span class="glyphicon glyphicon-warning-sign pull-right" style="font-size:1.4em"></span>';
										echo '<a href="detail.php?type=host&amp;id=', $host['id'], '" class="list-group-item list-group-item-', status_class($host['status']), '"><strong>', $host['name'], '</strong> <small>', $host['description'], '</small>', $badge, '</a>';
									}
							echo '</div>';
						}
						echo '
					</div>
				</div>';
		}
	}
}

$endscript = '';
if (!empty($jsdata)) {
	$endscript = "
		<script src='js/chart.bundle.min.js'></script>
		<script>
			var ctx = $('#twentyfourchart');
			var chart = new Chart(ctx, {
				type: 'line',
				data: {
					datasets: [{
						borderColor: 'red',
						borderWidth: 1,
						pointRadius: 0,
						data: $jsdata,
					}]
				},
				options: {
					title: {
						display: true,
						text: 'Response Time Last 24 Hours'
					},
					scales: {
						xAxes: [{
							type: 'time',
							display: true,
						}],
						yAxes: [{
							display: true,
							scaleLabel: {
								display: true,
								labelString: 'ms'
							}
						}]
					},
					legend: {
						display: false
					},
					elements: {
						line: {
							tension: 0
						}
					}
				}
			});
		</script>";
}
	
page_end($endscript);

?>