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
	$site_stmt = db_prepare("INSERT INTO sites (name, subnet, exclude, hostmethod) VALUES (?, ?, ?, ?)");
	$site_result = db_execute($site_stmt, array($sitename, $subnet, $exclude, $hostdiscoverymethod));
	if ($site_result['result']) {
		echo '<div class="panel-group">';
		$nmapoutput = nmap_cmd($hostcmd . ' -oX - ' . $subnet);
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
				<div class="panel panel-default">
					<div class="panel-heading">
						<input type="checkbox" class="bs-toggle" data-on="Enabled" data-off="Disabled" data-size="mini" id="host-toggle-', $host_result['id'], '" data-host-id="', $host_result['id'], '" />
						<strong>', $hostname, '</strong> <small>(', $addresses, ')</small>
					</div>
					<div id="host-data-', $host_result['id'], '" class="panel-collapse collapse">
						<div class="panel-body">
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
						$(this).parent().parent().next().children().html('<h5>Scanning...</h5><div class=\"progress\"><div class=\"progress-bar progress-bar-striped active\" style=\"width: 100%\"></div></div>');
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
	
} else {
page_start('Add Site', FALSE, '<link href="css/bootstrap-toggle.min.css" rel="stylesheet" />');

echo '
	<form class="form-horizontal" role="form" action="" method="post" name="add-site-form" id="add-site-form">
		<div class="form-group">
			<label for="name" class="col-sm-2 control-label">Site Name</label>
			<div class="col-sm-10">
				<input type="text" class="form-control" id="sitename" name="sitename" placeholder="Ex: Main Office">
			</div>
		</div>
		<div class="form-group">
			<label for="subnet" class="col-sm-2 control-label">Subnet or Network Range</label>
			<div class="col-sm-10">
				<input type="text" class="form-control" id="subnet" name="subnet" placeholder="Ex: 192.168.0.0/24 OR 192.168.0.1-20">
				<span class="help-block">On the next page you will select which hosts from this subnet you want to monitor.</span>
			</div>
		</div>
		<div class="panel panel-default">
			<div class="panel-heading">
				<a data-toggle="collapse" href="#collapseOne"><h4 class="panel-title">Advanced Options <span class="glyphicon glyphicon-chevron-down"></span></h4></a>
			</div>
			<div id="collapseOne" class="panel-collapse collapse">
				<div class="panel-body">
					<div class="form-group">
					<label for="exclude" class="col-sm-2 control-label">Exclude IPs</label>
						<div class="col-sm-10">
							<input type="text" class="form-control" id="exclude" name="exclude" placeholder="Ex: 192.168.0.1 OR 192.168.0.1,192.168.0.2 OR 192.168.0.1-10">
							<span class="help-block">Enter a single, comma separated list or range of IPs you want exluded from scanning.</span>
						</div>
					</div>
					<div class="form-group">
						<label for="hostdiscoverymethod" class="col-sm-2 control-label">Host Discovery Method</label>
						<div class="col-sm-10">
							<div class="btn-group" data-toggle="buttons">
								<label class="btn btn-primary active">
									<input type="radio" name="hostdiscoverymethod" id="h-disco-icmp" checked value="icmp"> Standard ICMP </input>
								</label>
								<label class="btn btn-primary">
									<input type="radio" name="hostdiscoverymethod" id="h-disco-aggressive" value="aggressive"> Aggressive </input>
								</label>
							</div>
							<span class="help-block">Use Standard ICMP unless your hosts do not respond to standard ICMP type 8 requests.</span>
						</div>
					</div>
					<!--<div class="form-group">
						<label for="servicediscoverymethod" class="col-sm-2 control-label">Service Discovery Method</label>
						<div class="col-sm-10">
							<div class="btn-group" data-toggle="buttons">
								<label class="btn btn-primary active" onclick="$(\'#customdiscoform\').collapse(\'hide\');">
									<input type="radio" name="servicediscoverymethod" id="s-disco-fast" checked value="fast"> Fast (100 most common) </input>
								</label>
								<label class="btn btn-primary" onclick="$(\'#customdiscoform\').collapse(\'hide\');">
									<input type="radio" name="servicediscoverymethod" id="s-disco-std" value="standard"> Standard (1000 most common) </input>
								</label>
								<label class="btn btn-primary" onclick="$(\'#customdiscoform\').collapse(\'show\');">
									<input type="radio" name="servicediscoverymethod" id="s-disco-custom" value="custom"> Custom </input>
								</label>
							</div>
						</div>
					</div>
					<div class="form-group collapse" id="customdiscoform">
						<label for="customdisco" class="col-sm-2 control-label">Service Discovery Ports</label>
						<div class="col-sm-10">
							<input type="text" class="form-control" id="customdisco" name="customdisco" placeholder="Ex: 25,53,80,443,3389 OR 1-65535">
						</div>
					</div>-->
				</div>
			</div>
		</div>
		<div class="form-group">
			<div class="col-sm-offset-2 col-sm-10">
				<button type="submit" class="btn btn-default btn-primary">Start Initial Discovery</button>
			</div>
		</div>
	</form>
	<div class="hidden" id="add-site-results">
		<h2>Discovering Hosts, Please Wait...</h2>
		<div class="progress">
			<div class="progress-bar progress-bar-striped active" role="progressbar" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="width: 100%">
				<span class="sr-only">Scanning</span>
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
					$('#add-site-form').addClass('hidden');
					$('#add-site-results').removeClass('hidden');
				}
			}); 
		});
	</script>";
	
page_end($endscript);

}

?>