<?php
require_once ($_SERVER['DOCUMENT_ROOT'].'/bitrix/header.php');
$APPLICATION->SetTitle('Вебинары');
?>

<?php
$APPLICATION->IncludeComponent(
    'itrack:report.webinars.list',
    '',
    [],
    false
);
?>

<?php
require_once ($_SERVER['DOCUMENT_ROOT'].'/bitrix/footer.php');