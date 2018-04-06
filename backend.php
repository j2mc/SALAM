<?php
/*
-----------------------------------
backend.php for SALAM v2
By Jacob McEntire
Copyright 2016
------------------------------------
*/

require('library/SSI.php');

$runstats = array(
	'time' => time(),
	'runtime' => -microtime(true),
	'hosts' => 0,
	'services' => 0,
	'warning' => 0,
	'warning_conf' => 0,
	'critical' => 0,
	'critical_conf' => 0,
	'email_sent' => 0,
	'rows_added' => 0,
	'rows_deleted' => 0);
$alert_email = [];

//echo date("Y-m-d H:i:s"), ',';

run_checks();

if (!empty($alert_email))
	send_alert_email($alert_email);

//Archive data every 15 minutes
if (date("i") % 15 == 0)
	archive_data();

$runstats['runtime'] += microtime(true);
if (isset($_GET['interactive']))
	print_r($runstats);
$runstat_stmt = db_prepare("INSERT INTO runstats (time, runtime, hosts, services, warning, warning_conf, critical, critical_conf, email_sent, rows_added, rows_deleted) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
db_execute($runstat_stmt, array_values($runstats));

function check_alert($type, $type_id, $level, $checked = 0, $rtime = 0, $avg_rt = 0) {
	$time = time();
	global $email_start, $email_end, $email_warn, $alert_email;
	$send_email = FALSE;
	if ($time >= mktime($email_start, 0, 0) && $time <= mktime($email_end, 0, 0))
		$between_times = TRUE;
	else
		$between_times = FALSE;
	$alert_stmt = db_prepare("SELECT id, level, email_sent, checked FROM alerts WHERE type = ? AND type_id = ? AND active = ? LIMIT 1");
	$new_alert = db_prepare("INSERT INTO alerts (type, type_id, active, level, start, lastcheck, checked) VALUES (?, ?, ?, ?, ?, ?, ?)");
	db_execute($alert_stmt, array($type, $type_id, 1));
	$result = db_fetch($alert_stmt, 'row');
	if (!empty($result)) {
		//existing alert found
		$update_alert = db_prepare("UPDATE alerts SET active = ?, lastcheck = ?, checked = ? WHERE id = ?");
		$alert_result['id'] = $result['id'];
		$checked += $result['checked'];
		if ($result['level'] == $level) {
			//alert is still active, update time
			db_execute($update_alert, array(1, $time, $checked, $result['id']));
			if ($between_times && (($email_warn && $level == 1) || $level == 2) && !$result['email_sent']) {
				$send_email = TRUE;
			}
		}
		else {
			//alert is no longer active, turn off
			db_execute($update_alert, array(0, $time, $checked, $result['id']));
			if ($result['email_sent']) {
				//send status change email no matter the time or level
				$send_email = TRUE;
			}
			elseif ($between_times && (($email_warn && $level == 1) || $level == 2)) {
				//send alert email if enabled
				$send_email = TRUE;
			}
			if ($level > 0) {
				$alert_result = db_execute($new_alert, array('host', $result['id'], 1, $level, $time, $time, $checked));
			}
		}
	}
	else {
		//No active alerts found
		if ($level > 0) {
			//Create new alert
			if ($between_times && (($email_warn && $level == 1) || $level == 2)) {
				//send alert email if enabled
				$send_email = TRUE;
			}
			$alert_result = db_execute($new_alert, array($type, $type_id, 1, $level, $time, $time, $checked));
		}
	}
	if ($send_email)
		$alert_email[] = array('alert_id' => $alert_result['id'], 'type' => $type, 'type_id' => $type_id, 'level' => $level, 'rtime' => $rtime, 'avg_rt' => $avg_rt);
}

function alert_email_body($alert) {
	switch ($alert['level']) {
		case 0:
			$bgcolor = '#dff0d8';
			$color = '#3c763d';
			$text = 'RECOVERY';
			break;
		case 1:
			$bgcolor = '#fcf8e3';
			$color = '#8a6d3b';
			$text = 'WARNING';
			break;
		case 2:
			$bgcolor = '#f2dede';
			$color = '#a94442';
			$text = 'CRITICAL';
			break;
	}
	$message = '<tr><td bgcolor="' . $bgcolor . '" style="border:1px solid #dddddd;"><font color="' . $color . '" face="Tahoma" size="3">';
	if ($alert['type'] == 'site') {
		$site_stmt = db_prepare("SELECT name, subnet FROM sites WHERE id = ? LIMIT 1");
		db_execute($site_stmt, array($alert['type_id']));
		$site_info = db_fetch($site_stmt, 'row');
		$name = $site_info['name'];
		$message .= '<b>SITE ' . $text . ' - ' . $name . '</b><br />' . $site_info['subnet'];
	}
	elseif ($alert['type'] == 'host') {
		$host_stmt = db_prepare("SELECT hosts.name, hosts.description, hostdata.data FROM hosts JOIN hostdata ON hosts.id = hostdata.host_id WHERE hosts.id = ? AND (hostdata.type = ? OR hostdata.type = ?) LIMIT 1");
		db_execute($host_stmt, array($alert['type_id'], 'ipv4', 'ipv6'));
		$host_info = db_fetch($host_stmt, 'row');
		$name = $host_info['name'];
		$message .= '<b>HOST ' . $text . ' - ' . $name . '</b><br />';
		if ($host_info['description'] != '')
			$message .= $host_info['description'] . '<br />';
		$message .= $host_info['data'];
	}
	elseif ($alert['type'] == 'service') {
		$service_stmt = db_prepare("SELECT services.name AS service_name, services.type, services.port, hosts.name AS host_name, hosts.description, hostdata.data FROM services JOIN hosts ON services.host_id = hosts.id JOIN hostdata ON hosts.id = hostdata.host_id WHERE services.id = ? AND (hostdata.type = ? OR hostdata.type = ?) LIMIT 1");
		db_execute($service_stmt, array($alert['type_id'], 'ipv4', 'ipv6'));
		$service_info = db_fetch($service_stmt, 'row');
		$name = $service_info['service_name'] . ' on ' . $service_info['host_name'];
		$message .= '<b>SERVICE ' . $text . ' - ' . $name . '</b><br />';
		if ($service_info['description'] != '')
			$message .= $service_info['description'] . '<br />';
		$message .= $service_info['data'] . ' ' . $service_info['type'] . ' ' . $service_info['port'];
	}
	$subject = strtoupper($alert['type']) . ' ' . $text . ' - ' . $name;
	if ($alert['level'] < 2 && $alert['type'] != 'site') {
		$message .= ' - response time ' . $alert['rtime'] / 1000 . ' ms';
		$subject .= ' - response time ' . $alert['rtime'] / 1000 . ' ms';
	}
	$message .= '</font></td></tr>';
	return array($subject, $message);
}

function send_alert_email($alerts) {
	global $from_email, $to_email, $runstats;
	$headers  = 'MIME-Version: 1.0' . "\r\n";
	$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
	$headers .= 'From: SALAM <' . $from_email . '>';
	$message = '
	<html>
	<body>
	  <table border="0" cellpadding="0" cellspacing="0" width="100%">
	    <tr><td valign="top">
		  <table border="0" cellpadding="5" cellspacing="0" width="100%">
		    <tr><td bgcolor="#222222"><font color="#9d9d9d" face="Tahoma" size="4">S A L A M</font></td></tr>
		  </table>
		</td></tr>
		<tr><td align="center" valign="top">
		  <table border="0" cellpadding="5" cellspacing="0" width="90%">
		    <tr><td align="center"></td></tr>
			<tr><td align="center" valign="top">
			  <table border="0" cellpadding="10" cellspacing="0" width="100%">
				<tr><td align="center" style="border:1px solid #dddddd; border-top-left-radius: 4px; border-top-right-radius: 4px;">
				  <font color="#555555" face="Tahoma" size="4">New Alerts</font><br /><font color="#555555" face="Tahoma" size="2">' . date("r") . '</font></td></tr>';
	
	$alert_update_stmt = db_prepare("UPDATE alerts SET email_sent = 1 WHERE id = ?");
	if (count($alerts) == 1) {
		$type = $alerts[0]['type'];
		$return = alert_email_body($alerts[0], TRUE);
		$subject = $return[0];
		$message .= $return[1];
	}
	else {
		$recovery_count = 0;
		$warning_count = 0;
		$critical_count = 0;
		foreach ($alerts as $alert) {
			$return = alert_email_body($alert);
			$message .= $return[1];
			switch ($alert['level']) {
				case 0:
					$recovery_count++;
					break;
				case 1:
					$warning_count++;
					break;
				case 2:
					$critical_count++;
					break;
			}
			
		}
		$subject = 'MULTIPLE ALERTS ';
		if ($critical_count > 0)
			$subject .= '<' . $critical_count . ' CRITICAL>';
		if ($warning_count > 0)
			$subject .= '<' . $warning_count . ' WARNING>';
		if ($recovery_count > 0)
			$subject .= '<' . $recovery_count . ' RECOVERY>';
	}
	$message .= '
				<tr><td align="center" style="border:1px solid #dddddd; border-bottom-left-radius: 4px; border-bottom-right-radius: 4px;"><font color="#9d9d9d" face="Tahoma" size="2">View All Alerts</font></td></tr>
			  </table>
			  <br /><br />
			</td></tr>
		  </table>
		</td></tr>
	  </table>
	</body>
	</html>';
	$sent = mail($to_email, $subject, $message, $headers);
	if ($sent) {
		foreach ($alerts as $alert) {
			db_execute($alert_update_stmt, array($alert['alert_id']));
		}
		$runstats['email_sent'] = TRUE;
	}
}

function run_checks() {
	global $runstats;
	$site_stmt = db_prepare("SELECT id, hostmethod, warn_percent, service_int, discovery_int, lastcheck FROM sites");
	db_execute($site_stmt);
	$checkdata_stmt = db_prepare("INSERT INTO checkdata (type, type_id, time, rtime) VALUES (?, ?, ?, ?)");
	$site_result = db_fetch($site_stmt);
	foreach ($site_result as $site) {
		//Loop through all sites
		$host_stmt = db_prepare("SELECT hostdata.data FROM hosts JOIN hostdata ON hosts.id = hostdata.host_id WHERE hosts.enabled = ? AND hosts.site_id = ? AND (type = ? OR type = ?)");
		db_execute($host_stmt, array(1, $site['id'], 'ipv4', 'ipv6'));
		$result = db_fetch($host_stmt);
		$hostlist = '';
		if(!empty($result)) {
			//Loop through all enabled hosts in site
			foreach ($result as $v) {
				$hostlist .= $v[0] . ' ';
				$runstats['hosts']++;
			}
			if ($site['hostmethod'] == "aggressive")
				$hostcmd = "-sn -PE -PS80,443 -v -n";
			else
				$hostcmd = "-sn -PE -v -n";
			$nmapoutput = nmap_cmd($hostcmd . ' -oX - ' . $hostlist);
			$time = time();
			$host_id_stmt = db_prepare("SELECT hosts.id, hosts.avg_rt, hosts.avg_count, hosts.status, hosts.lastcheck FROM hosts JOIN hostdata ON hosts.id = hostdata.host_id WHERE hostdata.data = ? LIMIT 1");
			$host_update_stmt = db_prepare("UPDATE hosts SET status = ?, avg_rt = avg_rt + ?, avg_count = avg_count + 1, lastcheck = ?, last_rt = ?, uptime = uptime + ?, downtime = downtime + ?, warntime = warntime + ? WHERE id = ?");
			$site_uptime = 0;
			$site_downtime = 0;
			$sitelastcheck = $site['lastcheck'];
			if (empty($sitelastcheck))
				$sitelastcheck = $time;
			if ($nmapoutput->runstats->hosts['up'] > 0) {
				//Site is up
				$site_status = 0;
				$site_uptime = $time - $sitelastcheck;
				foreach ($nmapoutput->host as $host) {
					//parse nmap output, store in database and create alerts
					$hostaddress = '';
					foreach ($host->address as $address) {
						if ($address['addrtype'] == "ipv6")
							$hostaddress = $address['addr'];
						elseif ($address['addrtype'] == "ipv4")
							$hostaddress = $address['addr'];
					}
					$rtime = fixnanosec($host->times['srtt']);
					//get host id and info
					db_execute($host_id_stmt, array($hostaddress));
					$host_info = db_fetch($host_id_stmt, 'row');
					if (!empty($host_info)) {
						$uptime = 0;
						$downtime = 0;
						$warntime = 0;
						$checked = 1;
						$lastcheck = $host_info['lastcheck'];
						if (empty($lastcheck))
							$lastcheck = $time;
						$avg_rt = $host_info['avg_rt'] / $host_info['avg_count'];
						$max_rt = $avg_rt * $site['warn_percent'];
						$time_since_last = $time - $host_info['lastcheck'];
						if ($host->status['state'] == 'up') {
							if ($rtime > $max_rt) {
								$status = 1;
								$runstats['warning']++;
							}
							else
								$status = 0;
						}
						else {
							$status = 2;
							$runstats['critical']++;
						}
						if ($status > 0) {
							//double check result
							//echo "-$hostaddress-alert->$status";
							if (time() - $time < 1)
								sleep(1);
							$tempnmap = nmap_cmd($hostcmd . ' -oX - ' . $hostaddress);
							$rtime = fixnanosec($tempnmap->host->times['srtt']);
							if ($tempnmap->runstats->hosts['up'] > 0 && ($status == 2 || $tempnmap->host->times['srtt'] < $max_rt))
								$status -= 1;
							$checked++;
							//echo '->', $status;
						}
						if ($status > 0) {
							//triple check result
							if (time() - $time < 3)
								sleep(2);
							$tempnmap = nmap_cmd($hostcmd . ' -oX - ' . $hostaddress);
							$rtime = fixnanosec($tempnmap->host->times['srtt']);
							if ($tempnmap->runstats->hosts['up'] > 0 && ($status == 2 || $tempnmap->host->times['srtt'] < $max_rt))
								$status -= 1;
							$checked++;
							//echo '->', $status;
						}
						switch ($status) {
							case 0:
								$uptime = $time - $lastcheck;
								break;
							case 1:
								$warntime = $time - $lastcheck;
								$runstats['warning_conf']++;
								break;
							case 2:
								$downtime = $time - $lastcheck;
								$runstats['critical_conf']++;
								break;
						}
						check_alert('host', $host_info['id'], $status, $checked, $rtime, $avg_rt);
						db_execute($host_update_stmt, array($status, $rtime, $time, $rtime, $uptime, $downtime, $warntime, $host_info['id']));
						if ($status < 2)
							db_execute($checkdata_stmt, array('host', $host_info['id'], $time, $rtime));
					}
					
				}
				$include_time = time() - ($site['service_int'] * 60);
				$host_stmt = db_prepare("SELECT hostdata.data, hosts.id FROM hosts JOIN hostdata ON hosts.id = hostdata.host_id WHERE hosts.enabled = ? AND hosts.status = ? AND hosts.site_id = ? AND (type = ? OR type = ?)");
				db_execute($host_stmt, array(1, 0, $site['id'], 'ipv4', 'ipv6'));
				$result = db_fetch($host_stmt);
				if(!empty($result)) {
					//Loop through all enabled hosts in site that are up
					$service_stmt = db_prepare("SELECT id, type, port, avg_rt, avg_count, lastcheck FROM services WHERE enabled = ? AND host_id = ? AND (lastcheck < ? OR status > ?)");
					$service_update_stmt = db_prepare("UPDATE services SET status = ?, avg_rt = avg_rt + ?, avg_count = avg_count + 1, lastcheck = ?, last_rt = ?, uptime = uptime + ?, downtime = downtime + ?, warntime = warntime + ? WHERE id = ?");
					foreach ($result as $host) {
						db_execute($service_stmt, array(1, $host['id'], $include_time, 0));
						$service_result = db_fetch($service_stmt);
						if (!empty($service_result)) {
							foreach ($service_result as $s) {
								//Loop through all services that need to be scanned, run scan, update db and create alerts
								$runstats['services']++;
								$uptime = 0;
								$downtime = 0;
								$warntime = 0;
								$checked = 1;
								$lastcheck = $s['lastcheck'];
								$avg_rt = $s['avg_rt'] / $s['avg_count'];
								$max_rt = $avg_rt * $site['warn_percent'];
								if ($s['type'] == 'UDP')
									$servicecmd = "-sU -p " . $s['port'] . " -v -n -Pn";
								else
									$servicecmd = "-sT -p " . $s['port'] . " -v -n -Pn";
								$servicecmd .= ' -oX - ' . $host['data'];
								$nmapoutput = nmap_cmd($servicecmd);
								$time = time();
								if (empty($lastcheck))
									$lastcheck = $time;
								if ($nmapoutput->host->ports->port->state['state'] == 'open') {
									$rtime = fixnanosec($nmapoutput->host->times['srtt']);
									if ($rtime > $max_rt) {
										$status = 1;
										$runstats['warning']++;
									}
									else
										$status = 0;
								}
								else {
									$status = 2;
									$runstats['critical']++;
								}
								if ($status > 0) {
									//double check result
									//echo 'alert->', $status;
									if (time() - $time < 1)
										sleep(1);
									$nmapoutput = nmap_cmd($servicecmd);
									$rtime = fixnanosec($nmapoutput->host->times['srtt']);
									if ($nmapoutput->host->ports->port->state['state'] == 'open' && ($status == 2 || $rtime < $max_rt))
										$status -= 1;
									$checked++;
									//echo '->', $status;
								}
								if ($status > 0) {
									//triple check result
									if (time() - $time < 2)
										sleep(1);
									$nmapoutput = nmap_cmd($servicecmd);
									$rtime = fixnanosec($nmapoutput->host->times['srtt']);
									if ($nmapoutput->host->ports->port->state['state'] == 'open' && ($status == 2 || $rtime < $max_rt))
										$status -= 1;
									$checked++;
									//echo '->', $status;
								}
								switch ($status) {
									case 0:
										$uptime = $time - $lastcheck;
										break;
									case 1:
										$warntime = $time - $lastcheck;
										$runstats['warning_conf']++;
										break;
									case 2:
										$downtime = $time - $lastcheck;
										$runstats['critical_conf']++;
										break;
								}
								check_alert('service', $s['id'], $status, $checked, $rtime, $avg_rt);
								db_execute($service_update_stmt, array($status, $rtime, $time, $rtime, $uptime, $downtime, $warntime, $s['id']));
								if ($status < 2)
									db_execute($checkdata_stmt, array('service', $s['id'], $time, $rtime));
								}
						}
					}
				}
			}
			else {
				//Site is DOWN!
				$site_status = 2;
				$site_downtime = $time - $sitelastcheck;	
				$runstats['critical']++;
				$runstats['critical_conf']++;
			}
			check_alert('site', $site['id'], $site_status);
			$site_update_stmt = db_prepare("UPDATE sites SET status = ?, lastcheck = ?, uptime = uptime + ?, downtime = downtime + ? WHERE id = ?");
			db_execute($site_update_stmt, array($site_status, $time, $site_uptime, $site_downtime, $site['id']));
		}
		
	}
}

function fixnanosec($time) {
	if ($time <= 0)
		$time = 1000;
	return $time;
}

function archive_data() {
	global $runstats;
	$time = time();
	//Calulate min, max, avg for past 15 minutes and put into archive table
	//Resolution: 0 = 15 minutes | 1 = hourly | 2 = daily
	$archive_stmt = db_prepare("INSERT INTO archivedata (type, type_id, time, min_rt, avg_rt, max_rt, resolution) SELECT type, type_id, AVG(time), MIN(rtime), AVG(rtime), MAX(rtime), ? FROM checkdata WHERE time > ? GROUP BY type, type_id;");
	$result = db_execute($archive_stmt, array(0, $time - 900));
	$runstats['rows_added'] += $result['count'];
	if (date("i") == '00') { //Run Every hour
		//Calulate min, max, avg for past hour and put into archive table
		$result = db_execute($archive_stmt, array(1, $time - 3600));
		$runstats['rows_added'] += $result['count'];
		//Delete 15 min data older than 7 days
		$deletearchive_stmt = db_prepare("DELETE FROM archivedata WHERE time < ? AND resolution = ?");
		$result = db_execute($deletearchive_stmt, array($time - 604800, 0));
		$runstats['rows_deleted'] += $result['count'];
		if (date("G") == '0') { //Run Every 24 hours
			//Calculate min, max, avg for past day and put into archive table
			$result = db_execute($archive_stmt, array(2, $time - 86400));
			$runstats['rows_added'] += $result['count'];
			//Delete hourly data older than 30 days
			$result = db_execute($deletearchive_stmt, array($time - 2592000, 1));
			$runstats['rows_deleted'] += $result['count'];
		}
		//Delete full data older than 24 hours
		$deleteck_stmt = db_prepare("DELETE FROM checkdata WHERE time < ?");
		$result = db_execute($deleteck_stmt, array($time - 86400));
		$runstats['rows_deleted'] += $result['count'];
		//Delete runstats older than 24 hours
		$deleterun_stmt = db_prepare("DELETE FROM runstats WHERE time < ?");
		$result = db_execute($deleterun_stmt, array($time - 86400));
		$runstats['rows_deleted'] += $result['count'];
	}
}

?>
