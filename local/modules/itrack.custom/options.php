<?php

$MODULE_ID = 'itrack.custom';

use Bitrix\Main\Config\Option;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Application;
use Bitrix\Main\Loader;

$app = Application::getInstance();
$context = $app->getContext();
$request = $context->getRequest();
Loc::loadMessages($context->getServer()->getDocumentRoot()."/bitrix/modules/main/options.php");
Loc::loadMessages(__FILE__);

global $USER;
if (!$USER->CanDoOperation($MODULE_ID . '_settings')) {
    $APPLICATION->AuthForm(Loc::getMessage("ACCESS_DENIED"));
}

if(!Loader::includeModule('iblock')) {
    ShowError(Loc::GetMessage($MODULE_ID."_MODULE_iblock_NOT_INSTALLED"));
    return;
}

$arIblocks = [];
$dbIblock = \Bitrix\Iblock\IblockTable::query()
    ->setSelect(['ID','NAME'])
    ->exec();
while($arIBlock = $dbIblock->fetch()) {
    $arIblocks[$arIBlock['ID']] = '['.$arIBlock['ID'].']: '.$arIBlock['NAME'];
}

$arAllOptions = [
    'bizon365' => [
        [
            'bizon365_id',
            Loc::getMessage($MODULE_ID.'_bizon365_id'),
            Option::get($MODULE_ID, 'bizon365_id'),
            ['text']
        ],
        [
            'bizon365_token',
            Loc::getMessage($MODULE_ID.'_bizon365_token'),
            Option::get($MODULE_ID, 'bizon365_token'),
            ['text']
        ],
        [
            'webinars_iblock_id',
            Loc::getMessage($MODULE_ID.'_webinars_iblock_id'),
            Option::get($MODULE_ID, 'webinars_iblock_id'),
            [
                'selectbox',
                $arIblocks
            ]
        ],
    ],
    'sendpulse' => [
        [
            'sendpulse_id',
            Loc::getMessage($MODULE_ID.'_sendpulse_id'),
            Option::get($MODULE_ID, 'sendpulse_id'),
            ['text']
        ],
        [
            'sendpulse_token',
            Loc::getMessage($MODULE_ID.'_sendpulse_token'),
            Option::get($MODULE_ID, 'sendpulse_token'),
            ['text']
        ],
        [
            'sendpulse_sync_books',
            Loc::getMessage($MODULE_ID.'_sendpulse_sync_books'),
            Option::get($MODULE_ID, 'sendpulse_sync_books'),
            ['checkbox']
        ]
    ]
];

if(isset($request["save"]) && check_bitrix_sessid()) {
    foreach ($arAllOptions as $part) {
        foreach($part as $arOption) {
            if(is_array($arOption)) {
                __AdmSettingsSaveOption($MODULE_ID, $arOption);
            }
        }
    }
}

$arTabs = [
    [
        "DIV" => "bizon365",
        "TAB" => Loc::getMessage($MODULE_ID.'_bizon365_tab_title'),
        "ICON" => $MODULE_ID . '_settings',
        "TITLE" => Loc::getMessage($MODULE_ID.'_bizon365_tab_subtitle'),
        'TYPE' => 'options', //options || rights || user defined
    ],
    [
        "DIV" => "sendpulse",
        "TAB" => Loc::getMessage($MODULE_ID.'_sendpulse_tab_title'),
        "ICON" => $MODULE_ID . '_settings',
        "TITLE" => Loc::getMessage($MODULE_ID.'_sendpulse_tab_subtitle'),
        'TYPE' => 'options', //options || rights || user defined
    ]
];

$tabControl = new CAdminTabControl("tabControl", $arTabs);

$tabControl->Begin();
?>
<form method="POST" action="<?= $APPLICATION->GetCurPage() ?>?mid=<?= htmlspecialcharsbx($mid) ?>&amp;lang=<?= LANG ?>"
      name="<?= $MODULE_ID ?>_settings">
    <?= bitrix_sessid_post(); ?>
    <?
    foreach ($arTabs as $tab) {
        $tabControl->BeginNextTab();
        __AdmSettingsDrawList($MODULE_ID, $arAllOptions[$tab['DIV']]);
    }?>
    <?$tabControl->Buttons();?>
    <input type="submit" class="adm-btn-save" name="save" value="<?=Loc::getMessage($MODULE_ID.'_save');?>">
    <?=bitrix_sessid_post();?>
    <? $tabControl->End(); ?>
</form>
