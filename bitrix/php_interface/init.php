<?

$eventManager = Bitrix\Main\EventManager::getInstance();
/*$eventManager->addEventHandler(
'main',
'OnBeforePhpMail',
	function($event){
		file_put_contents($_SERVER['DOCUMENT_ROOT'].'/bitrix/php_interface/mail.log',date(DATE_COOKIE)."\n".print_r($event, true), FILE_APPEND);
});*/