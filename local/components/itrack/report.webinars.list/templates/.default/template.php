<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
/** @var array \$arParams */
/** @var array \$arResult */
/** @global \CMain \$APPLICATION */
/** @global \CUser \$USER */
/** @global \CDatabase \$DB */
/** @var \CBitrixComponentTemplate \$this */
/** @var string \$templateName */
/** @var string \$templateFile */
/** @var string \$templateFolder */
/** @var string \$componentPath */
/** @var array \$templateData */
/** @var \CBitrixComponent \$component */
$this->setFrameMode(true);

use \Bitrix\Main\Localization\Loc;

\Bitrix\Main\UI\Extension::load(array('ui.hint'));

$bodyClass = $APPLICATION->getPageProperty('BodyClass', false);
$APPLICATION->setPageProperty('BodyClass', trim(sprintf('%s %s', $bodyClass, ' pagetitle-toolbar-field-view no-background')));
?>
<?php
$APPLICATION->IncludeComponent(
    'bitrix:main.ui.filter',
    '',
    [
        'FILTER_ID' => $arParams['LIST_ID'],
        'GRID_ID' => $arParams['LIST_ID'],
        'FILTER' => $arResult['FILTER_FIELDS'],
        'ENABLE_LIVE_SEARCH' => true,
        'ENABLE_LABEL' => true
    ],
    false
);?>
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
        'PAGE_SIZES' => [
            ['NAME' => '5', 'VALUE' => '5'],
            ['NAME' => '10', 'VALUE' => '10'],
            ['NAME' => '20', 'VALUE' => '20'],
            ['NAME' => '50', 'VALUE' => '50'],
            ['NAME' => '100', 'VALUE' => '100']
        ],
        'AJAX_OPTION_JUMP' => 'N',
        'SHOW_CHECK_ALL_CHECKBOXES' => false,
        'SHOW_ROW_ACTIONS_MENU' => true,
        'SHOW_GRID_SETTINGS_MENU' => true,
        'SHOW_NAVIGATION_PANEL' => false,
        'SHOW_PAGINATION' => false,
        'SHOW_SELECTED_COUNTER' => false,
        'SHOW_TOTAL_COUNTER' => true,
        'SHOW_PAGESIZE' => true,
        'SHOW_ACTION_PANEL' => true,
        'ALLOW_COLUMNS_SORT' => true,
        'ALLOW_COLUMNS_RESIZE' => true,
        'ALLOW_HORIZONTAL_SCROLL' => true,
        'ALLOW_SORT' => true,
        'ALLOW_PIN_HEADER' => true,
        'AJAX_OPTION_HISTORY' => 'N',
        'TOTAL_ROWS_COUNT' => $arResult['TOTAL_ROWS_COUNT'],
        'ENABLE_COLLAPSIBLE_ROWS' => false
    ],
    false
); ?>