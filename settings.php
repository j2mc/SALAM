<?php

/* 
settings.php for SALAM v2
By Jacob McEntire
Copyright 2015
*/

require_once("library/SSI.php");

page_start('Settings');

$site_stmt = db_prepare("SELECT id, name, subnet FROM sites");
db_execute($site_stmt);
$site_result = db_fetch($site_stmt);
if (!empty($site_result)) {
	echo '<div class="panel panel-default">
	<div class="panel-heading"><h4>Sites</h4></div>
	<table class="table table-hover">';
	$host_count_stmt = db_prepare("SELECT COUNT(id) FROM hosts WHERE site_id = ?");
	$enabled_count_stmt = db_prepare("SELECT COUNT(id) FROM hosts WHERE site_id = ? AND enabled = 1");
	foreach ($site_result as $site) {
		db_execute($host_count_stmt, array($site['id']));
		$host_count = db_fetch($host_count_stmt, 'col');
		db_execute($enabled_count_stmt, array($site['id']));
		$enabled_count = db_fetch($enabled_count_stmt, 'col');
		echo '<tr><td><strong>', $site['name'], '</strong> (', $enabled_count, ' of ', $host_count, ' Enabled)<small>', $site['subnet'], '</small></td><td align="right"><!--<a href="addsite.php?action=edit&amp;id=', $site['id'], '" class="btn btn-warning"><span class="glyphicon glyphicon-pencil"></span></a>--> <a href="#" data-toggle="modal" data-target="#deletemodal" data-siteid="', $site['id'], '", class="btn btn-danger"><span class="glyphicon glyphicon-trash"></span></a></td></tr>';
	}
	echo '</table></div>';
} else {
	echo '<h4>No Sites Found</h4>';
}
echo '
	<a href="addsite.php" class="btn btn-primary btn-lg">Add Site</a>
	
	<div class="modal fade" id="deletemodal" data-backdrop="static" data-keyboard="false" tabindex="-1" role="dialog" aria-labelledby="deletmodalLabel" aria-hidden="true">
		<div class="modal-dialog">
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