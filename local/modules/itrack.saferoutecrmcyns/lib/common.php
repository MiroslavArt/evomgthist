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
	public static $module_id = 'itrack.saferoutecrmcyns';
	public static $propByOffer = '';
	public static $argetOptions = [];
	public static $ardataLog = [];
	public static $arLogSync = [];
	public static $boolMake_record_timeline = [];
	public static $boolAutoChangeStage = [];
	public static $boolAutoChangeStageRule = [];
	public static $boolChangeStage = [];
	public static $idThisUser = 53;
	public static $arStagesAtNeedRunAgent=['C41:11','C41:12'];
	/*
	 * Надо
	 * Доставляются в Saferout	C41:11
	 * На ПВЗ в Saferoute	C41:12
	 * */

	public static $debug = true;

	public function __construct()
	{

	}

	public function syncHoockDeals($incomingparam = [])
	{
		self::$propByOffer = \Bitrix\Main\Config\Option::get(self::$module_id, 'property_deals_sfID');
		$arListDeals = self::funcarListDeals($incomingparam);
		self::$argetOptions = self::funcargetOptions();

		$getUseCategory = function ($a) {
			$arCats = [];
			foreach ($a as $val) {
				$arCats[] = $val['CATEGORY_ID'];
			}
			return $arCats;
		};
		/*
		 *Какие статусы есть у сделок
		 *
		 * array (
			  41 =>
			  array (
				'empty' => '--',
				'C41:13' => '',
				'C41:NEW' => 'ЗАЯВКИ',
				'C41:PREPARATION' => 'НЕ ДОЗВОНИЛИСЬ',
				'C41:EXECUTING' => 'ДОСТАВЛЯЮТСЯ',
				'C41:7' => 'Не отображен в сэйфроут',
				'C41:3' => 'ОТПРАВЛЕН ТРЕК НОМЕР',
				'C41:9' => 'Подготовленные в Saferoute',
				'C41:11' => 'Доставляются',
				'C41:12' => 'На ПВЗ',
				'C41:4' => 'ВОЗВРАТ ПОЧТЫ РОССИИ',
				'C41:5' => 'ВОЗВРАТЫ СЭЙФ РОУТ',
				'C41:WON' => 'Сделка успешна',
				'C41:LOSE' => 'Сделка провалена',
			  ),
			)
		 *
		 * */
		$arDealStatuses = self::funcarDealStatuses($getUseCategory($arListDeals));
		self::$ardataLog = self::funcardataLog();

		self::$boolMake_record_timeline = (self::$argetOptions['make_record_timeline'][0]['VALUE'] == 'Y') ? True : False;
		self::$boolAutoChangeStage = (self::$argetOptions['setting_set_autochange'][0]['VALUE'] == 'Y') ? True : False;
		self::$boolChangeStage = (self::$argetOptions['setting_set_change'][0]['VALUE'] == 'Y') ? True : False;

		self::$boolAutoChangeStageRule = (self::$argetOptions['setting_set_autochange_rule'][0]['VALUE'] == 'Y') ? True : False;
		if (self::$debug) {
			self::$arLogSync[] = 'Запись в коммент сделки разрешена boolMake_record_timeline : ' . (self::$boolMake_record_timeline);
			self::$arLogSync[] = 'Разрешено менять стадию сделки - boolChangeStage :' . (self::$boolChangeStage);
			self::$arLogSync[] = 'Разрешено менять _по каким-то правилам_ стадию сделки - boolAutoChangeStage :' . (self::$boolAutoChangeStage);
			self::$arLogSync[] = 'Разрешено если стадию не обнаружена из параметров подбирать ближайшую - boolAutoChangeStageRule :' . (self::$boolAutoChangeStageRule);
		}
		self::$arLogSync[] = 'количество сделок со свойством :' . count($arListDeals);

		self::funcupdateByLoop($arListDeals);

		print_r(self::$arLogSync);
		self::writeLogDebug(self::$arLogSync);

	}

	/**
	 * @return array
	 */
	public function funcarListDeals($varfields = [])
	{
		$arSetFilter = $arrayIDDeals = [];
		$sPropSF = self::$argetOptions['property_deals_sfID'][0]['VALUE'];
		$sPropSF = self::$propByOffer;


		if (!empty($varfields)) {
			$varfields = is_array($varfields) ? $varfields : [$varfields];
			$arSetFilter = ['ID' => $varfields,
				"!$sPropSF" => ""
			];
		} else {
			$arSetFilter = [">=DATE_CREATE" => new \Bitrix\Main\Type\DateTime("04.12.2020 00:00:00"),
				"!$sPropSF" => ""
			];

		}
		self::$arLogSync[] = empty($varfields);
		self::$arLogSync[] = $arSetFilter;


		$arrayIDDeals = \Bitrix\Crm\DealTable::query()
			->setSelect(["ID", "STAGE_ID", $sPropSF, "CATEGORY_ID"])
			->setFilter($arSetFilter)
			// ->where('ID', 116371)
			// ->setLimit(2)
			->exec()
			->fetchAll();
		// print_r($arrayIDDeals);

		return $arrayIDDeals;

	}

	/**
	 * @return array
	 */
	public static function funcargetOptions()
	{
		$arOptionsSync = ExtrequestOptionTable::query()
			->setSelect(["NAME", "MODULE_ID", "VALUE"])
			->setFilter([
				"!VALUE" => ""
				, "!VALUE" => 'empty'
			])
			->where("MODULE_ID", self::$module_id)
			->exec()->fetchAll();
		$arOpt = [];
		foreach ($arOptionsSync as $val) {
			$arOpt[$val['NAME']][] = $val;
		}
		return $arOpt;
	}

	/**
	 * @return array
	 */
	public static function funcarDealStatuses($f_UseCategory = [])
	{
		$lsDealStatus = [];

		$ardealCat = array();
		$dealCatIterator = \Bitrix\Crm\Category\Entity\DealCategoryTable::query()
			->setSelect(["ID", "NAME"])
			->setFilter(['ID' => $f_UseCategory])
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

	/**
	 * @param
	 * @return array
	 */
	public static function funcardataLog()
	{
		$pathModule = count(glob($_SERVER['DOCUMENT_ROOT'] . '/local/modules/' . self::$module_id)) > 0 ?
			'/local/modules/' . self::$module_id :
			'/bitrix/modules/' . self::$module_id;

		$strInf = file_get_contents(sprintf("%s%s/req_service_answer.json", $_SERVER['DOCUMENT_ROOT'], $pathModule));
//		$strInf = file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/local/modules/itrack.saferoutecrmcyns/req_service_answer.json');

		$ar = json_decode($strInf, true);
		// $ar = \Bitrix\Main\Web\Json::decode($strInf);
		$arInf = [];
		array_walk($ar, function ($v, $k) use (&$arInf) {
			$numD = array_keys($v)[0];
			$arInf[$numD] = $v[$numD];
		});

		return $arInf;

	}

	/**
	 * @param arDeals db
	 */
	public static function funcupdateByLoop($f_arListDeals)
	{
		foreach ($f_arListDeals as $vID) {
			self::$debug && self::$arLogSync[] = "\n" . '---------V---------начало обработки сделки"' . $vID['ID'] . '" с текущей стадией ' . $vID['STAGE_ID'];
			self::execfunc($vID['ID'], $vID['STAGE_ID'], $vID);
		}
	}

	/**
	 * @param ID Dals , stage, db arDeal
	 */
	public static function execfunc($strID = '', $currentStage, $arcurrentDeal)
	{
		if (empty($strID) || empty($currentStage)) {
			return;
		}

		self::UpdateDeliveryInf($arcurrentDeal);

		self::funcUpdateStage($currentStage, $arcurrentDeal);


	}

	/**
	 * @return string
	 */
	public static function UpdateDeliveryInf($f_arListDeals)
	{

		if (empty(self::$argetOptions['property_tracknum']) or
			empty(self::$argetOptions['property_urltracknum']) or
			empty(self::$argetOptions['property_nameoffer']) or
			empty(self::$argetOptions['property_companylog'])) {
			return;
		}

		$ardataLogDeal = self::$ardataLog[$f_arListDeals['ID']];

		$arInfBook = self::selectBooksForUFDeal(
			$ardataLogDeal, []);

		$arSelect = ["ID"
			, self::$argetOptions['property_tracknum'][0]['VALUE']
			, self::$argetOptions['property_wishdelivery'][0]['VALUE']
			, self::$argetOptions['property_companylog'][0]['VALUE']
			, self::$argetOptions['property_urltracknum'][0]['VALUE']
			, self::$argetOptions['property_nameoffer'][0]['VALUE']
		];
		$arrayIDDeals = \Bitrix\Crm\DealTable::query()
			// ->addSelect('CATEGORY_ID')	b_crm_deal_category
			// ->setSelect(["*","UF_*"])
			// ->setSelect(["ID","STAGE_ID","TITLE", "COMPANY_ID", "OPPORTUNITY", "CURRENCY_ID",$sPropSF,"CATEGORY_ID"])
			->setSelect($arSelect)
			// ->setFilter([
			// 'ID'=>$farcurrentDeal['ID'],
			// ])
			->where('ID', $f_arListDeals['ID'])
			// ->setLimit(2)
			->exec()
			->fetch();
		$boolUpdateInfDelivery = false;
		if ($arrayIDDeals[self::$argetOptions['property_nameoffer'][0]['VALUE']] != $arInfBook && !empty($arInfBook)) {
			$arUpdate[self::$argetOptions['property_nameoffer'][0]['VALUE']] = $arInfBook;

			$boolUpdateInfDelivery = true;
		}
			self::$debug && self::$arLogSync[] ="..track ". $f_arListDeals['ID']." UF:".$arUpdate['UF_CRM_1606464347828'];
			self::$debug && self::$arLogSync[] ="..track ". $f_arListDeals['ID']."  SF:".$ardataLogDeal['track_number'];
		if ($arrayIDDeals[self::$argetOptions['property_tracknum'][0]['VALUE']] != $ardataLogDeal['track_number'] && !empty($ardataLogDeal['track_number'])) {
			$arUpdate['UF_CRM_1606464347828'] = $ardataLogDeal['track_number'];
			
			$boolUpdateInfDelivery = true;
		}
		if ($arrayIDDeals[self::$argetOptions['property_wishdelivery'][0]['VALUE']] != $ardataLogDeal['delivery_date'] && !empty($ardataLogDeal['delivery_date'])) {
			$arUpdate[self::$argetOptions['property_wishdelivery'][0]['VALUE']] = \Bitrix\Main\Type\DateTime::createFromUserTime($ardataLogDeal['delivery_date']);
			$boolUpdateInfDelivery = true;
		}
		if ($arrayIDDeals[self::$argetOptions['property_companylog'][0]['VALUE']] != $ardataLogDeal['company'] && !empty($ardataLogDeal['company'])) {
			$arUpdate[self::$argetOptions['property_companylog'][0]['VALUE']] = $ardataLogDeal['company'];
			$boolUpdateInfDelivery = true;
		}
		if ($arrayIDDeals['UF_CRM_1606465706750'] != $ardataLogDeal['tracking_url'] && !empty($ardataLogDeal['tracking_url'])) {
			$arUpdate['UF_CRM_1606465706750'] = $ardataLogDeal['tracking_url'];
			$boolUpdateInfDelivery = true;
		}
		if ($boolUpdateInfDelivery) {
			self::$debug && self::$arLogSync[] = [$f_arListDeals['ID'] => $arUpdate];
			$obDeal = new \CCrmDeal(false);

			if (!$obDeal->Update($f_arListDeals['ID']
				, $arUpdate
				, $bCompare = true
				, $bUpdateSearch = true
				, $options = array(
					"ENABLE_SYSTEM_EVENTS" => false
				, "REGISTER_STATISTICS" => false
//					,"CURRENT_USER"=>1
				))) {
				self::$debug && self::$arLogSync[] = $obDeal->LAST_ERROR;
				// print_r($obDeal->LAST_ERROR);
				// $this->WriteToTrackingService('Ошибка обновления сделки : '.$obDeal->LAST_ERROR);
			} else {
				self::$debug && self::$arLogSync[] = 'UpdateInfDelivery Cделка ' . $f_arListDeals['ID'] . ' обновлена. ';





				// print_r($arUpdate);
				// $this->WriteToTrackingService('Cделка обновлена. Поля: '.print_r($arUpdate, true));
			}
		} else {
			self::$debug && self::$arLogSync[] = 'UpdateInfDelivery На сделке ' . $f_arListDeals['ID'] . ' нечего обновлять в UF ';
		}
	}

	/**
	 * @return
	 */
	public static function selectBooksForUFDeal($f_ardataLog)
	{
		$numIDBlock_withBOOKCatalog = 35;
		$strPropertyWithArtucul = "PROPERTY_154";

		$getprod = array_map(function ($a) {
			return [
				'name' => $a['name'],
				'count' => $a['count'],
				'price_declared' => $a['price_declared'],
				'vendor_code' => $a['vendor_code'],
			];
		}, array_values($f_ardataLog['products']));

		$listBookForProd = function ($prod
			, $numIDBlock_withBOOKCatalog
			, $strPropertyWithArtucul) {
			$ret = [];

			foreach ($prod as $val) {
				$arItem = '';
				if (!empty($val["vendor_code"])) {
					$obElement = new \CIBlockElement;
					$arItem = $obElement->GetList(
						[]
						, [
							"IBLOCK_ID" => $numIDBlock_withBOOKCatalog,
							"=" . $strPropertyWithArtucul . "_VALUE" => $val["vendor_code"]
						]
						, false, false
						, ["ID", "NAME", "PROPERTY_*", $strPropertyWithArtucul
						]
					)->fetch()["NAME"];
				}
				$objdbfield = \CUserFieldEnum::GetList(
					[]
					, ["VALUE" => trim($arItem)]
				);
				$IDbook = $objdbfield->fetch()["ID"];
				$ret[] = $IDbook;
			}
			return $ret;
		};

		$arForUpdateProdUF = $listBookForProd($getprod
			, $numIDBlock_withBOOKCatalog
			, $strPropertyWithArtucul);

		return $arForUpdateProdUF;
	}

	/*
	 * @param currentStage str C41:11
	 * */

	public static function funcUpdateStage($currentStage, $arcurrentDeal)
	{
		$arListStatuses = self::prepareSettingByGroup($currentStage);

		$numCurrentStateFromService = self::$ardataLog[$arcurrentDeal['ID']]['info'][0]['status_id'];
//		$numCurrentStateFromService=43;
		self::$debug && self::$arLogSync[] = 'Из внешних данных, последний статус : ' . ($numCurrentStateFromService);

		$declareStateInOptionTabModule = function ($ar, $num) {

			$_kst = array_keys($ar);
			$_find = array_search($num, $_kst);
			if ($_find === false) {
				return false;
			} else {
				return [$_kst[$_find] => $ar[$_kst[$_find]]];
			}


		};

		$findExternalWithInternal = $declareStateInOptionTabModule($arListStatuses['assoc'], $numCurrentStateFromService);
		self::$debug && self::$arLogSync[] = 'Входящая цифра состояния от внешнего источника, стадия  ' . $numCurrentStateFromService;
		self::$debug && self::$arLogSync[] = 'Внешний номер найден с списке "внутренних сопоставлений" в настройках модуля  "' . (is_array($findExternalWithInternal)) . '"';
		self::$debug && self::$arLogSync[] = [$findExternalWithInternal];
		self::$debug && self::$arLogSync[] = $arListStatuses['assoc'];
		if (!is_array($findExternalWithInternal)) {
			self::$debug && self::$arLogSync[] = ' Не найдено сопоставление. Ждем, когда появится или вручную менеджеры изменят. ' . "\n" . 'Вот список зарегистрированных во вкладке настройки модуля. /Слева столбик equal статусов агрегатора/ ';
			self::$debug && self::$arLogSync[] = $arListStatuses['assoc'];
		} else {
			if (self::$boolChangeStage) {

				$strSTAGE_IDDeal = \Bitrix\Crm\DealTable::query()
					->setSelect(['STAGE_ID'])
					->where('ID', $arcurrentDeal['ID'])
					// ->setLimit(2)
					->exec()
					->fetch()['STAGE_ID'];
				$strSTAGE_IDFromService = array_values($findExternalWithInternal)[0];

				if ($strSTAGE_IDDeal <> $strSTAGE_IDFromService) {
					if (!empty($strSTAGE_IDFromService)) {
						$arUpdate['STAGE_ID'] = $strSTAGE_IDFromService;
						$obDeal = new \CCrmDeal(false);
						if (!$obDeal->Update($arcurrentDeal['ID']
							, $arUpdate
							, $bCompare = true
							, $bUpdateSearch = false
							, $options = array(
								"ENABLE_SYSTEM_EVENTS" => false
							, "REGISTER_STATISTICS" => false
							, "CURRENT_USER" => self::$idThisUser
							))) {
							self::$debug && self::$arLogSync[] = $obDeal->LAST_ERROR;
							// print_r($obDeal->LAST_ERROR);
							// $this->WriteToTrackingService('Ошибка обновления сделки : '.$obDeal->LAST_ERROR);
						} else {
							self::$debug && self::$arLogSync[] = 'STAGE_ID Cделка ' . $arcurrentDeal['ID'] . ' обновлена c ' . $strSTAGE_IDDeal . ' до ' . $strSTAGE_IDFromService;

							if (in_array($arUpdate['STAGE_ID'],self::$arStagesAtNeedRunAgent)){

								// $ret = file_get_contents("https://api.telegram.org/bot1211118756:AAHjf1vYBkxxw41HweSvO3QSRtFHc4jfrdc/sendMessage?chat_id=1289889057&text=".$arUpdate['STAGE_ID']."_".$arcurrentDeal['ID']);

								$start = microtime(true);
								self::$debug && self::$arLogSync[] = 'now run robots .. потому,что на этой стадии должны быть выполнены роботы..'."\n";
								$arErrors = array();
								\CCrmBizProcHelper::AutoStartWorkflows(
									2,//CCrmOwnerType::Deal,
									$arcurrentDeal['ID'],
									2,//CCrmBizProcEventType::Edit,
									$arErrors
								);

								$arFieldsRobot=array (
									'STAGE_ID' => $arUpdate['STAGE_ID'],
									'~DATE_MODIFY' => 'now()',
									'MODIFY_BY_ID' => self::$idThisUser,
									'STAGE_SEMANTIC_ID' => 'P',
									'IS_NEW' => 'N',
									//'ACCOUNT_CURRENCY_ID' => 'RUB',
									// 'OPPORTUNITY_ACCOUNT' => '3500.00',
									// 'TAX_VALUE_ACCOUNT' => 0.0,
									// 'CLOSED' => 'N',
									'ID' => $arcurrentDeal['ID'],
									'C_OLD_FIELDS' =>
										array (
											'ID' => $arcurrentDeal['ID'],
											// 'ASSIGNED_BY_ID' => '1',//'24', USER it!!!
											'STAGE_ID' => $strSTAGE_IDDeal,
										),
								);
								//Region automation
								$starter = new \Bitrix\Crm\Automation\Starter(\CCrmOwnerType::Deal, $arcurrentDeal['ID']);
								$starter->setUserIdFromCurrent()->runOnUpdate($arFieldsRobot, []);

								$finish = microtime(true);
								$totaltimerunrobot = number_format(($finish - $start), 5);

								// self::$debug && self::$arLogSync[] = 'now end robots over : '.(($totaltimerunrobot)).' секундъ'."\n";

							}else{
								self::$debug && self::$arLogSync[] = 'now NOT run robots .. потому,что на этой стадии /'.$arUpdate['STAGE_ID'].'/ НЕ должны быть выполнены роботы..'."\n";

								}
								// $ret = file_get_contents("https://api.telegram.org/bot1211118756:AAHjf1vYBkxxw41HweSvO3QSRtFHc4jfrdc/sendMessage?chat_id=1289889057&text='"."0000_".$arcurrentDeal['ID']."'");
							
							// print_r($arUpdate);
							// $this->WriteToTrackingService('Cделка обновлена. Поля: '.print_r($arUpdate, true));$strSTAGE_IDDeal <> $strSTAGE_IDFromService
						}
					}
				} else {
					self::$debug && self::$arLogSync[] = 'проверка роботов не запущена, так как стадии - сделки /'.$strSTAGE_IDDeal.'/ и сервиса /'.$strSTAGE_IDFromService.'/ совпадают'."\n";

					self::$debug && self::$arLogSync[] = 'STAGE_ID Cделка ' . $arcurrentDeal['ID'] . ' без изменений, есть ' . $strSTAGE_IDDeal . ', а на сервисе ' . $strSTAGE_IDFromService;

				}

			}


		}
		$ef = 1;
	}

	public static function prepareSettingByGroup($funccurrentStage)
	{

		$numGroup = preg_replace('/[^0-9]/', '', explode(':', $funccurrentStage)[0]);
		$arListStatesByGroup = [];

		foreach (self::$argetOptions as $key => $value) {
			if ((stripos($value[0]['NAME'], "_" . $numGroup . "_")) !== false) {
				$numTmpOperation = end(explode('_', $value[0]['NAME']));
				$arListStatesByGroup['series'][] = $value[0]['VALUE'];
				$arListStatesByGroup['assoc'][$numTmpOperation] = $value[0]['VALUE'];
				$arListStatesByGroup['reassoc'][$value[0]['VALUE']] = $numTmpOperation;
			}
		}
		return $arListStatesByGroup;
	}

	public static function writeLogDebug($arlog = [])
	{
		if (empty($arlog)) {
			return;
		}
		$pathModule = count(glob($_SERVER['DOCUMENT_ROOT'] . '/local/modules/' . self::$module_id)) > 0 ?
			'/local/modules/' . self::$module_id :
			'/bitrix/modules/' . self::$module_id;
		file_put_contents(sprintf("%s%s/debug_update_deals.json", $_SERVER['DOCUMENT_ROOT'], $pathModule)
			, "" . json_encode([
				'time' => date("H:i:s d-m-Y")//intval(microtime(true))
				, 'inf' => $arlog
			])
		);
//		file_put_contents(
//			$_SERVER['DOCUMENT_ROOT'].'/bitrix/fOnAfterCrmDealAdd_1.json'
//			, ",".json_encode([
//				'time'=>intval(microtime(true))
//				,'inf'=>$ahf1
//			])
//			, FILE_APPEND
//		);
	}
}


