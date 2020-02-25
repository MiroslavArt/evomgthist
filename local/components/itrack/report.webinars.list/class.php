<?php

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\UI;
use Bitrix\Main\Grid;

Loc::loadMessages(__FILE__);

class CItrackReportWebinarsList extends \CBitrixComponent
{
    private $modules = ['crm','iblock'];
    protected $arSort;
    protected $obNav;
    protected $webinarsIblockId;
    protected $accountId;

    public function onPrepareComponentParams($arParams)
    {
        if(empty($arParams['LIST_ID'])) {
            $arParams['LIST_ID'] = 'report_webinars_list';
        }
        return $arParams;
    }

    public function executeComponent()
    {
        if($this->checkModules()) {
            $urlTemplates = [
                'counter' => '#webinar_id#/#counter#/',
            ];
            $page = \CComponentEngine::parseComponentPath('/report/webinars/', $urlTemplates, $arVars);
            if(!empty($page) && in_array($page, ['counter'])) {
                $this->initAdditionalData();
                $this->processDealList($arVars);
                $this->includeComponentTemplate('ajax');
            } else {
                $this->makeResult();
                $this->includeComponentTemplate();
            }
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

    protected function processDealList($arVars)
    {
        $this->arResult['AJAX_DATA'] = [
            'ACTION' => 'dealList',
            'DATA' => []
        ];

        if(in_array($arVars['counter'], ['registered','unregistered','enter','finish']) && !empty($arVars['webinar_id'])) {
            $dbItems = \CIBlockElement::GetList(
                [],
                ['IBLOCK_ID' => $this->webinarsIblockId, '=ID' => $arVars['webinar_id']],
                false,
                false,
                ['ID', 'CODE','NAME','PROPERTY_ROOM_ID']
            );
            if($arItem = $dbItems->Fetch()) {
                $arIdPart = explode('*', $arItem['CODE']);
                $parsedDate = \DateTime::createFromFormat('Y-m-d\TH:i:s', $arIdPart[1]);
                $arDealsFilter = [
                    'CHECK_PERMISSIONS' => 'N',
                    'CATEGORY_ID' => 50,
                    'UF_CRM_1582128672' => str_replace($this->accountId.':','',$arItem['PROPERTY_ROOM_ID_VALUE']),
                    '>=UF_CRM_1582128645' => $parsedDate->format('d.m.Y').' 00:00:00',
                    '<UF_CRM_1582128645' => $parsedDate->format('d.m.Y').' 23:59:59'
                ];
                $dbDeals = \CCrmDeal::GetListEx(
                    [],
                    $arDealsFilter,
                    false,
                    false,
                    ['ID','STAGE_ID','UF_CRM_1581578029']
                );
                while($arDeal = $dbDeals->Fetch()) {
                    if($arVars['counter'] === 'registered') {
                        if(strpos($arDeal['UF_CRM_1581578029'], 'Вебинар Бизон365') === false) {
                            $this->arResult['AJAX_DATA']['DATA']['FILTER']['=ID'][] = $arDeal['ID'];
                        }
                    }
                    if($arVars['counter'] === 'unregistered') {
                        if(strpos($arDeal['UF_CRM_1581578029'], 'Вебинар Бизон365') !== false) {
                            $this->arResult['AJAX_DATA']['DATA']['FILTER']['=ID'][] = $arDeal['ID'];
                        }
                    }
                    if($arVars['counter'] === 'enter') {
                        if($arDeal['STAGE_ID'] === 'C50:PREPARATION') {
                            $this->arResult['AJAX_DATA']['DATA']['FILTER']['=ID'][] = $arDeal['ID'];
                        }
                    }
                    if($arVars['counter'] === 'finish') {
                        if($arDeal['STAGE_ID'] === 'C50:WON') {
                            $this->arResult['AJAX_DATA']['DATA']['FILTER']['=ID'][] = $arDeal['ID'];
                        }
                    }
                }
                $this->arResult['AJAX_DATA']['DATA']['FILTER']['STAGE_SEMANTIC_ID'] = ['P','S','F'];
            }
        }
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


        $obFilterOption = new UI\Filter\Options($this->arParams['LIST_ID'], $this->arResult['FILTER_PRESETS']);
        $arFilterData = $obFilterOption->getFilter();
        $arFilter = [
            'IBLOCK_ID' => $this->webinarsIblockId,
            'ACTIVE' => 'Y'
        ];
        $this->makeRequestFilter($arFilterData, $arFilter);

        $this->arResult['COLUMNS'] = [
            ['id' => 'TITLE', 'name' => Loc::getMessage('ITRACK_RWL_COLUMN_TITLE'), 'type' => 'text', 'default' => true],
            ['id' => 'REGISTERED', 'name' => Loc::getMessage('ITRACK_RWL_COLUMN_REGISTERED'), 'type' => 'text', 'default' => true],
            ['id' => 'UNREGISTERED', 'name' => Loc::getMessage('ITRACK_RWL_COLUMN_UNREGISTERED'), 'type' => 'text', 'default' => true],
            ['id' => 'ENTER', 'name' => Loc::getMessage('ITRACK_RWL_COLUMN_ENTER'), 'type' => 'text', 'default' => true],
            ['id' => 'FINISH', 'name' => Loc::getMessage('ITRACK_RWL_COLUMN_FINISH'), 'type' => 'text', 'default' => true]
        ];


        $dbItems = \CIBlockElement::GetList(
            [],
            $arFilter,
            false,
            false,
            ['ID', 'CODE','NAME','PROPERTY_ROOM_ID']
        );

        $this->arResult['ITEMS'] = [];

        while($arItem = $dbItems->Fetch()) {
            $arIdPart = explode('*', $arItem['CODE']);
            $parsedDate = \DateTime::createFromFormat('Y-m-d\TH:i:s', $arIdPart[1]);
            $dbDeals = \CCrmDeal::GetListEx(
                [],
                [
                    'CHECK_PERMISSIONS' => 'N',
                    'CATEGORY_ID' => 50,
                    'UF_CRM_1582128672' => str_replace($this->accountId.':','',$arItem['PROPERTY_ROOM_ID_VALUE']),
                    '>=UF_CRM_1582128645' => $parsedDate->format('d.m.Y').' 00:00:00',
                    '<UF_CRM_1582128645' => $parsedDate->format('d.m.Y').' 23:59:59'
                ],
                false,
                false,
                ['ID','STAGE_ID','UF_CRM_1581578029']
            );
            $registered = 0;
            $unregistered = 0;
            $enter = 0;
            $finish = 0;
            while($arDeal = $dbDeals->Fetch()) {
                if(strpos($arDeal['UF_CRM_1581578029'], 'Вебинар Бизон365') !== false) {
                    $unregistered++;
                } else {
                    $registered++;
                }
                if($arDeal['STAGE_ID'] === 'C50:WON') {
                    $finish++;
                } elseif($arDeal['STAGE_ID'] === 'C50:PREPARATION') {
                    $enter++;
                }
            }
            if($registered > 0 || $unregistered > 0) {
                $this->arResult['ITEMS'][] = [
                    'data' => $arItem,
                    'columns' => [
                        'TITLE' => $arItem['NAME'],
                        'REGISTERED' => '<a onclick="BX.iTrack.Component.ReportWebinarsList.openList(\''.$arItem['ID'].'\',\'registered\');" href="javascript:void(0);">'.$registered.'</a>',
                        'UNREGISTERED' => '<a onclick="BX.iTrack.Component.ReportWebinarsList.openList(\''.$arItem['ID'].'\',\'unregistered\');" href="javascript:void(0);">'.$unregistered.'</a>',
                        'ENTER' => '<a onclick="BX.iTrack.Component.ReportWebinarsList.openList(\''.$arItem['ID'].'\',\'enter\');" href="javascript:void(0);">'.$enter.'</a>',
                        'FINISH' => '<a onclick="BX.iTrack.Component.ReportWebinarsList.openList(\''.$arItem['ID'].'\',\'finish\');" href="javascript:void(0);">'.$finish.'</a>'
                    ]
                ];
            }
        }

        //$this->obNav->setRecordCount($rsItems->getCount());
        $this->arResult['TOTAL_ROWS_COUNT'] = $dbItems->SelectedRowsCount();
    }

    /**
     * Формирует фильтр дл язапроса данных
     *
     * @param $arFilterData
     * @param $arFilter
     */
    protected function makeRequestFilter($arFilterData, &$arFilter)
    {
        if(!empty($arFilterData['TITLE'])) {
            $arFilter['NAME'] = $arFilterData['TITLE'];
        }
        if(!empty($arFilterData['FIND'])) {
            $arFilter['%NAME'] = $arFilterData['FIND'];
        }
    }

    /**
     * Задает параметры для компонента фильтра
     */
    protected function makeFilterComponentData()
    {
        $this->arResult['FILTER_FIELDS'] = [
            ['id' => 'TITLE', 'name' => Loc::getMessage('ITRACK_RWL_COLUMN_TITLE'), 'type' => 'text', 'default' => true]
        ];
    }

    protected function initAdditionalData()
    {
        $this->webinarsIblockId = Option::get('itrack.custom', 'webinars_iblock_id', 0);
        $this->accountId = Option::get('itrack.custom', 'bizon365_id', 0);
    }
}