<?php

/* 
addsite.php for SALAM v2
By Jacob McEntire
Copyright 2018
*/

require_once("library/SSI.php");

if (isset($_POST['sitename']) && isset($_POST['subnet'])) {
	$sitename = check_xss($_POST['sitename']);
	$subnet = check_xss($_POST['subnet']);
	$exclude = check_xss($_POST['exclude']);
	$hostdiscoverymethod = check_xss($_POST['hostdiscoverymethod']);
	if ($hostdiscoverymethod == 'aggressive')
		$hostcmd = "-sn";
	else
		$hostcmd = "-sn -PE";
	$nmapoutput = nmap_cmd($hostcmd . ' -oX - ' . $subnet);
	if ($nmapoutput->runstats->hosts['up'] > 0) {
		echo '<div class="panel-group">';
		$site_stmt = db_prepare("INSERT INTO sites (name, subnet, exclude, hostmethod) VALUES (?, ?, ?, ?)");
		$site_result = db_execute($site_stmt, array($sitename, $subnet, $exclude, $hostdiscoverymethod));
		$data_stmt = db_prepare("INSERT INTO hostdata (host_id, type, data) VALUES (?, ?, ?)");
		$host_stmt = db_prepare("INSERT INTO hosts (site_id, avg_rt, avg_count) VALUES (?, ?, ?)");
		$hostname_stmt = db_prepare("UPDATE hosts SET name=?, description=? WHERE id=?");
		foreach ($nmapoutput->host as $host) {
			$PTR = '';
			$mac = '';
			$ipv4 = '';
			$ipv6 = '';
			$vendor = '';
			$addresses = '';
			$rtime = $host->times['srtt'];
			if ($rtime <= 0)
				$rtime = 1000;
			$host_result = db_execute($host_stmt, array($site_result['id'], $rtime, 1));
			foreach ($host->hostnames->hostname as $hostname) {
				$data_result = db_execute($data_stmt, array($host_result['id'], $hostname['type'], $hostname['name']));
				${$hostname['type']} = $hostname['name'];
			}
			foreach ($host->address as $address) {
				$data_result = db_execute($data_stmt, array($host_result['id'], $address['addrtype'], $address['addr']));
				if ($address['addrtype'] == "mac" && !empty($address['vendor'])) {
					$vendor = $address['vendor'];
					$data_result = db_execute($data_stmt, array($host_result['id'], 'vendor', $vendor));
				}
				${$address['addrtype']} = $address['addr'];
			}
			if (!empty($PTR))
				$hostname = $PTR;
			elseif (!empty($ipv4))
				$hostname = $ipv4;
			elseif (!empty($mac))
				$hostname = $mac;
			elseif (!empty($ipv6))
				$hostname = $ipv6;
			else
				$hostname = "Unknown";
			db_execute($hostname_stmt, array($hostname, $vendor, $host_result['id']));
			if (!empty($vendor))
				$addresses = $vendor;
			if (!empty($ipv4)) {
				if (empty($addresses))
					$addresses = $ipv4;
				else
					$addresses .= ' | ' . $ipv4;
			}
			if (!empty($mac)) {
				if (empty($addresses))
					$addresses = $mac;
				else
					$addresses .= ' | ' . $mac;
			}
			if (!empty($ipv6)) {
				if (empty($addresses))
					$addresses = $ipv6;
				else
					$addresses .= ' | ' . $ipv6;
			}
				
			echo '
				<div class="card">
					<div class="card-header">
						<input type="checkbox" class="bs-toggle" data-on="Enabled" data-off="Disabled" data-size="small" data-offstyle="secondary" id="host-toggle-', $host_result['id'], '" data-host-id="', $host_result['id'], '" />
						<strong>', $hostname, '</strong> <small>(', $addresses, ')</small>
					</div>
					<div id="host-data-', $host_result['id'], '" class="collapse">
						<div class="card-body">
						</div>
					</div>
				</div>
				';
		}
		echo "</div>
			<script>
			  $(function() {
				$('.bs-toggle').bootstrapToggle();
				$('.bs-toggle').change(function() {
					var hostid = $(this).attr('data-host-id');
					if ($(this).prop('checked')) {
						//$(this).bootstrapToggle('disable');
						$(this).parent().parent().next().children().html('<h5>Scanning...</h5><div class=\"progress\"><div class=\"progress-bar progress-bar-striped progress-bar-animated\" style=\"width: 100%\"></div></div>');
						$(this).parent().parent().next().collapse('show');
						$(this).parent().parent().next().children().load('scanhost.php?scan='+hostid);
					} else {
						$(this).parent().parent().next().children().load('scanhost.php?disable='+hostid);
						$(this).parent().parent().next().collapse('hide');
					}
				});
			  })
			</script>";
	}
	else
		die('No Hosts Found');
} else {

$breadcrumb = array(
	"Settings" => "settings.php",
	"active" => "Add Site"
);

page_start($breadcrumb, FALSE, '<link href="css/bootstrap-toggle.min.css" rel="stylesheet" />');

echo '
<div class="container">
	<div class="card" id="add-site-form-card">
		<div class="card-body">
			<form action="" method="post" name="add-site-form" id="add-site-form">
				<div class="form-group row">
					<label for="name" class="col-sm-2 col-form-label">Site Name</label>
					<div class="col-sm-10">
						<input type="text" class="form-control" id="sitename" name="sitename" placeholder="Ex: Main Office">
					</div>
				</div>
				<div class="form-group row">
					<label for="subnet" class="col-sm-2 col-form-label">Subnet, Network Range or List</label>
					<div class="col-sm-10">
						<input type="text" class="form-control" id="subnet" name="subnet" placeholder="Ex: 192.168.0.0/24 OR 192.168.0.1-20 OR 192.168.0.1 192.168.0.5 192.168.0.9">
						<small class="form-text text-muted">On the next page you will select which hosts from this subnet you want to monitor.</small>
					</div>
				</div>
				<div class="card mb-3">
					<div class="card-header">
						<h5 class="mb-0">
						<button type="button" class="btn btn-link collapsed" data-toggle="collapse" data-target="#collapseOne">Advanced Options <span class="oi oi-chevron-bottom" aria-hidden="true"></span></button>
						</h5>
					</div>
					<div id="collapseOne" class="collapse">
						<div class="card-body">
							<div class="form-group row">
							<label for="exclude" class="col-sm-2 col-form-label">Exclude IPs</label>
								<div class="col-sm-10">
									<input type="text" class="form-control" id="exclude" name="exclude" placeholder="Ex: 192.168.0.1 OR 192.168.0.1 192.168.0.2 OR 192.168.0.1-10">
									<small class="form-text text-muted">Enter a single, list, range or combination of IPs you want exluded from scanning.</small>
								</div>
							</div>
							<div class="form-group row">
								<label for="hostdiscoverymethod" class="col-sm-2 col-form-label">Host Discovery Method</label>
								<div class="col-sm-10">
									<div class="btn-group btn-group-toggle" data-toggle="buttons">
										<label class="btn btn-primary active">
											<input type="radio" name="hostdiscoverymethod" id="h-disco-icmp" checked value="icmp"> Standard ICMP
										</label>
										<label class="btn btn-primary">
											<input type="radio" name="hostdiscoverymethod" id="h-disco-aggressive" value="aggressive"> Aggressive
										</label>
									</div>
									<small class="form-text text-muted">Use Standard ICMP unless your hosts do not respond to standard ICMP type 8 requests.</small>
								</div>
							</div>
						</div>
					</div>
				</div>
				<button type="submit" class="btn btn-block btn-lg btn-primary">Discover Hosts</button>
			</form>
		</div>
	</div>
	<div class="card d-none" id="add-site-results-card">
		<div class="card-body" id="add-site-results">
			<h2>Discovering Hosts, Please Wait...</h2>
			<div class="progress">
				<div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="width: 100%">
					<span class="sr-only">Scanning</span>
				</div>
			</div>
		</div>
	</div>
</div>';

$endscript = "
	<script src='js/bootstrap-toggle.min.js'></script>
	<script src='js/jquery.form.min.js'></script>
	<script>
		$(document).ready(function() { 
			$('#add-site-form').ajaxForm({ 
				target: '#add-site-results', 
				beforeSubmit: function() {
					$('#add-site-form-card').addClass('d-none');
					$('#add-site-results-card').removeClass('d-none');
				}
			}); 
		});
	</script>";
	
page_end($endscript);

}

?>