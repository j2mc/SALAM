<?php

/* 
index.php for SALAM v2
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

function overview() {
		$site_stmt = db_prepare("SELECT id, name, status FROM sites");
		db_execute($site_stmt);
		$site_result = db_fetch($site_stmt);
		$host_stmt = db_prepare("SELECT id, name, description, status, last_rt FROM hosts WHERE enabled = ? AND site_id = ?");
		$alert_stmt = db_prepare("SELECT COUNT(id), MAX(level) FROM alerts WHERE type = 'service' AND active = 1 AND type_id IN (SELECT services.id FROM services WHERE host_id = ?)");
		foreach ($site_result as $site) {
			db_execute($host_stmt, array(1, $site['id']));
			$result = db_fetch($host_stmt);
			if(!empty($result)) {
				echo '<div class="card">
						<div class="list-group list-group-flush">
						<a href="detail.php?type=site&amp;id=', $site['id'], '" class="list-group-item list-group-item-', status_class($site['status']), ' h4 text-center">', $site['name'];
						if ($site['status'] == 2)  //Site is down
							echo '<span class="oi oi-warning float-right" title="Critical" aria-hidden="true"></span>';
						echo '</a>';
						foreach ($result as $host) {
							$badge = '<small class="float-right">' .  $host['last_rt'] / 1000 . 'ms</small>';
							if ($host['status'] == 0) { //Host is up, see if there are service alerts
								db_execute($alert_stmt, array($host['id']));
								$alerts = db_fetch($alert_stmt, 'row');
								if ($alerts[0] > 0) {
									$badge = '<span class="badge">' . $alerts[0] . '</span>';
									$host['status'] = $alerts[1];
								}									
							}
							elseif ($host['status'] == 2)
								$badge = '<span class="oi oi-warning float-right" title="Critical" aria-hidden="true"></span>';
							if ($site['status'] == 2)
								$host['status'] = 2;
							echo '<a href="detail.php?type=host&amp;id=', $host['id'], '" class="list-group-item list-group-item-', status_class($host['status']), '"><strong>', $host['name'], '</strong> <small>', $host['description'], '</small>', $badge, '</a>';
						}
						echo '</div>';
				echo '</div>';
			}
		}
		echo '
		</div>';
}

$endscript = '<script>$(document).ready(function() {setInterval(function(){ $( "#overview" ).load( "index.php?ajax" ); }, ' . $refresh_frequency * 1000 . ');});</script>';

if (isset($_GET['ajax']))
	overview();
else {
	$tv = FALSE;
	if (isset($_GET['tv']))
		$tv = TRUE;
	$breadcrumb = array(
				"active" => "Dashboard"
			);
	page_start($breadcrumb, $tv, '', $dashboard_columns);
	echo '
	<div class="card-columns">';
	overview();
	echo '
	</div>';
	page_end($endscript);
}
?>