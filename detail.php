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
		$host_stmt = db_prepare("SELECT hosts.name, hosts.description, hosts.status, hosts.avg_rt, hosts.avg_count, hosts.lastcheck, hosts.uptime, hosts.downtime, hosts.warntime, hosts.last_rt, sites.id AS site_id, sites.name AS site_name FROM hosts, sites WHERE hosts.site_id = sites.id AND hosts.id = ?");
		$alert_count_stmt = db_prepare("SELECT COUNT(id) FROM alerts WHERE type = ? AND type_id = ? AND level = ?");
		$host_data_stmt = db_prepare("SELECT type, data FROM hostdata WHERE host_id = ?");
		$service_stmt = db_prepare("SELECT id, name, type, port, status, last_rt FROM services WHERE host_id = ? AND enabled = ?");
		db_execute($host_stmt, array($id));
		$result = db_fetch($host_stmt, 'row');
		
		if (!empty($result)) {
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
			$breadcrumb = array(
				"Dashboard" => "/",
				$result['site_name'] => 'detail.php?type=site&amp;id=' . $result['site_id'],
				"active" => $result['name']
			);
			page_start($breadcrumb, FALSE);
			echo '
				<div class="row">
					<div class="col-md-6">
						<div class="card mb-3">
							<div class="list-group list-group-flush">
								<span class="list-group-item"><strong>Average Response Time</strong><small class="float-right">', round(($result['avg_rt'] / $result['avg_count'])/1000, 3), 'ms</small></span>
								<span class="list-group-item"><strong>Last Response Time</strong><small class="float-right">', $result['last_rt'] / 1000, 'ms</small></span>
								<span class="list-group-item"><strong>Last Checked</strong><small class="float-right">', how_long($result['lastcheck']), '</small></span>
								<span class="list-group-item"><strong>Uptime</strong><small class="float-right">', convert_seconds($result['uptime']), ' | ', $up_percent, '%</small></span>
								<span class="list-group-item"><strong>Downtime</strong><small class="float-right">', convert_seconds($result['downtime']), ' | ', $down_percent, '%</small></span>
								<span class="list-group-item"><strong>Warntime</strong><small class="float-right">', convert_seconds($result['warntime']), ' | ', $warn_percent, '%</small></span>
								<a href="alerts.php?type=host&amp;level=2&amp;id=', $id, '" class="list-group-item list-group-item-danger"><strong>Critical Alerts</strong><small class="float-right">', $critical_count, '</small></a>
								<a href="alerts.php?type=host&amp;level=1&amp;id=', $id, '" class="list-group-item list-group-item-warning"><strong>Warning Alerts</strong><small class="float-right">', $warn_count, '</small></a>
							</div>
						</div>';
						if (!empty($service_result)) {
							echo '
							<div class="card">
								<div class="list-group list-group-flush">
									<span class="list-group-item"><strong>Services</strong></span>';
									foreach ($service_result as $service) {
										$badge = '<small class="float-right">' .  $service['last_rt'] / 1000 . 'ms</small>';
										if ($service['status'] == 2)
											$badge = '<span class="glyphicon glyphicon-warning-sign float-right" style="font-size:1.4em"></span>';
										echo '<a href="detail.php?type=service&amp;id=', $service['id'], '" class="list-group-item list-group-item-', status_class($service['status']), '"><strong>', $service['name'], '</strong> <small>', $service['type'], ' ', $service['port'], '</small>', $badge, '</a>';
									}
									echo '
								</div>
							</div>';
						}
					echo '
					</div>
					<div class="col-md-6">
						<div class="card mb-3">
							<div class="card-header">
								<ul class="nav nav-tabs card-header-tabs nav-justified">
									<li class="nav-item">
										<a class="nav-link active graphtab" href="#" id="twentyfour" data-range="1">24 Hours</a>
									</li>
									<li class="nav-item">
										<a class="nav-link graphtab" href="#" id="seven" data-range="7">7 Days</a>
									</li>
									<li class="nav-item">
										<a class="nav-link graphtab" href="#" id="thirty" data-range="30">30 Days</a>
									</li>
									<li class="nav-item">
										<a class="nav-link graphtab" href="#" id="year" data-range="365">1 Year</a>
									</li>
								</ul>
							</div>
							<canvas id="historychart"></canvas>
						</div>
						<div class="card">
							<div class="list-group list-group-flush">
								<span class="list-group-item"><strong>Description</strong><small class="float-right">', $result['description'], '</small></span>';
							foreach ($host_data_result as $host_data)
								echo '<span class="list-group-item"><strong>', $host_data['type'], '</strong><small class="float-right">', $host_data['data'], '</small></span>';
							echo '
							</div>
						</div>
					</div>
				</div>';
		}
	}
	elseif ($type == 'service') {
		$alert_count_stmt = db_prepare("SELECT COUNT(id) FROM alerts WHERE type = ? AND type_id = ? AND level = ?");
		$service_stmt = db_prepare("SELECT hosts.id AS host_id, hosts.name AS host_name, sites.id AS site_id, sites.name AS site_name, services.name, services.type, services.port, services.status, services.avg_rt, services.avg_count, services.lastcheck, services.uptime, services.downtime, services.warntime, services.last_rt FROM services, hosts, sites WHERE services.host_id = hosts.id AND hosts.site_id = sites.id AND services.id = ?");
		db_execute($service_stmt, array($id));
		$result = db_fetch($service_stmt, 'row');
		if (!empty($result)) {
			db_execute($alert_count_stmt, array('service', $id, 1));
			$warn_count = db_fetch($alert_count_stmt, 'col');
			db_execute($alert_count_stmt, array('service', $id, 2));
			$critical_count = db_fetch($alert_count_stmt, 'col');
			$totaltime = $result['uptime'] + $result['downtime'] + $result['warntime'];
			$up_percent = round($result['uptime'] / ($totaltime / 100), 3);
			$down_percent = round($result['downtime'] / ($totaltime / 100), 3);
			$warn_percent = round($result['warntime'] / ($totaltime / 100), 3);
			$breadcrumb = array(
				"Dashboard" => "/",
				$result['site_name'] => 'detail.php?type=site&amp;id=' . $result['site_id'],
				$result['host_name'] => 'detail.php?type=host&amp;id=' . $result['host_id'],
				"active" => $result['name']
			);
			page_start($breadcrumb, FALSE);
			echo '
				<div class="row">
					<div class="col-md-6">
						<div class="card">
							<div class="list-group list-group-flush">
								<span class="list-group-item"><strong>Protocol / Port</strong><small class="float-right">', $result['type'], ' ', $result['port'], '</small></span>
								<span class="list-group-item"><strong>Average Response Time</strong><small class="float-right">', round(($result['avg_rt'] / $result['avg_count'])/1000, 3), 'ms</small></span>
								<span class="list-group-item"><strong>Last Response Time</strong><small class="float-right">', $result['last_rt'] / 1000, 'ms</small></span>
								<span class="list-group-item"><strong>Last Checked</strong><small class="float-right">', how_long($result['lastcheck']), '</small></span>
								<span class="list-group-item"><strong>Uptime</strong><small class="float-right">', convert_seconds($result['uptime']), ' | ', $up_percent, '%</small></span>
								<span class="list-group-item"><strong>Downtime</strong><small class="float-right">', convert_seconds($result['downtime']), ' | ', $down_percent, '%</small></span>
								<span class="list-group-item"><strong>Warntime</strong><small class="float-right">', convert_seconds($result['warntime']), ' | ', $warn_percent, '%</small></span>
								<a href="alerts.php?type=service&amp;level=2&amp;id=', $id, '" class="list-group-item list-group-item-danger"><strong>Critical Alerts</strong><small class="float-right">', $critical_count, '</small></a>
								<a href="alerts.php?type=service&amp;level=1&amp;id=', $id, '" class="list-group-item list-group-item-warning"><strong>Warning Alerts</strong><small class="float-right">', $warn_count, '</small></a>
							</div>
						</div>
					</div>
					<div class="col-md-6">
						<div class="card">
							<div class="card-header">
								<ul class="nav nav-tabs card-header-tabs nav-justified">
									<li class="nav-item">
										<a class="nav-link active graphtab" href="#" id="twentyfour" data-range="1">24 Hours</a>
									</li>
									<li class="nav-item">
										<a class="nav-link graphtab" href="#" id="seven" data-range="7">7 Days</a>
									</li>
									<li class="nav-item">
										<a class="nav-link graphtab" href="#" id="thirty" data-range="30">30 Days</a>
									</li>
									<li class="nav-item">
										<a class="nav-link graphtab" href="#" id="year" data-range="365">1 Year</a>
									</li>
								</ul>
							</div>
							<canvas id="historychart"></canvas>
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
			$jsuptimedata = "[$up_percent, $down_percent]";
			$breadcrumb = array(
				"Dashboard" => "/",
				"active" => $result['name']
			);
			page_start($breadcrumb, FALSE);
			echo '
				<div class="row">
					<div class="col-md-6">
						<div class="card mb-3">
							<div class="list-group list-group-flush">
								<span class="list-group-item"><strong>Subnet or IP Ranges</strong><small class="float-right">', $result['subnet'], '</small></span>
								<span class="list-group-item"><strong>Last Checked</strong><small class="float-right">', how_long($result['lastcheck']), '</small></span>
								<span class="list-group-item"><strong>Uptime</strong><small class="float-right">', convert_seconds($result['uptime']), ' | ', $up_percent, '%</small></span>
								<span class="list-group-item"><strong>Downtime</strong><small class="float-right">', convert_seconds($result['downtime']), ' | ', $down_percent, '%</small></span>
								<a href="alerts.php?type=site&amp;level=2&amp;id=', $id, '" class="list-group-item list-group-item-danger"><strong>Critical Alerts</strong><small class="float-right">', $critical_count, '</small></a>
							</div>
						</div>
						<div class="card">
							<canvas id="uptimechart" height="100px"></canvas>
						</div>
					</div>
					<div class="col-md-6">';
						if(!empty($host_result)) {
							echo '
							<div class="card">
							  <div class="list-group list-group-flush">
								<span class="list-group-item"><strong>Hosts</strong></span>';
									foreach ($host_result as $host) {
										$badge = '<small class="float-right">' .  $host['last_rt'] / 1000 . 'ms</small>';
										if ($host['status'] == 0) { //Host is up, see if there are service alerts
											db_execute($alert_stmt, array($host['id']));
											$alerts = db_fetch($alert_stmt, 'row');
											if ($alerts[0] > 0) {
												$badge = '<span class="badge">' . $alerts[0] . '</span>';
												$host['status'] = $alerts[1];
											}									
										}
										else
											$badge = '<span class="glyphicon glyphicon-warning-sign float-right" style="font-size:1.4em"></span>';
										echo '<a href="detail.php?type=host&amp;id=', $host['id'], '" class="list-group-item list-group-item-', status_class($host['status']), '"><strong>', $host['name'], '</strong> <small>', $host['description'], '</small>', $badge, '</a>';
									}
							echo '
							  </div>
							</div>';
						}
						echo '
					</div>
				</div>';
		}
	}
}

$endscript = "<script src='js/chart.bundle.min.js'></script>";
if ($type == 'host' || $type == 'service') {
	$endscript .= "
		<script>
			var ctx = $('#historychart');
			var historychart;
			$( '.graphtab' ).click(function(e) {
			  e.preventDefault();
			  $( '.graphtab' ).not(this).removeClass('active');
			  $( this ).addClass('active');
			  var range = $( this ).data('range');
			  $.get('chartdata.php?type=$type&id=$id&range=' + range, function ( chartdata ) {
				    historychart.destroy();
					historychart = new Chart(ctx, chartdata);
				}, 'json' );
			});
			$.get('chartdata.php?type=$type&id=$id&range=1', function ( chartdata ) {
				historychart = new Chart(ctx, chartdata);
			}, 'json' );
		</script>";
}
if (!empty($jsuptimedata)) {
	$endscript .= "
		<script>
			var ctx = $('#uptimechart');
			var uptimechart = new Chart(ctx, {
				type: 'doughnut',
				data: {
					datasets: [{
						data: $jsuptimedata,
						backgroundColor: [
							'#5cb85c',
							'#d9534f',
						]
					}],
					labels: [
						'Up',
						'Down'
					],
				},
				options: {
					legend: {
						position: 'left',
					}
				}
			});
		</script>";
}	
page_end($endscript);

?>