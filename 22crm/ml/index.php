<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");

$APPLICATION->IncludeComponent("bitrix:crm.ml", ".default", array(
	'SEF_FOLDER' => '/22crm/ml/',
));

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");