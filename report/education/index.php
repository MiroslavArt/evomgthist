<?php
require_once ($_SERVER['DOCUMENT_ROOT'].'/bitrix/header.php');
?>

<?php
$APPLICATION->IncludeComponent(
    'itrack:courses.analytics',
    '',
    ['CATEGORY_ID' => 47],
    false
);
?>

<?php
require_once ($_SERVER['DOCUMENT_ROOT'].'/bitrix/footer.php');
