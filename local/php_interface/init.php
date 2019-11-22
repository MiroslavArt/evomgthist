<?php

if(file_exists($_SERVER['DOCUMENT_ROOT'].'/bitrix/vendor/autoload.php')) {
    require_once ($_SERVER['DOCUMENT_ROOT'].'/bitrix/vendor/autoload.php');
}

if(Bitrix\Main\Loader::includeModule('itrack.custom')) {
    \iTrack\Custom\Application::init();
}