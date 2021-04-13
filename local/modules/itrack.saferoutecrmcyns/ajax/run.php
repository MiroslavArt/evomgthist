<?php
$islcl = false;

define("NOT_CHECK_PERMISSIONS", true);
define("STOP_STATISTICS", true);
define("NO_AGENT_STATISTIC", "Y");
define("NO_AGENT_CHECK", true);
define("NO_KEEP_STATISTIC", true);


$answer = Array();
$answer["success"] = false;

if ($_POST['login']) { // обманка - защита от спама
	echo json_encode($answer);
	die();
}
if ($_SERVER["REQUEST_METHOD"] != "POST") {
	header('HTTP/1.0 403 Forbidden');
	echo 'You are forbidden';
	die('');
}
if (!isset($_POST['query'])) {
	header('HTTP/1.0 403 Forbidden');
	echo 'You are forbidden';
	die('');
}
if ($_POST['query'] != 'simulateandtrainservice') {
	header('HTTP/1.0 403 Forbidden');
	echo 'You are forbidden';
	die('');
}
$answer["utime"] = time();
$answer["time"] = date('d-m-Y H:m:s');
$answer["exectimesecond"] = 0;
$time_start = microtime(true);
$module_id = "itrack.saferoutecrmcyns";
$arrayIDDeals = [];

// ---------------------------------

require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

// print_r($arrayIDDeals);

// ---------------------------------
// $ret = new Itrack\Saferoutecrmcyns\Chstate($lsDealStatus, $arrayIDDeals, $psync);
// print_r($ret);

$ret=CModule::IncludeModule('itrack.saferoutecrmcyns');
$ret=Itrack\Saferoutecrmcyns\Common::syncHoockDeals();

if ($_POST['action'] = "search-val") {
	// $answer = Classowner::search($_REQUEST['query']);
}
print_r($ret);
// echo json_encode($answer);
