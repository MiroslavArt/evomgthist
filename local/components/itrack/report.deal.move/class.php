<?php

use Bitrix\Crm\History\Entity\DealStageHistoryTable;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\UI;
use Bitrix\Main\Grid;
use Bitrix\Main\Config\Option;
use Bitrix\Main\ORM\Fields\Relations\Reference;
use Bitrix\Main\ORM\Query\Query;
use Bitrix\Main\Grid\Declension;

Loc::loadMessages(__FILE__);

class CItrackReportDealMove extends \CBitrixComponent
{
    private $modules = ['crm'];
    protected $arSort;
    protected $obNav;
    protected $arCategories = [];
    protected $arStagesRef = [];
    protected $arCategoryStages = [];

    public function onPrepareComponentParams($arParams)
    {
        if(empty($arParams['LIST_ID'])) {
            $arParams['LIST_ID'] = 'report_deal_move_list';
        }
        return $arParams;
    }

    public function executeComponent()
    {
        if($this->checkModules()) {
            $this->makeResult();
            $this->includeComponentTemplate();
        }
    }

    private function checkModules()
    {
        $bCheck = true;
        foreach($this->modules as $module) {
            if(!Loader::includeModule($module)) {
                ShowError('Error include module '.$module);
                $bCheck = false;
            }
        }
        return $bCheck;
    }

    protected function makeResult()
    {
        $this->initAdditionalData();
        $this->makeGridData();
        $this->makeFilterComponentData();
    }

    protected function makeGridData()
    {
        $this->arResult['FILTER_PRESETS'] = [
            'category_common' => [
                'name' => Loc::getMessage('ITRACK_RDMC_FILTER_PRESET_CC_NAME'),
                'default' => true,
                'fields' => ['CATEGORY' => 0]
            ]
        ];

        $obGridOptions = new Grid\Options($this->arParams['LIST_ID'], $this->arResult['FILTER_PRESETS']);
        $this->arSort = $obGridOptions->getSorting(array('sort' => array('ID' => 'ASC'), 'vars' => array('by' => 'by', 'order' => 'order')));

        $this->obNav = new UI\PageNavigation($this->arParams['LIST_ID']);
        $this->obNav->allowAllRecords(true)
            ->setPageSize($obGridOptions->GetNavParams()['nPageSize'])
            ->initFromUri();


        $obFilterOption = new UI\Filter\Options($this->arParams['LIST_ID'],$this->arResult['FILTER_PRESETS']);
        $arFilterData = $obFilterOption->getFilter();
        $arFilter = [];
        $this->makeRequestFilter($arFilterData, $arFilter);

        $this->arResult['WON'] = ['COUNT' => 0, 'SUM' => 0];
        $this->arResult['LOSE'] = ['COUNT' => 0, 'SUM' => 0];
        $this->arResult['NEW'] = 0;

        $obQuery = new Query(DealStageHistoryTable::getEntity());
        $obQuery->registerRuntimeField(new Reference(
            'DEAL',
            '\Bitrix\Crm\DealTable',
            ['this.OWNER_ID' => 'ref.ID']
        ));

        if(!empty($arFilter)) {
            $obQuery->setFilter($arFilter);
        }
        $obQuery->setSelect([
            'OWNER_ID',
            'TYPE_ID',
            'CREATED_TIME',
            'CATEGORY_ID',
            'STAGE_ID',
            'DEAL_BEGINDATE' => 'DEAL.BEGINDATE',
            'DEAL_CREATEDATE' => 'DEAL.DATE_CREATE',
            'DEAL_SUM' => 'DEAL.OPPORTUNITY',
            'DEAL_CURRENCY' => 'DEAL.CURRENCY_ID',
            'STAGE_SEMANTIC_ID'
        ]);
        $obQuery->setOrder(['CREATED_TIME' => 'asc']);
        $dbHistory = $obQuery->exec();

        $arDeals = [];
        $arStages = [];
        while($arHistory = $dbHistory->fetch()) {
            $arDealsHistory[$arHistory['OWNER_ID']][$arHistory['CATEGORY_ID']][] = $arHistory;
            $arDeals[$arHistory['OWNER_ID']] = [
                'ID' => $arHistory['OWNER_ID'],
                'BEGINDATE' => $arHistory['DEAL_BEGINDATE'],
                'CREATEDATE' => $arHistory['DEAL_CREATEDATE'],
                'SUM' => $arHistory['DEAL_SUM'],
                'CURRENCY' => $arHistory['DEAL_CURRENCY']
            ];
            $arStages[$arHistory['STAGE_ID']]['ITEMS'][] = $arHistory['OWNER_ID'];

            $prevStageId = null;
            $prevKey = count($arDealsHistory[$arHistory['OWNER_ID']][$arHistory['CATEGORY_ID']]) - 2;
            if($prevKey >= 0) {
                $prevStageId = $arDealsHistory[$arHistory['OWNER_ID']][$arHistory['CATEGORY_ID']][$prevKey]['STAGE_ID'];
            }

            if($arHistory['STAGE_SEMANTIC_ID'] == \Bitrix\Crm\PhaseSemantics::SUCCESS) {
                $arStages[$arHistory['STAGE_ID']]['IS_FINAL'] = true;
                $this->arResult['WON']['COUNT']++;
                $this->arResult['WON']['SUM'] += $arHistory['DEAL_SUM'];
            }
            if($arHistory['STAGE_SEMANTIC_ID'] == \Bitrix\Crm\PhaseSemantics::FAILURE) {
                $arStages[$arHistory['STAGE_ID']]['IS_FINAL'] = true;
                $this->arResult['LOSE']['COUNT']++;
                $this->arResult['LOSE']['SUM'] += $arHistory['DEAL_SUM'];

                if($prevStageId !== null) {
                    $arStages[$prevStageId]['LOSE']['COUNT']++;
                    $arStages[$prevStageId]['LOSE']['SUM']+= $arHistory['DEAL_SUM'];
                }
            }
            if($arHistory['TYPE_ID'] == \Bitrix\Crm\History\HistoryEntryType::CREATION) {
                $this->arResult['NEW']++;
            } else {
                $arStages[$arHistory['STAGE_ID']]['INCOMING']++;
            }

            if($prevStageId !== null) {
                if ($this->checkMoveBack($arHistory['STAGE_ID'], $prevStageId)) {
                    $arStages[$prevStageId]['INCOMING']--;
                }
            }
        }
        //print '<pre>'.print_r($arDeals, true).'</pre>';

        $this->arResult['COLUMNS'] = [];
        $this->arResult['COLUMNS'][] = [
            'id' => 'C_NEW',
            'name' => '',
            'type' => 'text',
            'default' => true,
            //'sticked' => true,
            //"sticked_default" => true,
        ];
        $titleRow = [
            'columns' => [
                'C_NEW' => ''
            ]
        ];
        $countRow = [
            'columns' => [
                'C_NEW' => $this->formatAllNew($this->arResult['NEW'])
            ]
        ];
        $loseRow = [
            'columns' => [
                'C_NEW' => '<div class="ev-i-rdm__all-lose-title">Проваленные</div>'
            ]
        ];

        $arDisplayStages = [];
        if(!empty($arFilterData['CATEGORY'])) {
            foreach($arFilterData['CATEGORY'] as $categoryId) {
                $entityID = $categoryId == 0 ? 'DEAL_STAGE' : 'DEAL_STAGE_'.$categoryId;
                $arDisplayStages = array_merge($arDisplayStages, $this->arCategoryStages[$entityID]);
            }
        } else {
            $arDisplayStages = array_keys($this->arStagesRef);
        }
        foreach ($arDisplayStages as $stageId) {
            $stage = $this->arStagesRef[$stageId];
            if(!empty($stage)) {
                if(!empty($arFilterData['NOT_SHOW_NULL']) && $arFilterData['NOT_SHOW_NULL'] == 'Y') {
                    if((int)$arStages[$stage['STATUS_ID']]['INCOMING'] === 0 && (int)$arStages[$stage['STATUS_ID']]['LOSE']['COUNT'] === 0) {
                        continue;
                    }
                }
                if(\CCrmDeal::GetSemanticID($stageId) == \Bitrix\Crm\PhaseSemantics::FAILURE
                    || \CCrmDeal::GetSemanticID($stageId) == \Bitrix\Crm\PhaseSemantics::SUCCESS) {
                    continue;
                }

                $stageDeals = [];
                $stageSum = 0;
                if (!empty($arStages[$stage['STATUS_ID']])) {
                    if ($arStages[$stage['STATUS_ID']]['IS_FINAL']) {
                        continue;
                    }
                    $stageDeals = array_unique($arStages[$stage['STATUS_ID']]['ITEMS']);
                    $stageSum = 0;
                    foreach ($stageDeals as $dealId) {
                        $stageSum += $arDeals[$dealId]['SUM'];
                    }
                }

                $this->arResult['COLUMNS'][] = [
                    'id' => $stage['STATUS_ID'],
                    'name' => '',
                    'type' => 'text',
                    'default' => true
                ];
                $hideCategoryName = !empty($arFilterData['CATEGORY']) && count($arFilterData['CATEGORY']) == 1;
                $titleRow['columns'][$stage['STATUS_ID']] = $this->formatStageTitle($stage, count($stageDeals), $stageSum, 'RUB', $hideCategoryName );
                $countRow['columns'][$stage['STATUS_ID']] = $this->formatStageCount($arStages[$stage['STATUS_ID']]['INCOMING'] ?: 0);
                $loseRow['columns'][$stage['STATUS_ID']] = $this->formatLoseCount($arStages[$stage['STATUS_ID']]['LOSE'] ?: []);
            }
        }
        $this->arResult['COLUMNS'][] = [
            'id' => 'C_END',
            'name' => '',
            'type' => 'text',
            'default' => true,
            //'sticked' => true,
            //"sticked_default" => true,
        ];
        $titleRow['columns']['C_END'] = '';
        $countRow['columns']['C_END'] = $this->formatAllSuccess($this->arResult['WON']['COUNT'], $this->arResult['WON']['SUM']);
        $loseRow['columns']['C_END'] = $this->formatAllFailure($this->arResult['LOSE']['COUNT'], $this->arResult['LOSE']['SUM']);
        $this->arResult['ITEMS'] = [
            $titleRow,
            $countRow,
            $loseRow
        ];

        $obGridOptions->setStickedColumns([]);
        $obGridOptions->save();
    }

    protected function formatStageTitle($arStage, $count = 0, $sum = 0, $currencyId = 'RUB', $hideCategoryName = false)
    {
        $name = $arStage['NAME'] ? : '';
        $color = '';
        $colorScheme = \Bitrix\Crm\Color\DealStageColorScheme::getByCategory(\Bitrix\Crm\Category\DealCategory::resolveFromStageID($arStage['STATUS_ID']));
        if($colorScheme !== null && $colorScheme->isPersistent())
        {
            $element = $colorScheme->getElementByName($arStage['STATUS_ID']);
            if($element !== null)
            {
                $color = $element->getColor();
            }
        }
        $str = '<div class="ev-i-rdm__stage-wrapper" '.(!empty($color) ? 'style="background-color: '.$color.';"' : '').'><div class="ev-i-rdm__stage-title">'.$name.'</div>';
        $str .= '<div class="ev-i-rdm__stage-title_subtitle">';
        $dealDeclension = new Declension('сделка', 'сделки', 'сделок');
        $str .= $count.' '.$dealDeclension->get($count).', ';
        $str .= CurrencyFormat($sum, $currencyId);
        if($hideCategoryName === false) {
            $categoryName = $this->arCategories[\Bitrix\Crm\Category\DealCategory::resolveFromStageID($arStage['STATUS_ID'])];
            $str .= '<br />('.$categoryName.')';
        }
        $str .= '</div></div>';
        return $str;
    }

    protected function formatStageCount($count = 0)
    {
        return '<div class="ev-i-rdm__stage-incoming">'.$count.'</div>';
    }

    protected function formatLoseCount($arLose = [], $currencyId = 'RUB')
    {
        $count = $arLose['COUNT'] ? : 0;
        $sum = $arLose['SUM'] ? : 0;
        $str = '<div class="ev-i-rdm__stage-lose">';
        $dealDeclension = new Declension('сделка', 'сделки', 'сделок');
        $str .= $count.' '.$dealDeclension->get($count).', ';
        $str .= CurrencyFormat($sum, $currencyId);
        $str .= '</div>';
        return $str;
    }

    protected function formatAllNew($count = 0)
    {
        return '<div class="ev-i-rdm__all-new"><div>'.($count > 0 ? '+' : '').$count.'</div><div>Новые</div></div>';
    }

    protected function formatAllSuccess($count = 0, $sum = 0, $currencyId = 'RUB')
    {
        $str = '<div class="ev-i-rdm__all-success"><div class="ev-i-rdm__all-success-count">'.($count > 0 ? '+' : '').$count.'</div>';
        $str .= '<div class="ev-i-rdm__all-success-sum">'.CurrencyFormat($sum, $currencyId).'</div></div>';
        return $str;
    }

    protected function formatAllFailure($count = 0, $sum = 0, $currencyId = 'RUB')
    {
        $dealDeclension = new Declension('сделка', 'сделки', 'сделок');
        $str = '<div class="ev-i-rdm__all-failure">'.$count.' '.$dealDeclension->get($count).', ';
        $str .= CurrencyFormat($sum, $currencyId).'</div>';
        return $str;
    }

    /**
     * Формирует фильтр дл язапроса данных
     *
     * @param $arFilterData
     * @param $arFilter
     */
    protected function makeRequestFilter($arFilterData, &$arFilter)
    {
        if(!empty($arFilterData['CATEGORY'])) {
            $arFilter['=CATEGORY_ID'] = $arFilterData['CATEGORY'];
        }

        if(!empty($arFilterData['AUTHOR'])) {
            $arFilter['=DEAL.ASSIGNED_BY_ID'] = $arFilterData['AUTHOR'];
        }

        if(isset($arFilterData['INTERVAL_from']) && $arFilterData['INTERVAL_from']) {
            $arFilter['>=CREATED_TIME'] = $arFilterData['INTERVAL_from'];
        }
        if(isset($arFilterData['INTERVAL_to']) && $arFilterData['INTERVAL_to']) {
            $arFilter['<=CREATED_TIME'] = $arFilterData['INTERVAL_to'];
        }
    }

    /**
     * Задает параметры для компонента фильтра
     */
    protected function makeFilterComponentData()
    {
        $this->arResult['FILTER_FIELDS'] = [
            ['id' => 'AUTHOR', 'name' => Loc::getMessage('ITRACK_RDMC_COLUMN_AUTHOR_NAME'), 'params' => ['multiple' => true], 'type' => 'custom_entity', 'default' => true],
            ['id' => 'INTERVAL', 'name' => Loc::getMessage('ITRACK_RDMC_COLUMN_INTERVAL_NAME'), 'type' => 'date', 'default' => true],
            ['id' => 'CATEGORY', 'name' => Loc::getMessage('ITRACK_RDMC_COLUMN_CATEGORY_NAME'), 'type' => 'list', 'params' => ['multiple' => true], 'items' => $this->arCategories, 'default' => true],
            ['id' => 'NOT_SHOW_NULL', 'name' => Loc::getMessage('ITRACK_RDMC_COLUMN_NOT_SHOW_NULL_NAME'), 'type' => 'list', 'items' => ['N' => Loc::getMessage('ITRACK_RDMC_NO'), 'Y' =>  Loc::getMessage('ITRACK_RDMC_YES')], 'default' => true]
        ];
    }

    protected function initAdditionalData()
    {
        $arDbCategories = \Bitrix\Crm\Category\DealCategory::getAll(true);
        foreach ($arDbCategories as $arCategory) {
            $this->arCategories[$arCategory['ID']] = $arCategory['NAME'];
        }

        $dbStages = \Bitrix\Crm\StatusTable::query()
            ->setFilter(['%ENTITY_ID' => 'DEAL_STAGE'])
            ->setSelect(['STATUS_ID','NAME', 'SORT', 'ENTITY_ID'])
            ->setOrder(['SORT' => 'asc'])
            ->exec();
        while($arStage = $dbStages->fetch()) {
            $this->arStagesRef[$arStage['STATUS_ID']] = $arStage;
            $this->arCategoryStages[$arStage['ENTITY_ID']][] = $arStage['STATUS_ID'];
        }
    }

    protected function checkMoveBack($currentStage, $prevStage)
    {
        return $this->arStagesRef[$currentStage]['SORT'] < $this->arStagesRef[$prevStage]['SORT'];
    }
}
