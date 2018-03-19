<?php

/* 
settings.php for SALAM v2
By Jacob McEntire
Copyright 2015
*/

require_once("library/SSI.php");

if (isset($_GET['mailtest'])) {
	global $from_email, $to_email;
	$headers  = 'MIME-Version: 1.0' . "\r\n";
	$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
	$headers .= 'From: SALAM <' . $from_email . '>';
	$subject = 'TEST EMAIL';
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
				  <font color="#555555" face="Tahoma" size="4">TEST EMAIL </font><br /><font color="#555555" face="Tahoma" size="2">' . date("r") . '</font>
				</td></tr>
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
		$_SESSION['alert_type'] = 'success';
		$_SESSION['alert'] = 'Test Email Sent to ' . $to_email;
	}
	else {
		$_SESSION['alert_type'] = 'danger';
		$_SESSION['alert'] = 'Test Email Failed: ' . $sent;
	}
}

$breadcrumb = array(
	"active" => "Settings"
);

page_start($breadcrumb);

$site_stmt = db_prepare("SELECT id, name, subnet FROM sites");
db_execute($site_stmt);
$site_result = db_fetch($site_stmt);
echo '
<div class="container">
	<div class="card m-3">
		<h4 class="card-header">Sites</h4>';
if (!empty($site_result)) {
	echo '
		<ul class="list-group list-group-flush">';
	$host_count_stmt = db_prepare("SELECT COUNT(id) FROM hosts WHERE site_id = ?");
	$enabled_count_stmt = db_prepare("SELECT COUNT(id) FROM hosts WHERE site_id = ? AND enabled = 1");
	foreach ($site_result as $site) {
		db_execute($host_count_stmt, array($site['id']));
		$host_count = db_fetch($host_count_stmt, 'col');
		db_execute($enabled_count_stmt, array($site['id']));
		$enabled_count = db_fetch($enabled_count_stmt, 'col');
		echo '
		<li class="list-group-item d-flex align-items-center justify-content-between">
			<strong>', $site['name'], '</strong>
			<small>(', $enabled_count, ' of ', $host_count, ' Enabled)  ', $site['subnet'], '</small>
			<!--<a href="addsite.php?action=edit&amp;id=', $site['id'], '" class="btn btn-warning"><i data-feather="edit"></i></a>-->
			<a href="#" data-toggle="modal" data-target="#deletemodal" data-siteid="', $site['id'], '", class="btn btn-small btn-outline-danger"><span class="oi oi-trash" title="Delete" aria-hidden="true"></span></a></li>';
	}
	echo '</ul>';
	
} else {
	echo '<h4 class="card-title text-center">No Sites Found</h4>';
}
echo '
		<div class="card-footer text-center">
			<a href="addsite.php" class="btn btn-outline-primary">Add Site</a><br />
		</div>
	</div>
	<div class="card m-3">
		<h4 class="card-header">Other Functions</h4>
		<div class="card-body">
			<a href="settings.php?mailtest" class="btn btn-outline-secondary">Send Test Email</a>
		</div>
	</div>
	<div class="card m-3">
		<h4 class="card-header">Last 10 Run Stats</h4>
		<table class="table table-hover table-sm table-responsive text-center">
			<thead>
				<tr>
					<th>Time</th><th>Runtime</th><th>Hosts/Services Checked</th><th>Warnings Detected/Confirmed</th><th>Critical Detected/Confirmed</th><th>Emails Sent</th><th>Archive Rows Added/Deleted</th>
				</tr>
			</thead>
			<tbody>';
$runstat_stmt = db_prepare("SELECT * FROM runstats ORDER BY time DESC LIMIT 10");
db_execute($runstat_stmt);
$runstats = db_fetch($runstat_stmt);
foreach($runstats as $stat) {
	echo '<tr>
		<td>', date("H:i:s", $stat['time']), '</td>
		<td>', round($stat['runtime'], 2), ' sec</td>
		<td>', $stat['hosts'], ' / ', $stat['services'], '</td>
		<td>', $stat['warning'], ' / ', $stat['warning_conf'], '</td>
		<td>', $stat['critical'], ' / ', $stat['critical_conf'], '</td>
		<td>', $stat['email_sent'], '</td>
		<td>', $stat['rows_added'], ' / ', $stat['rows_deleted'], '</td>
	</tr>';
}
echo '
			</tbody>
		</table>
	</div>
</div>
	<div class="modal fade" id="deletemodal" tabindex="-1" role="dialog" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered" role="document">
		</div>
	</div>
';

$endscript = '
<script src="js/jquery.form.min.js"></script>
<script>
	$("#deletemodal").on("show.bs.modal", function (event) {
		var button = $(event.relatedTarget)
		var site_id = button.data("siteid")
		var modal = $(this)
		modal.find(".modal-dialog").load("deletesite.php?id=" + site_id)
	})
	$(document).on( "submit", "form", (function(event) {
		$(this).ajaxSubmit({target:$(this)});
		$(this).html(\'<div class="modal-body"><img src="img/gears.svg" class="center-block" /><h3 class="text-center">Deleting...Please Wait</h3></div>\');
		return false;
	}));
	</script>
';
	
page_end($endscript);

?>