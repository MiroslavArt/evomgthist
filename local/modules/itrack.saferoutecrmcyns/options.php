<?php

use Bitrix\Main\Localization\Loc
	, Bitrix\Main\Loader
	, Bitrix\Main\Config\Option;

$module_id = 'itrack.saferoutecrmcyns';

use Itrack\Saferoutecrmcyns\Common;

Loader::includeModule($module_id);
Loader::includeModule("crm");


Loc::loadMessages($_SERVER["DOCUMENT_ROOT"] . BX_ROOT . "/modules/main/options.php");
Loc::loadMessages(__FILE__);

if ($APPLICATION->GetGroupRight($module_id) < "S") {
	$APPLICATION->AuthForm(Loc::getMessage("ACCESS_DENIED"));
}


$dealCatIterator = \Bitrix\Crm\Category\Entity\DealCategoryTable::query()
	->setSelect(["ID", "NAME"])
	->setFilter([
	])
	->where("IS_LOCKED", "N")
	->exec()
	->fetchAll();
foreach ($dealCatIterator as $siblingsElement) {
	$ardealCat[$siblingsElement['ID']] = $siblingsElement['NAME'];
}


$enumIDCat = array_keys($ardealCat);
/*
$arDealStatuses['KEY'] = array_keys($arDealStatus);
$arDealStatuses['VALUE'] = array_values($arDealStatus);
*/

$dealDefaultKey = '0';

$lsDealStatus = [];
foreach ($enumIDCat as $valdealCat) {
	$numgID = $valdealCat;
	$sgID = $numgID > 0 ? "DEAL_STAGE_$numgID" : "DEAL_STAGE";
	$arDealStatus = \CCrmStatus::GetStatusList($sgID);

	$lsDealStatus[$numgID]['empty'] = '--';
	foreach ($arDealStatus as $dealKey => $arItem) {
		$lsDealStatus[$numgID][$dealKey] = $arItem;
	}
}

function _cs($str)
{
	return mb_convert_encoding($str, 'utf8', mb_detect_encoding($str));
}

/*
https://saferoute.atlassian.net/wiki/spaces/API/pages/328130    
*/
$arSaferouteStatus = [
        72=> 'Возвращен',
        71=> 'Возвращается отправителю',
        62=> 'Передан для возврата отправителю',
        61=> 'Принят на сортировку для возврата',
        52=> 'Возвращен на сортировку',
        51 =>'Передан на возврат',
	44 => 'Вручен',
	43 => 'Выведен на доставку',
    42 => 'На ПВЗ.',
	412 => 'В городе получателя',
	411 => 'В пути',
	32 => 'Отгружен в компанию доставки',
	31 => 'Принят на сортировке',
	15 => 'В обработке',
	13 => 'Готов к отгрузке',
	12 => 'Подтвержден',
	11 => 'Черновик',
	10 => 'Отменен',
	777 => 'Not found',
];



$listDealsItem = function ($IBLOCK_TYPE = '') {
	$arDealsItem = Bitrix\Crm\UserField\UserFieldManager::getUserFieldEntity(\CCrmOwnerType::Deal)->GetFields();
	$DealsItem = [];
	foreach ($arDealsItem as $arItem) {

		$DealsItem[$arItem["FIELD_NAME"]] = $arItem["EDIT_FORM_LABEL"];
	}
	return $DealsItem;
};


$listBookItem = function ($idFieldRow = '1503') {
	$arBook = [];
	$arBook[0] = 'empty';
	$objdbfield = CUserFieldEnum::GetList(
		[]
		, ["USER_FIELD_ID" => $idFieldRow]
	);
	while ($dfield = $objdbfield->Fetch()) {
		$arBook[$dfield['ID']] = $dfield['VALUE'];
	}
	return $arBook;
};

//	"track_number": "7500991906617",
//	"tracking_url": "http://iml.ru/status",
//	"delivery_date": "04.12.2020",
//	"products": null,
//	"company": "IML"
/*трекномер в пользовательское поле (ПП) сделки  = [Трек-номер] - UF_CRM_1606464347828
2. URL ссылку => в ПП = [URL ссылка для отслеживания трекномера] - UF_CRM_1606465706750
3. Желаемая дата доставки => в ПП = [Желаемая дата доставки] - UF_CRM_1606464298469
4. Название товара => в ПП =[Название книги] - UF_CRM_1606465766006
5. Какая компания проводит доставку => в ПП = [Способ доставки] - UF_CRM_1606464078913*/

$aTabs[] = array(
	'DIV' => 'OSNOVNOE',
	'TAB' => Loc::getMessage('ITRACK_SAFEROUTECRMCYNS_TAB_SETTINGS'),
	'OPTIONS' => array(
		array(
			'login_id',
			Loc::getMessage('ITRACK_SAFEROUTECRMCYNS_OPTIONS_LOGIN_ID'),
			Loc::getMessage('ITRACK_SAFEROUTECRMCYNS_OPTIONS_LOGIN_ID_DEFAULT_VALUE'),
			array(
				'text',
				0
			)
		),
		array(
			'password_token',
			Loc::getMessage('ITRACK_SAFEROUTECRMCYNS_OPTIONS_PASSWORD_TOKEN'),
			Loc::getMessage('ITRACK_SAFEROUTECRMCYNS_OPTIONS_PASSWORD_TOKEN_DEFAULT_VALUE'),
			array(
				'password',
				0
			)
		),
		array(
			'property_deals_sfID',
			Loc::getMessage('ITRACK_SAFEROUTECRMCYNS_DEALS_PROP'),
			Loc::getMessage('ITRACK_SAFEROUTECRMCYNS_DEALS_PROP_DEFAULT_VALUE'),
			array(
				'selectbox',
				$listDealsItem()
			)
		),
		array(
			'property_tracknum',
			Loc::getMessage('ITRACK_SAFEROUTECRMCYNS_TRACKNUMBER'),
			Loc::getMessage('ITRACK_SAFEROUTECRMCYNS_TRACKNUMBER_DEFAULT'),
			array(
				'selectbox',
				$listDealsItem()
			)
		),
		array(
			'property_urltracknum',
			Loc::getMessage('ITRACK_SAFEROUTECRMCYNS_URLTRACKNUMBER'),
			Loc::getMessage('ITRACK_SAFEROUTECRMCYNS_URLTRACKNUMBER_DEFAULT'),
			array(
				'selectbox',
				$listDealsItem()
			)
		),
		array(
			'property_nameoffer',
			Loc::getMessage('ITRACK_SAFEROUTECRMCYNS_NAMEOFFER'),
			Loc::getMessage('ITRACK_SAFEROUTECRMCYNS_NAMEOFFER_DEFAULT'),
			array(
				'selectbox',
				$listDealsItem()
			)
		),
		array(
			'property_companylog',
			Loc::getMessage('ITRACK_SAFEROUTECRMCYNS_COMPANYDEL'),
			Loc::getMessage('ITRACK_SAFEROUTECRMCYNS_COMPANYDEL_DEFAULT'),
			array(
				'selectbox',
				$listDealsItem()
			)
		),
		array(
			'property_wishdelivery',
			Loc::getMessage('ITRACK_SAFEROUTECRMCYNS_WISHDELIVERY'),
			Loc::getMessage('ITRACK_SAFEROUTECRMCYNS_WISHDELIVERY_DEFAULT'),
			array(
				'selectbox',
				$listDealsItem()
			)
		),
		array(
			'setting_set_change',
			Loc::getMessage('ITRACK_SAFEROUTECRMCYNS_SETUPDATE'),
			Loc::getMessage('ITRACK_SAFEROUTECRMCYNS_SETUPDATE_VALUE'),
			array(
				'checkbox',
				$listDealsItem()
			)
		),
		array(
			'setting_set_autochange',
			Loc::getMessage('ITRACK_SAFEROUTECRMCYNS_SETAUTOUPDATE'),
			Loc::getMessage('ITRACK_SAFEROUTECRMCYNS_SETAUTOUPDATE_VALUE'),
			array(
				'checkbox',
				$listDealsItem()
			),
			'Y'
		),
		array(
			'setting_set_autochange_rule',
			Loc::getMessage('ITRACK_SAFEROUTECRMCYNS_RULE_SETAUTOUPDATE'),
			Loc::getMessage('ITRACK_SAFEROUTECRMCYNS_RULE_SETAUTOUPDATE_VALUE'),
			array(
				'checkbox',
				false
			),
			'Y'
		),
		array(
			'make_record_timeline',
			Loc::getMessage('ITRACK_SAFEROUTECRMCYNS_MAKERECORD_IN_TIMELINE'),
			Loc::getMessage('ITRACK_SAFEROUTECRMCYNS_MAKERECORD_IN_TIMELINE_DEFAULT'),
			array(
				'checkbox',
				True
			),
            'Y'
		)
	),
);

$aTabs[] = array(
	"DIV" => "logscron",
	"TAB" => Loc::getMessage("ITRACK_SAFEROUTECRMCYNS_LOG_TAB"),
	"TITLE" => Loc::getMessage("ITRACK_SAFEROUTECRMCYNS_LOG_TAB_TITLE"),
	"OPTIONS" => array(array(
		'cron_every_time',
		Loc::getMessage('ITRACK_SAFEROUTECRMCYNS_CRON_RUNTIME'),
		'',//Loc::getMessage('ITRACK_SAFEROUTECRMCYNS_OPTIONS_LOGIN_ID_DEFAULT_VALUE'),
		array(
			'text',
			0
		)
	))
);

foreach ($enumIDCat as $numIDCat) {
	$arCategory = array(
		'DIV' => "CATEGORY$numIDCat",
		'TAB' => $ardealCat[$numIDCat],//Loc::getMessage('ITRACK_SAFEROUTECRMCYNS_TAB_SETTINGS'),
		'OPTIONS' => [],
	);

	foreach ($arSaferouteStatus as $keySf => $valSf) {
		$arCategory['OPTIONS'][] = array(
			"property_sf_" . $numIDCat . "_" . $keySf,
			$valSf,
			'',
			array(
				'selectbox',
				$lsDealStatus[$numIDCat]
			)
		);
	}

	if ($numIDCat == 41) $aTabs[] = $arCategory;
}


$aTabs[] = array(
	"DIV" => "rights",
	"TAB" => Loc::getMessage("MAIN_TAB_RIGHTS"),
	"TITLE" => Loc::getMessage("MAIN_TAB_TITLE_RIGHTS"),
	"OPTIONS" => array()
);


$arFilter = array(
	"NAME" => "\\Itrack\\Saferoutecrmcyns\\Common::syncHoockDeals();"
);
$arResult["AGENT_RUN"] = CAgent::GetList(array("SORT" => "ASC"), $arFilter)->Fetch();


#Сохранение

if ($request->isPost() && $request['Apply'] && check_bitrix_sessid()) {

	foreach ($aTabs as $aTab) {
		foreach ($aTab['OPTIONS'] as $arOption) {
			if (!is_array($arOption))
				continue;

			if ($arOption['note'])
				continue;


			$optionName = $arOption[0];

			$optionValue = $request->getPost($optionName);
			if ($optionValue == 'empty') {

				Option::delete($module_id, array(
					"name" => $optionName
				));

			}
			if ($optionValue != 'empty') {
				Option::set($module_id, $optionName, is_array($optionValue) ? implode(",", $optionValue) : $optionValue);
			}
		}
	}
}

$tabControl = new CAdminTabControl('tabControl', $aTabs);

?>
<? $tabControl->Begin(); ?>
<form method='post'
      action='<? echo $APPLICATION->GetCurPage() ?>?mid=<?= htmlspecialcharsbx($request['mid']) ?>&lang=<?= $request['lang'] ?>'
      name='lsettings_comments_settings'>
	<? foreach ($aTabs as $aTab):
		if ($aTab['OPTIONS']):?>
			<? $tabControl->BeginNextTab(); ?>
			<? if ($aTab['DIV'] == 'logscron') { ?>
                <tr>
                    <td width="100%" style="" colspan="2">
                        <a href="/bitrix/admin/agent_list.php?PAGEN_1=1&SIZEN_1=20&lang=ru&set_filter=Y&adm_filter_applied=0&find_type=id&find_module_id=<?= $module_id ?>"
                           target="_blank"
                        >
							<?= GetMessage("ITRACK_SAFEROUTECRMCYNS_AGENT_VIEWINLIST"); ?></a>.
						<?= GetMessage("ITRACK_SAFEROUTECRMCYNS_AGENT_LOST_TIME"); ?> <?= $arResult["AGENT_RUN"]["LAST_EXEC"]; ?>
						<? echo "<pre>";
						print_r($arResult["AGENT_RUN"]);
						echo "</pre>"; ?>
                        </br> <?= GetMessage("ITRACK_SAFEROUTECRMCYNS_AGENT_TIMELASTUPDATE"); ?>

                    </td>
                </tr>

			<? } ?>
			<? __AdmSettingsDrawList($module_id, $aTab['OPTIONS']); ?>
		<? endif;
	endforeach; ?>
	<?
	$tabControl->BeginNextTab();

	require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/admin/group_rights.php");

	$tabControl->Buttons(); ?>
    <input type="submit"
           name="Apply"
           value="<? echo GetMessage('MAIN_SAVE') ?>">
    <input type="reset"
           name="reset"
           value="<? echo GetMessage('MAIN_RESET') ?>">
	<?= bitrix_sessid_post(); ?>
</form>
<? $tabControl->End(); 
			
// print_r($listDealsItem());			
// echo "<pre>"; print_r ($listDealsItem()); echo "</pre>";			
			?>

