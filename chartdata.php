<?php

/* 
detail.php for SALAM v2
By Jacob McEntire
Copyright 2018
*/

require("library/SSI.php");

if (isset($_GET['type']) && isset($_GET['id']) && isset($_GET['range'])) {
	$type = check_xss($_GET['type']);
	$id = check_xss($_GET['id']);
	$range = check_xss($_GET['range']);
	$chartdata['type'] = 'line';
	if ($range == 1) {
		$titlerange = '24 Hours';
		$timeunit = 'hour';
		$checkdata_stmt = db_prepare("SELECT time, rtime FROM checkdata WHERE type = ? AND type_id = ? and time > ?");
		db_execute($checkdata_stmt, array($type, $id, time() - 86400));
		$checkdata = db_fetch($checkdata_stmt);
		if (!empty($checkdata)) {
			foreach($checkdata as $d) {
				$data[] = array('t' => $d['time'] * 1000, 'y' => $d['rtime'] / 1000);
			}
			$chartdata['data']['datasets'][] = array (
				'backgroundColor' => '#eca9a7',
				'borderColor' => '#d9534f',
				'borderWidth' => 2,
				'pointRadius' => 0,
				'pointHitRadius' => 10,
				'data' => $data
			);
		}
	}
	else {
		if ($range == 7) {
			$resolution = 0;
			$titlerange = '7 Days';
			$timeunit = 'day';
		}
		elseif ($range == 30) {
			$resolution = 1;
			$titlerange = '30 Days';
			$timeunit = 'day';
		}
		else {
			$resolution = 2;
			$titlerange = 'Year';
			$timeunit = 'month';
		}
		$range *= 86400;
		$archivedata_stmt = db_prepare("SELECT time, min_rt, avg_rt, max_rt FROM archivedata WHERE type = ? AND type_id = ? AND time > ? AND resolution = ? ORDER BY time ASC");
		db_execute($archivedata_stmt, array($type, $id, time() - $range, $resolution));
		$archivedata = db_fetch($archivedata_stmt);
		if (!empty($archivedata)) {
			foreach($archivedata as $d) {
				$mindata[] = array('t' => $d['time'] * 1000, 'y' => $d['min_rt'] / 1000);
				$avgdata[] = array('t' => $d['time'] * 1000, 'y' => $d['avg_rt'] / 1000);
				$maxdata[] = array('t' => $d['time'] * 1000, 'y' => $d['max_rt'] / 1000);
			}
			$chartdata['data']['datasets'][] = array (
				'label' => 'Minimum',
				'backgroundColor' => '#addbad',
				'borderColor' => '#5cb85c',
				'borderWidth' => 2,
				'pointRadius' => 0,
				'data' => $mindata
			);
			$chartdata['data']['datasets'][] = array (
				'label' => 'Average',
				'backgroundColor' => '#a0c5e4',
				'borderColor' => '#428bca',
				'borderWidth' => 2,
				'pointRadius' => 0,
				'data' => $avgdata
			);
			$chartdata['data']['datasets'][] = array (
				'label' => 'Maximum',
				'backgroundColor' => '#eca9a7',
				'borderColor' => '#d9534f',
				'borderWidth' => 2,
				'pointRadius' => 0,
				'pointHitRadius' => 10,
				'data' => $maxdata
			);
		}
	}
	$xAxes[] = array('type' => 'time', 'display' => true, 'time' => array('unit' => $timeunit));
	$yAxes[] = array('display' => true, 'scaleLabel' => array('display' => true, 'labelString' => 'ms'), 'ticks' => array('suggestedMax' => 10));
	$chartdata['options'] = array(
		'title' => array('display' => 'true', 'text' => 'Response Time Last ' . $titlerange),
		'scales' => array('xAxes' => $xAxes, 'yAxes' => $yAxes),
		'legend' => array('display' => false),
		'elements' => array('line' => array('tension' => 0))
	);
	echo json_encode($chartdata);
}
?>