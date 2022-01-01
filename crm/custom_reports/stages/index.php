<?php
require_once ($_SERVER['DOCUMENT_ROOT'].'/bitrix/header.php');
$APPLICATION->SetTitle('Отчет о перемещениях сделок');
?>
<?$APPLICATION->IncludeComponent('itrack:report.deal.move','',[],false);?>

<?require_once ($_SERVER['DOCUMENT_ROOT'].'/bitrix/footer.php');