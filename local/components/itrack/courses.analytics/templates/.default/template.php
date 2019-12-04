<?php
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
/**
 * @var CBitrixComponentTemplate $this
 */
$this->addExternalJs($this->__folder.'/dist/bundle.js');
?>

<div class="">
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

<form id="filter" class="form" data-category="<?=$arParams['CATEGORY_ID'];?>">
    <div class="row">
        <div class="col-sm form-group">
            <label for="filter-course">Курс</label>
            <select id="filter-course" class="form-control">
            </select>
        </div>
        <div class="col-sm form-group">
            <label for="filter-date">Дата старта</label>
            <input id="filter-date" type="text" class="form-control datepicker" data-provide="datepicker">
        </div>
    </div>
</form>

<?php
if($arParams['IS_PAYMENTS']){?>
    <div class="" id="payments-table"></div>
<?php
} else {?>
    <div class="" id="courses-table"></div>
<?php
}
?>