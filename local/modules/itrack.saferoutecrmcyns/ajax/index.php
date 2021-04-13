<?php
$islcl = false;
// $boolVeryFast = true;
$boolVeryFast = false;
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
if ($boolVeryFast) {
	require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

	CUtil::JSPostUnescape();


	$ret = CModule::IncludeModule($module_id);
	$sPropSF = "UF_CRM_1604397642";


	\Bitrix\Main\Loader::includeModule('crm');
	$arrayIDDeals = \Bitrix\Crm\DealTable::query()
		// ->addSelect('CATEGORY_ID')	b_crm_deal_category
		// ->setSelect(["*","UF_*"])
		->setSelect([
				// "ID","STAGE_ID","TITLE", "COMPANY_ID", "OPPORTUNITY", "CURRENCY_ID",$sPropSF,"CATEGORY_ID"]
				"ID", "STAGE_ID", $sPropSF]
		)
		->setFilter([
			// 'ID'=>116371,
			"!$sPropSF" => ''
		])
		// ->where('ID', 116371)
		// ->setLimit(1)
		->exec()
		->fetchAll();
} else {
	$arrayIDDeals = [];
	require_once $_SERVER['DOCUMENT_ROOT'] . "/bitrix/php_interface/dbconn.php";
	$link = mysqli_connect($DBHost, $DBLogin, $DBPassword, $DBName);
	$query = "SELECT 
				`crm_deal`.`ID` AS `ID`,
				`crm_deal`.`STAGE_ID` AS `STAGE_ID`
				 ,`crm_deal_uts_object`.`UF_CRM_1604397642` 
				-- ,`crm_deal_uts_object`.`VALUE_ID` AS `UALIAS_0`
			FROM `b_crm_deal` `crm_deal` 
			LEFT JOIN `b_uts_crm_deal` `crm_deal_uts_object` ON `crm_deal`.`ID` = `crm_deal_uts_object`.`VALUE_ID`
			WHERE (`crm_deal_uts_object`.`UF_CRM_1604397642` IS NOT NULL AND LENGTH(`crm_deal_uts_object`.`UF_CRM_1604397642`) > 0)
			";
	$_res = mysqli_query($link, $query);
	while ($row = mysqli_fetch_assoc($_res)) {
		$arrayIDDeals[] = $row;
	}
	mysqli_close($link);
	// print_r($arrayIDDeals);
}
$answer["countdealstotal"] = count($arrayIDDeals);
// print_r($arrayIDDeals);

// ---------------------------------
// $ret = new Itrack\Saferoutecrmcyns\Chstate($lsDealStatus, $arrayIDDeals, $psync);
// print_r($ret);

$cntpositive = $cntnegative = 0;
$arData = $arhistoryState = [];
foreach ($arrayIDDeals as $key => $val) {
	$arInf = [];
	$strEnum = $val["UF_CRM_1604397642"];
	if (empty($strEnum)) {
		continue;
	}
	// $headers[] = 'Content-Type:application/json';
	// $headers[] = "Authorization:Bearer ytlUVbdmTeae03SBCXZUwbwEko26kjak";
	// $headers[] = "shop-id:76908";
	$headers[] = 'User-Agent: Mozilla/5.0 (ITrack dev bitrix24) Chrome/86.0.4240.183 Safari/537.36';
	$headers[] = 'Accept-Language: ru-RU,ru;q=0.9,en-US;q=0.8,en';
	$headers[] = 'Cookie: _ym_uid=16046572401012452973; _ym_d=1647657240; _ga=GA1.2.763129142.1602357240';
	$headers = array_unique($headers);

	if (is_numeric($val["UF_CRM_1604397642"]))
	{
		$url = "https://api.saferoute.ru/api/_scxsipqyysphcdqsgima5045gweib8y/order/info.json?order_id=" . $val["UF_CRM_1604397642"];
	}
	else
	{
		$url = "https://api.saferoute.ru/api/_scxsipqyysphcdqsgima5045gweib8y/order/info.json?widget_id=" . $val["UF_CRM_1604397642"];
	}
	
	//$url = "https://api.saferoute.ru/api/_scxsipqyysphcdqsgima5045gweib8y/order/info.json?widget_id=" . $val["UF_CRM_1604397642"];
	$curl = curl_init($url);
	curl_setopt($curl, CURLOPT_HEADER, false);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "GET");
	curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
	$response = json_decode(curl_exec($curl), true);
	unset($response['data']["logo"]);
	$status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
	$arhistoryState[] = $status;
	curl_close($curl);
	if ($status === 200) {

		$return = json_encode(
			['status' => $status, 'data' => count($response)]
		);
	} else {
		$return = json_encode([
			'data' => '',
			'status' => $status,
			'code' => isset($response->code) ? $response->code : null,
		]);
	}

	$arProds = array_map(function($a) {
		// print_r($a);
		return [
			'name'=>$a['name'],
			'count'=>$a['count'],
			'price_declared'=>$a['price_declared'],
			'vendor_code'=>$a['vendor_code']
		];
	},array_values($response['data']['products']));

	$arInf[$val['ID']] = [
		'info' => $response['data']['status_history']
		,'track_number' => $response['data']['track_number']
		,'tracking_url' => $response['data']['tracking_url']	
		,'delivery_date' => $response['data']['delivery_date']
		,'products' => $arProds
		,'company' => $response['data']['company']
		,'order_id' => $response['data']['order_id']
	];
	if ($response['status'] == 'error') {
		$arInf[$val['ID']] = ['error' => $response['message']];
		$cntnegative++;
	} elseif ($response['status'] == 'not_found') {
		$arInf[$val['ID']] = ['error' => "not_found"];
		$arInf[$val['ID']] = ['info' => "not_found"];
		$cntnegative++;
	} else {
		$cntpositive++;
	}
	// print_r($response);
	$arData[] = $arInf;
	unset($response);
}

$answer["response"]['pos'] = $cntpositive;
$answer["response"]['neg'] = $cntnegative;
$answer["response"]['historyanswerserver'] = $arhistoryState;
$answer["exectimesecond"] = round((microtime(true) - $time_start), 4);

$pathmodule = count(glob($_SERVER['DOCUMENT_ROOT'] . '/local/modules/' . $module_id)) > 0 ?
	$_SERVER['DOCUMENT_ROOT'] . '/local/modules/' . $module_id :
	$_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/' . $module_id;

file_put_contents($pathmodule . '/req_service_answer.json', "" . json_encode($arData) . '');


if ($_POST['action'] = "search-val") {
	// $answer = Classowner::search($_REQUEST['query']);
}

echo json_encode($answer);
