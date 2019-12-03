<?php
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
/**
 * @var CBitrixComponentTemplate $this
 */
$this->addExternalJs($this->__folder.'/dist/bundle.js');
?>

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

<div class="" id="courses-table">

</div>