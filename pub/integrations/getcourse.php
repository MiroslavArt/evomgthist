<?php
define("PUBLIC_AJAX_MODE", true);
define("NO_KEEP_STATISTIC", "Y");
define("NO_AGENT_STATISTIC","Y");
define("NOT_CHECK_PERMISSIONS", true);
define("DisableEventsCheck", true);
define("NO_AGENT_CHECK", true);

global $APPLICATION;

require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

if(Bitrix\Main\Loader::includeModule('itrack.custom')) {
    $gk = new iTrack\Custom\Integration\Getcourse\Getcourseimp();
    $gk->processWebhook();
}

die();


