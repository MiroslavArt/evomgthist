<?

namespace Itrack\Saferoutecrmcyns;

use Bitrix\Main\Config\Option;

class CustomRandom
{
	public $lsDealStatus = [];
	public $arDealsWithState;
	public $propertyByValueOrder;

	function __construct($lsDealStatus = [], $arDealsWithState = [], $propertyByValueOrder = '')
	{
		$this->lsDealStatus = $lsDealStatus;
		$this->arDealsWithState = $arDealsWithState;
		$this->propertyByValueOrder = $propertyByValueOrder;
	}

	function updatefuncByLoop()
	{
		// print_r($this->arDealsWithState);

		foreach ($this->arDealsWithState as $vID) {
			$this->execfunc($vID['ID'], $vID['STAGE_ID']);
		}


	}

	function execfunc($strID = '', $currentStage)
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

		$arUpdate['STAGE_ID'] = $fChangeState($arfindByKey['stage'], $currentStage);

		$obDeal = new \CCrmDeal(false);
		if (!$obDeal->Update($strID, $arUpdate)) {
			// print_r($obDeal->LAST_ERROR);
			// $this->WriteToTrackingService('Ошибка обновления сделки : '.$obDeal->LAST_ERROR);
		} else {
			// print_r($arUpdate);
			// $this->WriteToTrackingService('Cделка обновлена. Поля: '.print_r($arUpdate, true));
		}

	}

}
