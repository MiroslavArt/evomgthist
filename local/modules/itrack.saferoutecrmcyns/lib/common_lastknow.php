<?

namespace Itrack\Saferoutecrmcyns;

use Bitrix\Main\Config\Option
	, Bitrix\Main\Loader;

if (!\Bitrix\Main\Loader::includeModule("crm")) {
	die('..not crm');
}
/*
1.0. Отменён	
1.1. Черновик	
1.2. Подтверждён	
1.3. Готов к отгрузке	
1.4. Отгружен отправителем	
1.5. В обработке	
3.1. Принят на сортировке	
3.2. Заказ передан в компанию доставки	
3.3. Передан на возврат	
4.1. Принят компанией доставки	
4.1.1. В пути	
4.1.2. В городе получателя	
4.2. На ПВЗ	
4.3. Выведен на доставку	
4.4. Вручен	
4.6. Перенос даты доставки	
4.6.1. Отказ до вручения	
4.6.2. Отказ при вручении	
4.7. Частично вручен	
5.1. Передан на возврат	
5.2. Возвращен на сортировку	
6.1. Принят на сортировку для возврата	
7.2. Возвращен	
7.4. Частично возвращен	
8.3. Ошибка	
0.1. Неизвестный статус	
https://bitrix.evomgt.org/local/modules/itrack.saferoutecrmcyns/req_service_answer.json
*/




class Common
{
	public static $propByOffer = '_';

	public function __construct()
	{

	}

	public static function InvoiceSyncCreate($arFields)
	{
		$ID = $arFields["ID"];

		$arOptionVal = self::getOption();

		$login = urlencode($arOptionVal["login_ie"]);
		$password = urlencode($arOptionVal["password_ie"]);

		//if (!$arFields["PR_INVOICE_9"] || !$arFields["PR_INVOICE_10"])
		//   return false;

		$data = array(
			"Number" => $ID,
			"Date" => date('c'),
			"WithNds" => false,
			"SumsWithNds" => false,
			"Comment" => $arFields["USER_DESCRIPTION"],
			"BankAccount" => array(
				"AccountNumber" => $arOptionVal["AccountNumber"],
				"Bank" => array(
					"Bik" => $arOptionVal["BankBik"],
				)
			),
			"Contractor" => array(
				"Name" => ($arFields["PR_INVOICE_11"]) ? $arFields["PR_INVOICE_11"] : $arFields["PR_INVOICE_1"],
				"Inn" => $arFields["PR_INVOICE_9"],
				"Kpp" => $arFields["PR_INVOICE_10"]
			),
			"Items" => array()
		);

		foreach ($arFields["PRODUCT_ROWS"] as $product) {
			$data["Items"][] = array(
				"ProductName" => $product["PRODUCT_NAME"],
				"UnitName" => $product["MEASURE_NAME"],
				"Quantity" => $product["QUANTITY"],
				"Price" => $product["PRICE"],
				"PriceWithoutNds" => $product["PRICE"],
				"Sum" => $product["PRICE"] * $product["QUANTITY"],
				"NdsRate" => 0
			);
		}
		AddMessage2Log("data: " . print_r($data, true), "SyncCreate");
		$data_string = json_encode($data);

		$ch = curl_init('https://service.localhost.ru/API/CreateBill.ashx');
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'X-Login: ' . $login,
			'X-Password: ' . $password,
			'Content-Type: application/json',
			'Content-Length: ' . strlen($data_string)
		));

		$result = curl_exec($ch);
		AddMessage2Log("result: " . print_r($result, true), "SyncCreate");
		global $USER_FIELD_MANAGER;
		$USER_FIELD_MANAGER->Update('CRM_INVOICE', $ID, array($arOptionVal["invoice_prop"] => $result));
		return true;
	}

	public static function getOption()
	{

		$define = self::getDefine();

		$option = [
			'AccountNumber' => Option::get($define['ID_MODULE'], "AccountNumber"),
			'PropByService' => Option::get($define['ID_MODULE'], "property_deals_sfID"),
			'login_ie' => Option::get($define['ID_MODULE'], "login_ie"),
			'password_ie' => Option::get($define['ID_MODULE'], "password_ie"),
			'make_record_timeline' => Option::get($define['ID_MODULE'], "make_record_timeline"),
			'invoice_prop' => Option::get($define['ID_MODULE'], "invoice_prop"),
			'property_tracknum' => Option::get($define['ID_MODULE'], "property_tracknum"),
			'property_urltracknum' => Option::get($define['ID_MODULE'], "property_urltracknum"),
			'property_nameoffer' => Option::get($define['ID_MODULE'], "property_nameoffer"),
			'property_companylog' => Option::get($define['ID_MODULE'], "property_companylog"),
			'property_wishdelivery' => Option::get($define['ID_MODULE'], "property_wishdelivery"),
			'setting_set_autochange' => Option::get($define['ID_MODULE'], "setting_set_autochange"),
			'setting_set_autochange_rule' => Option::get($define['ID_MODULE'], "setting_set_autochange_rule")
			/*
			property_tracknum
			property_urltracknum
			property_nameoffer
			property_companylog
			property_wishdelivery

			*/
		];

		return $option;

	}

	public static function getDefine()
	{
		$arr = [
			'ID_MODULE' => 'itrack.saferoutecrmcyns'
		];

		return $arr;
	}

	public static function syncHoockInvoice($status = 1, $rev = 0)
	{// Status 0 - не оплачен, 1 - оплачен, 2 - частично оплачен, 3 - отклонен

		$settingModule = self::getOption();
		$field_name = $settingModule['invoice_prop'];

		if ($field_name && \CModule::IncludeModule('crm')) {

			$syncurl = 'https://service.localhost.ru/API/GetChanges.ashx?fromRevision=' . $rev;
			$arInvoice = array();

			$login = urlencode($settingModule['login_ie']);
			$password = urlencode($settingModule['password_ie']);
			// Отслеживание изменений статусов счетов
			$ch = curl_init($syncurl);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array(
				'X-Login: ' . $login,
				'X-Password: ' . $password
			));
			curl_exec($ch);

			$result = json_decode(curl_exec($ch), true);
			foreach ($result as $invoice) {
				if ($invoice["Revision"] > $rev) $rev = $invoice["Revision"];
				if ($invoice["Status"] == $status) {
					$arInvoice[] = $invoice["Id"];
				}
			}
			// Ищем соответствующие счета в CRM
			if (count($arInvoice)) {
				AddMessage2Log("Counts: " . count($arInvoice), "AgentSyncCOUNT");
				$res = \CCrmInvoice::GetList(Array("ID" => "DESC"), Array($field_name => $arInvoice, 'CHECK_PERMISSIONS' => 'N', "!STATUS_ID" => "P"), Array("ID"));
				while ($arInv = $res->Fetch()) {
					// меняем статус счета на оплачен
					\CCrmInvoice::SetStatus($arInv["ID"], "P");
					AddMessage2Log("Status: " . $status . "\n" . print_r($arInv, true), "AgentSync");
				}
				AddMessage2Log("Counts: " . count($arInvoice), "AgentSyncCOUNT_REAL");
			}
		}

		return "\\Itrack\\Saferoutecrmcyns\\Common::syncHoockInvoice('" . $status . "', '" . $rev . "');";
	}


	public static function syncDealStateTrack()
	{


		$headers[] = 'Content-Type:application/json';
		$headers[] = "Authorization:Bearer ytlUVbdmTeae03SBCXZUwbwEko26kjak";
		$headers[] = "shop-id:76908";
		$headers = array_unique($headers);

		$url = "https://api.saferoute.ru/v2/tracking?orderId=10127344";
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "GET");
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		$response = json_decode(curl_exec($curl));
		$status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);
		if ($status === 200) {
			$return = json_encode(
				['status' => $status, 'data' => $response]
			);
		} else {
			/*$return=json_encode([
				'status' => $status,
				'code' => isset($response->code),
				$response->code : null,
			]);*/
		}

		print_r($return);

	}

	public static function syncHoockDeals($_varsSync='')
	{
		$settingModule = self::getOption();
		$lsDealStatus = self::getListStatusDealsCategory();
		self::$propByOffer = \Bitrix\Main\Config\Option::get(self::getDefine()['ID_MODULE'], "property_deals_sfID");
		$arrayIDDeals = self::getListDeals(self::$propByOffer,$_varsSync);
		// $ret = new CustomRandom($lsDealStatus, $arrayIDDeals, self::$propByOffer);
		// $ret = $ret->updatefuncByLoop();

		$arOptionsSync=ExtrequestOptionTable::query()
			// ->setSelect(["*","UF_*"])
			->setSelect(["NAME","MODULE_ID","VALUE"])
			->setFilter([
				"!VALUE" => ""
				,"!VALUE"=>'empty'
			])
			->where("MODULE_ID", self::getDefine()['ID_MODULE'])
			// ->setLimit(10)
			->exec()->fetchAll();

		// print_r($arrayIDDeals);

			$extclass = new Chstate($lsDealStatus
				,false //, $arrayIDDeals=[]
				, self::$propByOffer
				, $arOptionsSync
				, $settingModule);
			$ret = $extclass->updatefuncByLoop($arrayIDDeals);


		return self::$propByOffer;

	}

	public static function getListStatusDealsCategory()
	{
		$lsDealStatus = [];

		$ardealCat = array();
		$dealCatIterator = \Bitrix\Crm\Category\Entity\DealCategoryTable::query()
			// ->setSelect(["*","UF_*"])
			->setSelect(["ID", "NAME"])
			->setFilter([
				// 'ID'=>116371--,
				// "!$sPropSF" => ""
				// "!$sPropSF"=>''
			])
			->where("IS_LOCKED", "N")
			// ->setLimit(1)
			->exec()
			->fetchAll();
		foreach ($dealCatIterator as $siblingsElement) {
			$ardealCat[$siblingsElement['ID']] = $siblingsElement['NAME'];
		}

		foreach (array_keys($ardealCat) as $valdealCat) {
			$numgID = $valdealCat;
			$sgID = $numgID > 0 ? "DEAL_STAGE_$numgID" : "DEAL_STAGE";
			$arDealStatus = \CCrmStatus::GetStatusList($sgID);

			$lsDealStatus[$numgID]['empty'] = '--';
			foreach ($arDealStatus as $dealKey => $arItem) {
				$lsDealStatus[$numgID][$dealKey] = $arItem;
			}
		}
		return $lsDealStatus;

	}

	public static function getListDeals($sPropSF,$f_varsSync)
	{


		$arrayIDDeals = [];
		/*		$obDealList = \CCrmDeal::GetList(
					array()
					, ["!$sPropSF" => ""]
					, $arSelect = ['ID', 'STAGE_ID', 'TITLE', 'COMPANY_ID', 'CONTACT_FULL_NAME', 'OPPORTUNITY', 'CURRENCY_ID', $sPropSF]
				);
				while ($odeal = $obDealList->Fetch()) {
					if (true or is_numeric($odeal[$sPropSF])) {
						$arrayIDDeals[] = $odeal;
					}

				}*/
		if(!empty($f_varsSync)){
			if(is_array($f_varsSync)){

			}else{
				$f_varsSync=[$f_varsSync];
			}
		}


		$_arSetFilter=(!empty($f_varsSync))?[
			'ID'=>$f_varsSync,
			// "!$sPropSF" => ""
			// "!$sPropSF"=>''
		]:[
			// 'ID'=>118051,
			">=DATE_CREATE"=>new \Bitrix\Main\Type\DateTime("04.14.2020 00:00:00")
		];

		$arrayIDDeals = \Bitrix\Crm\DealTable::query()
			->setSelect(["ID", "STAGE_ID", $sPropSF, "CATEGORY_ID"])
			// ->addSelect('CATEGORY_ID')	b_crm_deal_category
			// ->setSelect(["*","UF_*"])
			// ->setSelect(["ID","STAGE_ID","TITLE", "COMPANY_ID", "OPPORTUNITY", "CURRENCY_ID",$sPropSF,"CATEGORY_ID"])
			->setFilter($_arSetFilter)
			// ->where('ID', 116371)
			// ->setLimit(2)
			->exec()
			->fetchAll();

		return $arrayIDDeals;

	}


}


?>