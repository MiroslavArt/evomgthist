<?

namespace Itrack\Saferoutecrmcyns;

use Bitrix\Main\Config\Option
	, Bitrix\Main\Loader;

function removeBOM($str="") {
    if(substr($str, 0, 3) == pack('CCC', 0xef, 0xbb, 0xbf)) {
        $str = substr($str, 3);
    }
    return $str;
}

class Chstate
{
	public $lsDealStatus = [];
	public $arDealsWithState;
	public $propertyByValueOrder;
	public $pathModule = '/local/modules/';//'/bitrix/modules/';//'/local/modules/';
	public $dataLog;
	public $module_id;
	public $debug = true;

	// public $arOptionsSync;

	function __construct($lsDealStatus = [], $arDealsWithState = [], $propertyByValueOrder = '', $arOptionsSync = [])
	{
		$this->lsDealStatus = $lsDealStatus;
		$this->arDealsWithState = $arDealsWithState;
		$this->propertyByValueOrder = $propertyByValueOrder;
		$this->arOptionsSync = $arOptionsSync;
		$this->module_id = Common::getDefine()['ID_MODULE'];
		$this->pathModule = count(glob($_SERVER['DOCUMENT_ROOT'] . '/local/modules/' . $this->module_id)) > 0 ?
			  '/local/modules/' . $this->module_id :
			  '/bitrix/modules/' . $this->module_id;
		
	}

	function updatefuncByLoop()
	{
		// print_r($this->arDealsWithState);

		foreach ($this->arDealsWithState as $vID) {
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


		$arListStates = $this->prepareSettingByGroup($funccurrentStage);
		$boolAutoChangeStage=Common::getOption()['setting_set_autochange'];
		$boolEnableAutoChangeStage=($boolAutoChangeStage=='Y')?True:False;
		$boolAutoChangeStageRule=Common::getOption()['setting_set_autochange_rule'];
		$boolEnableAutoChangeStageRule=($boolAutoChangeStageRule=='Y')?False:True;

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

		$selNearest = function ($fh = [], $h = 1, $bRoundUP = false) {
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
		$this->debug && print_r("current stage ID, stage ID:" . $numCurrentState . "\n");
print_r($arListStates);
		// Whether the transaction status ID and the last dispatch status ID match?
		if (in_array($numCurrentStateFromService, $arDataExistInGroup)) {
			$codeBeState = $arListStates['assoc'][$numCurrentStateFromService];
			$this->debug && print_r("be stage CODE:" . $codeBeState . "\n");
			$boolPrecisely = true;
		} else {
			
			
			// Otherwise, look for the closest suitable
			$numBeState = $selNearest($arDataExistInGroup, $numCurrentState, $boolEnableAutoChangeStageRule);
			$this->debug && print_r("be stage ID:" . $numBeState . "\n");
			//What will be the identifier on the internal ID
			$codeBeState = $arListStates['assoc'][$numBeState];
			$this->debug && print_r("be stage CODE:" . $codeBeState . "\n");
			// print_r($this->dataLog[$arcurrentDeal['ID']]);
			$boolPrecisely = false;
		}

		if (!empty($codeBeState) && !empty($arcurrentDeal['ID'])) {
			$idDealforUpdate = $arcurrentDeal['ID'];
			//todo update deal stage
			$arUpdate['STAGE_ID'] = $codeBeState;
			
			$this->debug && print_r("enable AUTO update deals:'" . $boolEnableAutoChangeStage . "'\n");

			$boolEnableUpdate=False;
			if (!$boolPrecisely&&$boolEnableAutoChangeStage)
			{
				// Если не точно и разрешено искать ближайший
				$boolEnableUpdate=True;
			}
			
			if ($boolPrecisely)
			{
				//Если точно статус есть тогда менять можно
				$boolEnableUpdate=True;
			}
			
			
			if ($boolEnableUpdate)
			{
				$obDeal = new \CCrmDeal(false);
				if (!$obDeal->Update($idDealforUpdate, $arUpdate)) {
					// print_r($obDeal->LAST_ERROR);
					// $this->WriteToTrackingService('Ошибка обновления сделки : '.$obDeal->LAST_ERROR);
				} else {
					// print_r($arUpdate);
					// $this->WriteToTrackingService('Cделка обновлена. Поля: '.print_r($arUpdate, true));
				}
			}

		}

		$fstrinfCurrentStateFromService = 'Последнее состояние: \'%1$s\' , на дату: \'%2$s\' \n Этот статус от сервиса не зарегистрирован:\'%3$s\'  !';
		$strinfCurrentStateFromService = sprintf(
			$fstrinfCurrentStateFromService
			, $infCurrentStateFromService['status']
			, $infCurrentStateFromService['date']
			, $infCurrentStateFromService['status_id']
		);
		
		
		$fstrinfCurrentStateFromService = "Последнее состояние: '".$infCurrentStateFromService['status']."' , на дату: '".$infCurrentStateFromService['date']."' \n Этот статус от сервиса не зарегистрирован:'".$infCurrentStateFromService['status_id']."'  !";
		// print_r('\n====\n');
		print_r($fstrinfCurrentStateFromService);

		if (!$boolPrecisely) {
			
			
			// print_r($strinfCurrentStateFromService);
			//todo add message to timline
			$entity = new \Bitrix\Crm\Timeline\CommentEntry(false);
			$ret1 = $entity->create(
				array(
					'TEXT' => $fstrinfCurrentStateFromService,
					'SETTINGS' => array('HAS_FILES' => 'N'),
					'AUTHOR_ID' => 3, //ID пользователя,
					'BINDINGS' => [
						[
							'ENTITY_TYPE_ID' => 2 // 2 - Сделка
							, 'ENTITY_ID' => $idDealforUpdate
						]
					]
				));
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

	function SelectState()
	{
		// file_get_contents($_SERVER['DOCUMENT_ROOT'].'/local/modules/'.$module_id.'/req_service_answer.json', "".json_encode($arData).'');

		$module_id = empty($this->module_id) ? 'itrack.saferoutecrmcyns' : $this->module_id;

		$strInf = file_get_contents($_SERVER['DOCUMENT_ROOT'] . $this->pathModule . '/req_service_answer.json');
		// $strInf=removeBOM($strInf);
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


}


?>