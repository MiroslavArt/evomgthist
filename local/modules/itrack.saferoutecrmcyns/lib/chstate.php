<?

namespace Itrack\Saferoutecrmcyns;

use Bitrix\Crm\Timeline\TimelineType;
use Bitrix\Main\Config\Option
	, Bitrix\Main\Loader;

function removeBOM($str = "")
{
	if (substr($str, 0, 3) == pack('CCC', 0xef, 0xbb, 0xbf)) {
		$str = substr($str, 3);
	}
	return $str;
}

class Chstate
{
	public $lsDealStatus = [];
//	public $arDealsWithState;
	public $propertyByValueOrder;
	public $pathModule = '/local/modules/';//'/bitrix/modules/';//'/local/modules/';
	public $dataLog;
	public $module_id;
	public $debug = true;
//	protected $arOption;
	public $arOption;


	// public $arOptionsSync;

	function __construct($lsDealStatus = [], $arDealsWithState = [], $propertyByValueOrder = '', $arOptionsSync = [], $settingModule = [])
	{
		$this->lsDealStatus = $lsDealStatus;
		$this->dataLog = $this->SelectState();
//		$this->arDealsWithState = $arDealsWithState;
		$this->propertyByValueOrder = $propertyByValueOrder;
		$this->arOptionsSync = $arOptionsSync;
		$this->arOption = $settingModule;
		$this->module_id = 'itrack.saferoutecrmcyns';//Common::getDefine()['ID_MODULE'];
		$this->pathModule = count(glob($_SERVER['DOCUMENT_ROOT'] . '/local/modules/' . $this->module_id)) > 0 ?
			'/local/modules/' . $this->module_id :
			'/bitrix/modules/' . $this->module_id;
		if (empty($this->dataLog)) {
			$this->dataLog = $this->SelectState();
		}
	}

	function SelectState()
	{
		// file_get_contents($_SERVER['DOCUMENT_ROOT'].'/local/modules/'.$module_id.'/req_service_answer.json', "".json_encode($arData).'');

		$module_id = empty($this->module_id) ? 'itrack.saferoutecrmcyns' : $this->module_id;

//		$strInf = file_get_contents($_SERVER['DOCUMENT_ROOT'] . $this->pathModule . '/req_service_answer.json');
		$strInf = file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/local/modules/itrack.saferoutecrmcyns/req_service_answer.json');

		// $strInf=removeBOM($strInf);/local/modules//req_service_answer.json
		$ar = json_decode($strInf, true);
		// $ar = \Bitrix\Main\Web\Json::decode($strInf);
		// print_r($strInf);
		$arInf = [];
		array_walk($ar, function ($v, $k) use (&$arInf) {
			$numD = array_keys($v)[0];
			$arInf[$numD] = $v[$numD];
		});

		$arInfDealsIDSenum = array_keys($arInf);

		// print_r($this->arOptionsSync);
		return $arInf;
		// print_r($arInf);
		// var_dump(in_array(116254,array_keys($arInf)));

	}

	function updatefuncByLoop($f_arDealsWithState)
	{
		// print_r($this->arDealsWithState);

		foreach ($f_arDealsWithState as $vID) {
			// print_r($vID);
			$this->execfunc($vID['ID'], $vID['STAGE_ID'], $vID);
		}
	}

	function execfunc($strID = '', $currentStage, $arcurrentDeal)
	{
		if (empty($strID) || empty($currentStage)) {
			return;
		}
		/*
			[parameter] => Array
				(
					[$lsDealStatus] => <optional>
					[$searchval] => <optional>
				)


		(
			[num] => 1
			[stage] => Array
				(
					[0] => empty
					[1] => C7:NEW
					[2] => C7:EXECUTING
					[3] => C7:FINAL_INVOICE
					[4] => C7:2
					[5] => C7:3
					[6] => C7:WON
					[7] => C7:LOSE
					[8] => C7:APOLOGY
				)

			[now] => C7:NEW
		)
		*/
		$arfindByKey = function ($lsDealStatus = [], $searchval = '') {
			$ret = [];
			array_walk($lsDealStatus, function ($v, $k) use ($lsDealStatus, &$ret, $searchval) {
				$arks = array_keys($v);
				$searchkey = array_search($searchval, $arks);
				if ($searchkey) {
					$ret = ['num' => $searchkey, 'stage' => $arks, 'now' => $arks[$searchkey]];
				}
			});
			return $ret;

		};
		$arfindByKey = $arfindByKey($this->lsDealStatus, $currentStage);

		$fChangeState = function ($arIncomingState = [], $nowState = '') {

			$_c = count($arIncomingState);
			$_set = rand(1, $_c - 1);
			if (!empty($arIncomingState[$_set])) {
				return $arIncomingState[$_set];
			}
			return $nowState;
		};

		// $arUpdate['STAGE_ID'] = $fChangeState($arfindByKey['stage'], $currentStage);
		$arUpdate['STAGE_ID'] = $this->ChangeState($arfindByKey['stage'], $currentStage, $arcurrentDeal);
		// print_r($arUpdate);
		/*$obDeal = new \CCrmDeal(false);
		if (!$obDeal->Update($strID, $arUpdate)) {
			// print_r($obDeal->LAST_ERROR);
			// $this->WriteToTrackingService('Ошибка обновления сделки : '.$obDeal->LAST_ERROR);
		} else {
			// print_r($arUpdate);
			// $this->WriteToTrackingService('Cделка обновлена. Поля: '.print_r($arUpdate, true));
		}*/

	}

	function ChangeState($funcarfindByKey, $funccurrentStage, $arcurrentDeal)
	{

		$boolMake_record_timeline = ($this->arOption['make_record_timeline'] == 'Y') ? True : False;
		$arListStates = $this->prepareSettingByGroup($funccurrentStage);
		$boolAutoChangeStage = ($this->arOption['setting_set_autochange'] == 'Y') ? True : False;
		$boolEnableAutoChangeStage = ($boolAutoChangeStage == 'Y') ? True : False;
		$boolAutoChangeStageRule = ($this->arOption['setting_set_autochange_rule'] == 'Y') ? True : False;
		$boolEnableAutoChangeStageRule = ($boolAutoChangeStageRule == 'Y') ? False : True;

		$this->UpdateDeliveryInf($arcurrentDeal);

		$numNowPos = array_search($funccurrentStage, $arListStates['series']);
		$this->debug && print_r($arcurrentDeal);
		// $numBePos=$numNowPos+1;
		// $funcBeStage=$arListStates['series'][$numBePos];

		if (empty($this->dataLog)) {
			$this->dataLog = $this->SelectState();
		}

		if (!empty($this->dataLog[$arcurrentDeal['ID']])) {
			$_a = $this->dataLog[$arcurrentDeal['ID']];
			$numCurrentStateFromService = $_a['info'][0]['status_id'];
			$infCurrentStateFromService = $_a['info'][0];
			$this->debug && print_r("stat from exchange file:" . $_a['info'][0]['status_id'] . "\n");
		}
		// print_r($this->dataLog[$arcurrentDeal['ID']]['info'][0]);

		$selNearest = function ($fh = [], $h, $bRoundUP = false) {
			$numChange = 0;

			$re = array_map(function ($v) use ($h) {
				return $h % $v;
			}, $fh);
			/*
			in =18
			[10,12,13,32,44]
			x x x 32 x	bRoundUP=True	out=18=32
			x x 13 x x	bRoundUP=False	out=18=13
			*/

			$arSearchs = $bRoundUP ? max($re) : min($re);
			$numNB = array_search($arSearchs, $re);

			// $this->debug&&print_r($re);
			// $this->debug&&print_r('nearest:'.$fh[$numNB]."\n");

			return $fh[$numNB];
		};

		// What data is in the group?
		$arDataExistInGroup = array_keys($arListStates['assoc']); //[10],[12],[13],[32],[44],[777]
		// What is the state now?
		$this->debug && print_r("current stage CODE:" . $funccurrentStage . "\n");
		// What's the id of the current state
		$numCurrentState = $arListStates['reassoc'][$funccurrentStage]; // current ID stage:12
		$this->debug && print_r("current stage ID, stage ID:" . var_dump($numCurrentState) . "\n");
		print_r($arListStates);

		if(!in_array($numCurrentStateFromService, $arDataExistInGroup)){
			/*
			 * ,будущий статус не в ряду, переопределять в нечего, $numBeState выдаст фигню
			 *
			 * */
			$this->debug && print_r("221_ будущий статус не в ряду, переопределять в нечего current state is UNDEFINED by OPTION^ wait future state to goodest dett..\n");
			return;

		}


		// Whether the transaction status ID and the last dispatch status ID match?
		if (in_array($numCurrentStateFromService, $arDataExistInGroup)) {
			$codeBeState = $arListStates['assoc'][$numCurrentStateFromService];
			$this->debug && print_r("be stage CODE:" . $codeBeState . "..\n");
			$boolPrecisely = true;
		} else {
			if(empty($numCurrentState)){
				/*
				 * текущий статус не в ряду, переопределять нечего, $numBeState выдаст фигню
				 *
				 * */
				$this->debug && print_r("226_текущий статус не в ряду, переопределять нечего  current state is UNDEFINED by OPTION^ wait future state to goodest dett..\n");
				return;

			}

			// Otherwise, look for the closest suitable
			$numBeState = $selNearest($arDataExistInGroup, $numCurrentState, $boolEnableAutoChangeStageRule);
			$this->debug && print_r("be stage ID:" . $numBeState . "\n");
			//What will be the identifier on the internal ID
			$codeBeState = $arListStates['assoc'][$numBeState];
			$this->debug && print_r("be stage CODE:" . $codeBeState . "....\n");
			// print_r($this->dataLog[$arcurrentDeal['ID']]);
			$boolPrecisely = false;
		}

		if (!empty($codeBeState) && !empty($arcurrentDeal['ID'])) {
			$idDealforUpdate = $arcurrentDeal['ID'];
			//todo update deal stage
			$arUpdate['STAGE_ID'] = $codeBeState;

			$this->debug && print_r("enable AUTO update deals:'" . $boolEnableAutoChangeStage . "'\n");

			$boolEnableUpdate = False;
			if (!$boolPrecisely && $boolEnableAutoChangeStage) {
				// Если не точно и разрешено искать ближайший
				$boolEnableUpdate = True;
			}

			if ($boolPrecisely) {
				//Если точно статус есть тогда менять можно
				$boolEnableUpdate = True;
			}

			$obDeal = new \CCrmDeal(false);
			if (!$obDeal->Update($arcurrentDeal['ID'], $arUpdate)) {
				// print_r($obDeal->LAST_ERROR);
				// $this->WriteToTrackingService('Ошибка обновления сделки : '.$obDeal->LAST_ERROR);
			} else {
				// print_r($arUpdate);
				// $this->WriteToTrackingService('Cделка обновлена. Поля: '.print_r($arUpdate, true));
			}

		}

		$fstrinfCurrentStateFromService = 'Последнее состояние: \'%1$s\' , на дату: \'%2$s\' \n Этот статус от сервиса не зарегистрирован:\'%3$s\'  !';
		$strinfCurrentStateFromService = sprintf(
			$fstrinfCurrentStateFromService
			, $infCurrentStateFromService['status']
			, $infCurrentStateFromService['date']
			, $infCurrentStateFromService['status_id']
		);


		$fstrinfCurrentStateFromService = "Последнее состояние: '" . $infCurrentStateFromService['status'] . "' , на дату: '" . $infCurrentStateFromService['date'] . "' \n Этот статус от сервиса :'" . $infCurrentStateFromService['status_id'] . "'  !";
		// print_r('\n====\n');
		print_r($fstrinfCurrentStateFromService);

		if(0) {
			if (!$boolPrecisely or $boolMake_record_timeline) {

				$arMake_record_timeline = [];
				if (!empty($infCurrentStateFromService['status'])) {
					$entityTL = new \Bitrix\Crm\Timeline\Entity\TimelineTable(false);
					$parameters = [
						'select' => [
							'ID'
							, 'BINDINGS'
							, 'COMMENT'
							// ,'CRM_TIMELINE_ENTITY_TIMELINE_BINDINGS_ENTITY_ID'
							// 'SECTION_CODE' => 'SECTION.CODE',
							// 'UF_SECTION_CODE_PATH' => 'SECTION.UF_SECTION_CODE_PATH',
						], 'filter' => [
							// 'ID' => '1663668',
							"%COMMENT" => $infCurrentStateFromService['status']
							//, 'CRM_TIMELINE_ENTITY_TIMELINE_BINDINGS_ENTITY_ID' => $idDealforUpdate
						]
					];
					$arMake_record_timeline = $entityTL::getList($parameters)->fetch();
				}

				// print_r($strinfCurrentStateFromService);
				define("NO_AGENT_STATISTIC", "Y");
				define("NO_AGENT_CHECK", true);
				if (empty($arMake_record_timeline)) {
					$paramBINDINGS = [
						'ENTITY_TYPE_ID' => 2 // 2 - Сделка
						, 'ENTITY_ID' => $idDealforUpdate
					];
					$ac = array(
						0 =>
							array(
								'ENTITY_TYPE_ID' => 2,
								'ENTITY_ID' => '117519',
							),
					);
					$entity = new \Bitrix\Crm\Timeline\CommentEntry(false);

					/*$ret1 = $entity->create(
						array(
							'TEXT' => $fstrinfCurrentStateFromService,
							'SETTINGS' => array('HAS_FILES' => 'N'),
							'AUTHOR_ID' => 3, //ID пользователя,
							'BINDINGS' => [
								$paramBINDINGS
							]
						));*/
					$entityTM = new \Bitrix\Crm\Timeline\Entity\TimelineTable();
					$result = $entityTM::add(
						array(
							'TYPE_ID' => \Bitrix\Crm\Timeline\TimelineType::COMMENT,
							'TYPE_CATEGORY_ID' => 0,
							'CREATED' => new \Bitrix\Main\Type\DateTime(),
							'AUTHOR_ID' => 3, //ID пользователя,
							'COMMENT' => $fstrinfCurrentStateFromService,
							'SETTINGS' => array('HAS_FILES' => 'N'),
							'ASSOCIATED_ENTITY_TYPE_ID' => 2,
							'ASSOCIATED_ENTITY_ID' => $idDealforUpdate
						)
					);


					if ($result->isSuccess()) {
						$IDtm = $result->getId();
						$bindings = isset($paramBINDINGS) && is_array($paramBINDINGS) ? $paramBINDINGS : array();

						$entity::registerBindings($IDtm, [$bindings]);

						$entity::buildSearchContent($IDtm);
					}
				}
			}
		}
	}

	function prepareSettingByGroup($funccurrentStage)
	{
		// print_r($this->arOptionsSync);
		$numGroup = preg_replace('/[^0-9]/', '', explode(':', $funccurrentStage)[0]);
		$arListStatesByGroup = [];

		foreach ($this->arOptionsSync as $key => $value) {
			if ((stripos($value['NAME'], "_" . $numGroup . "_")) !== false) {
				$numTmpOperation = end(explode('_', $value['NAME']));
				$arListStatesByGroup['series'][] = $value['VALUE'];
				$arListStatesByGroup['assoc'][$numTmpOperation] = $value['VALUE'];
				$arListStatesByGroup['reassoc'][$value['VALUE']] = $numTmpOperation;
			}
		}
		return $arListStatesByGroup;

	}

	function UpdateDeliveryInf($farcurrentDeal)
	{
		if (empty($this->dataLog)) {
			$this->dataLog = $this->SelectState();
		}


		if (empty($this->arOption['property_tracknum']) or
			empty($this->arOption['property_urltracknum']) or
			empty($this->arOption['property_nameoffer']) or
			empty($this->arOption['property_companylog'])) {
			return;
		}

		$ardataLog = $this->dataLog[$farcurrentDeal['ID']];
		// todo make record in comment and remove deals
		$arInfBook = $this->selectBooks($ardataLog, []);

		$arrayIDDeals = \Bitrix\Crm\DealTable::query()
			// ->addSelect('CATEGORY_ID')	b_crm_deal_category
			// ->setSelect(["*","UF_*"])
			// ->setSelect(["ID","STAGE_ID","TITLE", "COMPANY_ID", "OPPORTUNITY", "CURRENCY_ID",$sPropSF,"CATEGORY_ID"])
			->setSelect(["ID"
				, $this->arOption['property_tracknum']
				, $this->arOption['property_wishdelivery']
				, $this->arOption['property_companylog']
				, $this->arOption['property_urltracknum']
				, $this->arOption['property_nameoffer']
			])
			// ->setFilter([
			// 'ID'=>$farcurrentDeal['ID'],
			// ])
			->where('ID', $farcurrentDeal['ID'])
			// ->setLimit(2)
			->exec()
			->fetch();
		$boolUpdateInfDelivery = false;
		if ($arrayIDDeals[$this->arOption['property_nameoffer']] != $arInfBook && !empty($arInfBook)) {
			$arUpdate[$this->arOption['property_nameoffer']] = $arInfBook;


			$boolUpdateInfDelivery = true;
		}
		if ($arrayIDDeals[$this->arOption['property_tracknum']] != $ardataLog['track_number'] && !empty($ardataLog['track_number'])) {
			$arUpdate['UF_CRM_1606464347828'] = $ardataLog['track_number'];
//			$boolUpdateInfDelivery = true;
		}
		if ($arrayIDDeals['UF_CRM_1606464298469'] != $ardataLog['delivery_date'] && !empty($ardataLog['delivery_date'])) {
			$arUpdate['UF_CRM_1606464298469'] = $ardataLog['delivery_date'];
			$boolUpdateInfDelivery = true;
		}
		if ($arrayIDDeals[$this->arOption['property_companylog']] != $ardataLog['company'] && !empty($ardataLog['company'])) {
			$arUpdate[$this->arOption['property_companylog']] = $ardataLog['company'];
			$boolUpdateInfDelivery = true;
		}
		if ($arrayIDDeals['UF_CRM_1606465706750'] != $ardataLog['tracking_url'] && !empty($ardataLog['tracking_url'])) {
			$arUpdate['UF_CRM_1606465706750'] = $ardataLog['tracking_url'];
			$boolUpdateInfDelivery = true;
		}

		if ($boolUpdateInfDelivery) {
			define("NO_AGENT_STATISTIC", "Y");
			define("NO_AGENT_CHECK", true);
			$obDeal = new \CCrmDeal(false);
			define("NO_AGENT_STATISTIC", "Y");
			define("NO_AGENT_CHECK", true);
			if (!$obDeal->Update($farcurrentDeal['ID']
				, $arUpdate
			, $bCompare = true
				, $bUpdateSearch = true
				, $options = array("ENABLE_SYSTEM_EVENTS"=>false))) {
				// print_r($obDeal->LAST_ERROR);
				// $this->WriteToTrackingService('Ошибка обновления сделки : '.$obDeal->LAST_ERROR);
			} else {
				// print_r($arUpdate);
				// $this->WriteToTrackingService('Cделка обновлена. Поля: '.print_r($arUpdate, true));
			}
		}


	}

	function _customSelectBOOK($incomingInf = [], $arOptionsFromTab = [])
	{
		$mathesBook = array(
			'Книга Системный бизнес. Лидер и команда' =>
				array(
					'NAME' => 'ЧБ. Системный бизнес. Лидер и Команда',
					'ID' => '1588',
				),
			'Книга "Системный бизнес лидер и команда"' =>
				array(
					'NAME' => 'ЧБ. Системный бизнес. Лидер и Команда',
					'ID' => '1588',
				),
			'ЧБ. Системный бизнес. Лидер и Команда' =>
				array(
					'NAME' => 'ЧБ. Системный бизнес. Лидер и Команда',
					'ID' => '1589',
				),
			'Моя компания работает без меня' =>
				array(
					'NAME' => 'Моя компания работает без меня',
					'ID' => '1590',
				),

		);
		empty($arOptionsFromTab) && $arOptionsFromTab = $mathesBook;
		/*incomingInf = array(
			'track_number' => '7500991906617',
			'tracking_url' => 'http://iml.ru/status',
			'delivery_date' => '04.12.2020',
			'products' =>
				array(
					0 =>
						array(
							'name' => 'Книга Системный бизнес. Лидер и команда',
							'count' => 1,
							'price_declared' => '800.00',
							'warehouse_barcode' => 'FF35282',
						),
				),
			'company' => 'IML',
			'order_id' => 10130200,
		);*/


		$getprod = array_map(function ($a) {

			return [
				'name' => $a['name'],
				'count' => $a['count'],
			];
		}, array_values($incomingInf['products']));


		$listBookForProd = function ($prod, $settings = []) {
			$ret = [];
			$keysettings = array_keys($settings);
			foreach ($prod as $val) {
				$_retsearch = array_search($val['name'], $keysettings);
				if (is_numeric($_retsearch)) {
					$ret[] = $settings[$keysettings[$_retsearch]]['ID'];
				} else {
					// todo set default setting and set message event
				}
			}
			return $ret;
		};

		$arForUpdateProdUF = $listBookForProd($getprod, $arOptionsFromTab);
		return $arForUpdateProdUF;

	}

	function addMessageToTimeline($message = '', $strMatches = '')
	{


	}

	function selectBooks($incomingInf = [], $arOptionsFromTab = [])
	{
		$numIDBlock_withBOOKCatalog=35;
		$strPropertyWithArtucul="PROPERTY_154";

		$getprod = array_map(function ($a) {
			return [
				'name' => $a['name'],
				'count' => $a['count'],
				'price_declared' => $a['price_declared'],
				'vendor_code' => $a['vendor_code'],
			];
		}, array_values($incomingInf['products']));

		$listBookForProd = function ($prod
			,$numIDBlock_withBOOKCatalog
			,$strPropertyWithArtucul) {
			$ret = [];

			foreach ($prod as $val) {
				$arItem = '';
				if (!empty($val["vendor_code"])) {
					$obElement = new \CIBlockElement;
					$arItem = $obElement->GetList(
						[]
						, [
							"IBLOCK_ID" => $numIDBlock_withBOOKCatalog,
							"=".$strPropertyWithArtucul."_VALUE" => $val["vendor_code"]
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
			,$numIDBlock_withBOOKCatalog
			,$strPropertyWithArtucul);

		return $arForUpdateProdUF;


	}
}


?>