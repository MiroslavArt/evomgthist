<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();
?>

<?php
//$APPLICATION->RestartBuffer();
?>

<?php
switch($arResult['AJAX_DATA']['ACTION']) {
    case 'dealList':
        $APPLICATION->IncludeComponent(
            'bitrix:ui.sidepanel.wrapper',
            '',
            [
                'POPUP_COMPONENT_NAME' => 'bitrix:crm.deal.list',
                'POPUP_COMPONENT_TEMPLATE_NAME' => '',
                'POPUP_COMPONENT_PARAMS' => [
                    'INTERNAL_FILTER' => $arResult['AJAX_DATA']['DATA']['FILTER'],
                    'HIDE_FILTER' => true
                ]
            ]
        );
        //$APPLICATION->IncludeComponent('bitrix:crm.deal.list','', [], false);
        break;
}
?>