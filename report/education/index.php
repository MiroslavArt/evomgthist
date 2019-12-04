<?php
require_once ($_SERVER['DOCUMENT_ROOT'].'/bitrix/header.php');
$APPLICATION->SetTitle('Доска прогресса');
?>

<?php
$APPLICATION->IncludeComponent(
    'itrack:courses.analytics',
    '',
    [
        'CATEGORY_ID' => 47,
        'TYPE' => $_REQUEST['type']
    ],
    false
);
?>

<?php
require_once ($_SERVER['DOCUMENT_ROOT'].'/bitrix/footer.php');
