<?php

/* 
addsite.php for SALAM
By Jacob McEntire
Copyright 2015
*/

require_once("library/SSI.php");

if (isset($_GET['enable'])) {
	$host_id = check_xss($_GET['enable']);
	$host_stmt = db_prepare("SELECT name, description, enabled FROM hosts WHERE id = ?");
	db_execute($host_stmt, array($host_id));
	$hostinfo = db_fetch($host_stmt, 'row');
	$host_stmt = db_prepare("UPDATE hosts SET enabled=? WHERE id=?");
	db_execute($host_stmt, array(TRUE, $host_id));
	echo '
	<form action="scanhost.php?save=', $host_id, '" method="post" id="host-form-', $host_id, '">
		<div class="form-group row">
			<label for="name" class="col-sm-2 col-form-label">Name</label>
			<div class="col-sm-10">
				<input type="text" name="name" class="form-control" placeholder="Name" value="', $hostinfo['name'], '" />
			</div>
		</div>
		<div class="form-group row">
			<label class="col-sm-2 col-form-label">Description</label>
			<div class="col-sm-10">
				<input type="text" name="description" class="form-control" placeholder="Description" value="', $hostinfo['description'], '" />
			</div>
		</div>
		<div class="form-group">
			<label class="col-sm-2 col-form-label">Services</label>
			<div class="col-sm-10">';
			$service_stmt = db_prepare("SELECT id, type, port, name, enabled FROM services WHERE host_id = ?");
			db_execute($service_stmt, array($host_id));
			$result = db_fetch($service_stmt);
			if(!empty($result)) {
				echo '
				<table class="table table-condensed table-striped">
					<thead><tr><th>Monitor</th><th>Type</th><th>Service Name</th></tr></thead><tbody>';
				foreach ($result as $service) {
					echo '
					<tr>
						<td>
							<input type="checkbox" class="servicechk" data-size="small" name="services[', $service['id'], '][enabled]" ';
							if ($service['enabled'])
								echo ' checked="checked"';
							echo '/>
						</td><td>
							', strtoupper($service['type']), ' ', $service['port'], '
						</td><td>
							<input type="text" name="services[', $service['id'], '][name]" class="form-control input-sm" value="', $service['name'], '" />
						</td>
					</tr>';
				}
				echo '
				</tbody></table>';
			}
		echo '
			<button type="button" class="btn btn-primary">Scan for Services</button>
			</div>
		</div>
	</form>
	<div id="host-results-$host_id"></div>';
	echo "
	<script>
		$(function() {
			$('#host-form-$host_id input').focus(function() {
				$('#host-results-$host_id').html('');
			});
			$('#host-form-$host_id input').change(function() {
				var options = {
					target: '#host-results-$host_id',
					beforeSubmit: function() {
						$('#host-form-$host_id input').prop('disabled', true);
						$('#host-toggle-$host_id').bootstrapToggle('disable');
						$('#host-results-$host_id').html('<div class=\"alert alert-info\"><strong> Saving...</strong> Please Wait</div>');
					},
					success:	function() {
						$('#host-form-$host_id input').prop('disabled', false);
						$('#host-toggle-$host_id').bootstrapToggle('enable');
					}
				};
				$('#host-form-$host_id').ajaxForm(options); 
				$('#host-form-$host_id').submit();
			});
		})
	</script>";
}
elseif (isset($_GET['scan'])) {
	$host_id = check_xss($_GET['scan']);
	$ip_stmt = db_prepare("SELECT data FROM hostdata WHERE host_id = ? AND (type = ? OR type = ?) LIMIT 1");
	$host_stmt = db_prepare("SELECT name, description, enabled FROM hosts WHERE id = ?");
	db_execute($ip_stmt, array($host_id, 'ipv4', 'ipv6'));
	$ip = db_fetch($ip_stmt, 'col');
	db_execute($host_stmt, array($host_id));
	$hostinfo = db_fetch($host_stmt, 'row');
	$nmapoutput = nmap_cmd(' -n -Pn -sS --open -T5 -oX - ' . $ip);
	$host_stmt = db_prepare("UPDATE hosts SET enabled=? WHERE id=?");
	db_execute($host_stmt, array(TRUE, $host_id));
	$service_stmt = db_prepare("INSERT INTO services (host_id, type, port, name, avg_rt, avg_count) VALUES (?, ?, ?, ?, ?, ?)");
	echo '
	<form action="scanhost.php?save=', $host_id, '" method="post" id="host-form-', $host_id, '">
		<div class="form-group row">
			<label for="name" class="col-sm-2 col-form-label">Name</label>
			<div class="col-sm-10">
				<input type="text" name="name" class="form-control" placeholder="Name" value="', $hostinfo['name'], '" />
			</div>
		</div>
		<div class="form-group row">
			<label class="col-sm-2 col-form-label">Description</label>
			<div class="col-sm-10">
				<input type="text" name="description" class="form-control" placeholder="Description" value="', $hostinfo['description'], '" />
			</div>
		</div>
		<div class="form-group">
			<label class="col-sm-2 col-form-label">Services</label>
			<div class="col-sm-10">
				<table class="table table-condensed table-striped">
					<thead><tr><th>Monitor</th><th>Type</th><th>Service Name</th></tr></thead><tbody>';
		$rtime = $nmapoutput->host->times['srtt'];
		if ($rtime <= 0)
			$rtime = 1000;
		foreach ($nmapoutput->host->ports->port as $port) {
			if ($port->state['state'] == 'open') {
				$service_result = db_execute($service_stmt, array($host_id, strtoupper($port['protocol']), $port['portid'], strtoupper($port->service['name']), $rtime, 1));
				echo '
				<tr>
					<td>
						<input type="checkbox" class="servicechk" data-size="small" name="services[', $service_result['id'], '][enabled]" />
					</td><td>
						', strtoupper($port['protocol']), ' ', $port['portid'], '
					</td><td>
						<input type="text" name="services[', $service_result['id'], '][name]" class="form-control input-sm" value="', strtoupper($port->service['name']), '" />
					</td>
				</tr>';
			}
		}
		echo "
					</tbody>
				</table>
			</div>
		</div>
	</form>
	<div id=\"host-results-$host_id\"></div>
	<script>
		$(function() {
			$('.servicechk').bootstrapToggle({
				offstyle: 'secondary'
			});
			$('#host-form-$host_id input').focus(function() {
				$('#host-results-$host_id').html('');
			});
			$('#host-form-$host_id input').change(function() {
				//$('#host-form-$host_id input').prop('disabled', true);
				//$('#host-toggle-$host_id').bootstrapToggle('disable');
				var options = {
					target: '#host-results-$host_id',
					beforeSubmit: function() {
						$('#host-form-$host_id input').prop('disabled', true);
						$('#host-toggle-$host_id').bootstrapToggle('disable');
						$('#host-results-$host_id').html('<div class=\"alert alert-info\"><strong> Saving...</strong> Please Wait</div>');
					},
					success:	function() {
						$('#host-form-$host_id input').prop('disabled', false);
						$('#host-toggle-$host_id').bootstrapToggle('enable');
					}
				};
				$('#host-form-$host_id').ajaxForm(options); 
				$('#host-form-$host_id').submit();
			});
		})
	</script>";
}
elseif (isset($_GET['save'])) {
	$host_id = check_xss($_GET['save']);
	$name = check_xss($_POST['name']);
	$description = check_xss($_POST['description']);
	$host_stmt = db_prepare("UPDATE hosts SET name=?, description=? WHERE id=?");
	db_execute($host_stmt, array($name, $description, $host_id));
	if (!empty($_POST['services'])) {
		$service_stmt = db_prepare("UPDATE services SET name=?, enabled=? WHERE id=?");
		foreach ($_POST['services'] as $service_id => $service) {
			if (!empty($service['enabled']))
				$enabled = TRUE;
			else
				$enabled = FALSE;
			db_execute($service_stmt, array(check_xss($service['name']), $enabled, $service_id));
		}
	}
	echo '<div class="alert alert-success"><strong>Saved</strong></div>';
}
elseif (isset($_GET['disable'])) {
	$host_id = check_xss($_GET['disable']);
	$host_stmt = db_prepare("UPDATE hosts SET enabled=? WHERE id=?");
	db_execute($host_stmt, array(FALSE, $host_id));
	echo 'DISABLED';
}
	
?>