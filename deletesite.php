<?php

/* 
deletesite.php for SALAM v2
By Jacob McEntire
Copyright 2018
*/

require_once("library/SSI.php");

if (isset($_GET['id'])) {
	$site_id = check_xss($_GET['id']);
	$site_stmt = db_prepare("SELECT name FROM sites WHERE id = ?");
	db_execute($site_stmt, array($site_id));
	$site_name = db_fetch($site_stmt, 'col');
	if (isset($_POST['confirmed'])) {
		$delete_stmt = db_prepare("DELETE FROM sites WHERE id = ?");
		$result = db_execute($delete_stmt, array($site_id));
		$delete_stmt = db_prepare("DELETE FROM hosts WHERE site_id NOT IN (SELECT id FROM sites)");
		db_execute($delete_stmt);
		$delete_stmt = db_prepare("DELETE FROM services WHERE host_id NOT IN (SELECT id FROM hosts)");
		db_execute($delete_stmt);
		$delete_stmt = db_prepare("DELETE FROM hostdata WHERE host_id NOT IN (SELECT id FROM hosts)");
		db_execute($delete_stmt);
		$delete_stmt = db_prepare("DELETE FROM checkdata WHERE type = 'host' AND type_id NOT IN (SELECT id FROM hosts)");
		db_execute($delete_stmt);
		$delete_stmt = db_prepare("DELETE FROM checkdata WHERE type = 'service' AND type_id NOT IN (SELECT id FROM services)");
		db_execute($delete_stmt);
		$delete_stmt = db_prepare("DELETE FROM alerts WHERE type = 'host' AND type_id NOT IN (SELECT id FROM hosts)");
		db_execute($delete_stmt);
		$delete_stmt = db_prepare("DELETE FROM alerts WHERE type = 'service' AND type_id NOT IN (SELECT id FROM services)");
		db_execute($delete_stmt);
		$delete_stmt = db_prepare("DELETE FROM alerts WHERE type = 'site' AND type_id NOT IN (SELECT id FROM sites)");
		db_execute($delete_stmt);
		echo '
		<div class="modal-body">';
		if ($result['result'])
			echo '<h4>', $site_name, ' has been deleted</h4>';
		else
			echo '<h4>', $site_name, ' was unable to be deleted due to an error</h4>';
		echo '
		<div class="modal-footer">
			<a href="settings.php" class="btn btn-default">Close</a>
		</div>';
	}
	else {
		echo '
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title" id="myModalLabel">Delete Site?</h4>
			</div>
			<form id="deletesite" action="deletesite.php?id=', $site_id, '&amp;name=', $site_name, '" method="POST">
			<div class="modal-body">
				<h4>Are you sure you want to delete site ', $site_name, '?</h4>
				<input type="checkbox" name="confirmed" onchange="document.getElementById(\'deletebutton\').disabled = !this.checked;"> I understand this will remove all hosts and data related to this site.<br />
			</div>
			<div class="modal-footer">
				<button type="submit" id="deletebutton" class="btn btn-danger" disabled="disabled"><span class="glyphicon glyphicon-trash"></span> Delete</button>
				<button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
			</div>
			</form>
		</div>';
	}
}

?>