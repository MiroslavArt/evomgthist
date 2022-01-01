<?php
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

$bodyClass = $APPLICATION->getPageProperty('BodyClass', false);
$APPLICATION->setPageProperty('BodyClass', trim(sprintf('%s %s', $bodyClass, ' pagetitle-toolbar-field-view no-background')));
?>
<div class="ev-i-rdm">
    <div class="pagetitle-container toolbar__container_space-b">
        <div class="pagetitle-container page-title-align-left-container">
            <?php
            $APPLICATION->IncludeComponent(
                'bitrix:main.ui.filter',
                '',
                [
                    'FILTER_ID' => $arParams['LIST_ID'],
                    'GRID_ID' => $arParams['LIST_ID'],
                    'FILTER' => $arResult['FILTER_FIELDS'],
					'FILTER_PRESETS' => $arResult['FILTER_PRESETS'],
                    'ENABLE_LIVE_SEARCH' => false,
                    'ENABLE_LABEL' => true
                ],
                false
            );?>
        </div>
    </div>
    <?php
    echo Bitrix\Iblock\Helpers\Filter\Property::render($arParams['LIST_ID'], 'employee', array(array('FIELD_ID' => 'AUTHOR')));
    ?>
    <?
    $APPLICATION->IncludeComponent(
        'bitrix:main.ui.grid',
        '',
        [
            'GRID_ID' => $arParams['LIST_ID'],
            'COLUMNS' => $arResult['COLUMNS'],
            'ROWS' => $arResult['ITEMS'],
            'SHOW_ROW_CHECKBOXES' => false,
            'NAV_OBJECT' => $arResult['NAV_OBJECT'],
            'AJAX_MODE' => 'Y',
            'AJAX_ID' => \CAjax::getComponentID('bitrix:main.ui.grid', '.default', ''),
            'PAGE_SIZES' => [],
            'AJAX_OPTION_JUMP' => 'N',
            'SHOW_CHECK_ALL_CHECKBOXES' => false,
            'SHOW_ROW_ACTIONS_MENU' => true,
            'SHOW_GRID_SETTINGS_MENU' => true,
            'SHOW_NAVIGATION_PANEL' => false,
            'SHOW_PAGINATION' => false,
            'SHOW_SELECTED_COUNTER' => false,
            'SHOW_TOTAL_COUNTER' => false,
            'SHOW_PAGESIZE' => false,
            'SHOW_ACTION_PANEL' => false,
            'ALLOW_COLUMNS_SORT' => true,
            'ALLOW_COLUMNS_RESIZE' => true,
            'ALLOW_HORIZONTAL_SCROLL' => true,
            'ALLOW_SORT' => true,
            'ALLOW_PIN_HEADER' => true,
            'AJAX_OPTION_HISTORY' => 'N',
            'TOTAL_ROWS_COUNT' => $arResult['TOTAL_ROWS_COUNT'],
            'ENABLE_COLLAPSIBLE_ROWS' => false,
            'ALLOW_STICKED_COLUMNS' => true
        ],
        false
    ); ?>
</div>
