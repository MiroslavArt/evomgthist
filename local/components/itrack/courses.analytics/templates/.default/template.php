<?php
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
/**
 * @var CBitrixComponentTemplate $this
 */
$this->addExternalJs($this->__folder.'/dist/bundle.js');

$bodyClass = $APPLICATION->getPageProperty('BodyClass', false);
$APPLICATION->setPageProperty('BodyClass', trim(sprintf('%s %s', $bodyClass, ' pagetitle-toolbar-field-view no-background no-paddings')));
?>

<?/*<div class="">
    <a class="btn btn-primary" href="<?=$arResult['PAGE_URL'];?><?if(!$arParams['IS_PAYMENTS']):?>?type=payments<?endif;?>">
        <?php
        if($arParams['IS_PAYMENTS']){?>
            Обучение
        <?php
        } else {?>
            Оплаты
        <?php
        }?>
    </a>
</div>
*/?>
<div id="ica__page" data-category="<?=$arParams['CATEGORY_ID'];?>">
    <ul class="nav nav-pills">
        <li class="nav-item">
            <a href="<?=$arResult['PAGE_URL'];?>" class="nav-link<?if(!$arParams['IS_PAYMENTS']):?> active disabled<?endif;?>">Обучение</a>
        </li>
        <li class="nav-item">
            <a href="<?=$arResult['PAGE_URL'];?>?type=payments" class="nav-link<?if($arParams['IS_PAYMENTS']):?> active disabled<?endif;?>">Оплаты</a>
        </li>
    </ul>

    <?php
    $APPLICATION->IncludeComponent(
        'bitrix:main.ui.filter',
        '',
        [
            'FILTER_ID' => $arParams['FILTER_ID'],
            'GRID_ID' => $arParams['FILTER_ID'],
            'FILTER' => $arResult['FILTER_FIELDS'],
            'FILTER_PRESETS' => $arResult['FILTER_PRESETS'],
            'ENABLE_LIVE_SEARCH' => false,
            'ENABLE_LABEL' => true
        ],
        false
    );?>
    <?php
    echo Bitrix\Iblock\Helpers\Filter\Property::render($arParams['FILTER_ID'], 'employee', array(array('FIELD_ID' => 'ASSIGNED_BY_ID')));
    ?>

    <?php
    if($arParams['IS_PAYMENTS']){?>
        <div class="ca__result-table" id="payments-table"></div>
    <?php
    } else {?>
        <div class="ca__result-table" id="courses-table"></div>
    <?php
    }
    ?>
</div>
