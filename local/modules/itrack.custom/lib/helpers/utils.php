<?php

namespace Itrack\Custom\Helpers;

use Bitrix\Main;
use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Diag\Debug;
use Bitrix\Main\IO;
use Bitrix\Main\Text\Encoding;
use Bitrix\Main\Loader;
use Bitrix\Iblock;
use Bitrix\Main\Data;
use Bitrix\Main\Type;
use Bitrix\Sale\Delivery;
use Bitrix\Main\SystemException;
use Bitrix\Highloadblock\HighloadBlockTable as HLBT;


class Utils
{
    const MODULE_ID = 'itrack.custom';

    public static $paramsTranslit = [
        'max_len' => '100', // обрезает символьный код до 100 символов
        'change_case' => 'L', // буквы преобразуются к нижнему регистру
        'replace_space' => '_', // меняем пробелы на нижнее подчеркивание
        'replace_other' => '_', // меняем левые символы на нижнее подчеркивание
        'delete_repeat_replace' => 'true', // удаляем повторяющиеся нижние подчеркивания
        'use_google' => 'false', // отключаем использование google
    ];

    public static function GetEntityDataClass($HlBlockId)
    {
        \Bitrix\Main\Loader::includeModule('highloadblock');
        if (empty($HlBlockId) || $HlBlockId < 1)
        {
            return false;
        }
        $hlblock = HLBT::getById($HlBlockId)->fetch();
        $entity = HLBT::compileEntity($hlblock);
        $entity_data_class = $entity->getDataClass();
        return $entity_data_class;
    }

    public static function getFileExt($filename)
    {
        $array = explode('/', $filename);
        if ($array) {
            $filename = end($array);
        }

        $arParts = explode('.', $filename);
        if (count($arParts) > 1) {
            $ext = trim(array_pop($arParts));
            if (strlen($ext) == 0 || strlen($ext) > 4 || preg_match('/^(\d+)$/', $ext)) {
                return '';
            }
            if (ToLower($ext) == 'gz' && count($arParts) > 1) {
                $ext = array_pop($arParts) . '.' . $ext;
            }
            return $ext;
        } else {
            return '';
        }
    }

    public static function getFileName($fn)
    {
        global $APPLICATION;
        if (file_exists($_SERVER['DOCUMENT_ROOT'] . $fn)) {
            return $fn;
        }

        if (defined("BX_UTF")) {
            $tmpfile = $APPLICATION->ConvertCharsetArray($fn, LANG_CHARSET, 'CP1251');
        } else {
            $tmpfile = $APPLICATION->ConvertCharsetArray($fn, LANG_CHARSET, 'UTF-8');
        }

        if (file_exists($_SERVER['DOCUMENT_ROOT'] . $tmpfile)) {
            return $tmpfile;
        }

        return false;
    }

    public static function reArrayFiles(&$file_post)
    {

        $file_ary = array();
        $file_count = count($file_post['name']);
        $file_keys = array_keys($file_post);

        for ($i = 0; $i < $file_count; $i++) {
            foreach ($file_keys as $key) {
                $file_ary[$i][$key] = $file_post[$key][$i];
            }
        }

        return $file_ary;
    }

    /**
     * Проверка, кодировка сайта UTF-8 или нет
     * @return bool
     */
    public static function isUTF()
    {
        return (defined('BX_UTF') && BX_UTF === true);
    }

    public static function prepareEncoding($data)
    {
        if (self::isUTF()) return $data;
        return Encoding::convertEncoding($data, 'WINDOWS-1251', 'UTF-8');
    }

    /**
     * @param $request
     * @param string $delimiter
     * @return array
     */
    public static function explodeYandexReg($request, $delimiter = ' ')
    {
        $arrGeo = array();

        if (!empty($request)) {
            $arrGeo['latitude'] = explode($delimiter, $request, 2)[0];
            $arrGeo['longitude'] = explode($delimiter, $request, 2)[1];
        }

        return $arrGeo;
    }

    public static function getInfo($id)
    {
        $arResult = [];

        if (!empty($id) && Loader::includeModule('iblock')) {
            $arResult = Iblock\IblockTable::getList([
                'filter' => ['ID' => $id, 'ACTIVE' => 'Y'],
                'select' => ['NAME', 'DESCRIPTION'],
                'cache' => ['ttl' => 86400]
            ])->fetch();
        }

        return $arResult;

    }

    public static function strposSubstr($value, $needle = '(#')
    {
        $str = '';
        $pos = strpos($value, $needle);
        if ($pos !== false) {
            $str = substr($value, 0, $pos);
        } else {
            $str = $value;
        }

        return trim($str);
    }

    public static function pregMatchReplace($value, $pattern = '/\d./i')
    {
        $str = '';
        if (preg_match($pattern, $value, $matches)) {
            $strTemp = (string)$matches[0];
            $pos = strpos($value, $strTemp);
            if ($pos !== false && $pos === 0) {
                $str = substr($value, $pos + strlen($strTemp), strlen($value));
            } else {
                $str = $value;
            }

        } else {
            $str = $value;
        }

        return trim($str);
    }

    public static function mbUcfirst($text)
    {
        mb_internal_encoding("UTF-8");
        return mb_strtoupper(mb_substr($text, 0, 1)) . mb_substr($text, 1);
    }

    public static function logger($entity, $data, $title = '', $logFile = 'log.log')
    {

        $pathLog = Option::get(self::MODULE_ID, 'FOLDER_LOG', '/upload/logs/');

        $path = Application::getDocumentRoot() . $pathLog;

        $dir = new IO\Directory($path);

        if (!$dir->isExists()) {
            $dir->create();
        }

        $objDateTime = new Type\DateTime();

        $file = $pathLog . $logFile;

        Debug::writeToFile(
            [
                'TITLE' => $title,
                'ENTITY' => $entity,
                'DATE' => $objDateTime->format("Y-m-d H:i:s"),
                'DATA' => [$data]
            ],
            'LOG: ' . $title . ' : ' . $objDateTime->format("Y-m-d H:i:s"),
            $file
        );
    }

    public static function mapFiles($value)
    {
        $io = \CBXVirtualIo::GetInstance();

        if (!is_array($value))
            $value = array(
                $value,
            );

        $result = array();
        $j = 0;
        foreach ($value as $i => $file_name) {
            if (strlen($file_name) > 0) {
                if (preg_match("/^(ftp|ftps|http|https):\\/\\//", $file_name))
                    $arFile = \CFile::MakeFileArray($file_name);
                else
                    $arFile = \CFile::MakeFileArray($io->GetPhysicalName($file_name));

                if (isset($arFile["tmp_name"]))
                    $result["n" . ($j++)] = $arFile;
            }
        }
        return $result;
    }

    public static function imgResize($fileId, $width, $height, $type = BX_RESIZE_IMAGE_PROPORTIONAL_ALT)
    {

        return array_change_key_case(\CFile::ResizeImageGet($fileId, array('width' => $width, 'height' => $height), $type, true), CASE_UPPER);

    }

    public static function fullName($arFields)
    {
        $fullName = '';

        if (!empty($arFields['LAST_NAME'])) {
            $fullName .= $arFields['LAST_NAME'] . ' ';
        }
        if (!empty($arFields['NAME'])) {
            $fullName .= $arFields['NAME'] . ' ';
        }

        if (!empty($arFields['SECOND_NAME'])) {
            $fullName .= $arFields['SECOND_NAME'] . ' ';
        }


        return trim($fullName);
    }

    public static function filesize_format($filesize)
    {
        $formats = array(" байт", " Кб", " Мб", " Гб", " Tб");
        $format = 0;
        while ($filesize > 1024 && count($formats) != ++$format) {
            $filesize = round($filesize / 1024, 1);
        }
        $formats[] = " Tб";
        return $filesize . ' ' . $formats[$format];
    }

    public static function getPageClass()
    {
        global $APPLICATION;

        $APPLICATION->AddBufferContent([self::class, 'showPageClass']);

    }

    public static function showPageClass()
    {
        global $APPLICATION;
        $class = '';
        if (!empty($APPLICATION->GetProperty('BODY_CLASS'))) {
            $class .= $APPLICATION->GetProperty('BODY_CLASS');
        } else {
            $class .= !\CSite::InDir(SITE_DIR . 'index.php') ? 'single_page' : '';
        }

        if ($APPLICATION->GetProperty('MENU') === 'N') {
            $class = ' hide_menu_page';
        }
        if ($APPLICATION->GetProperty('HIDETITLE') === 'Y') {
            $class .= ' hide_title_page';
        }
        if ($APPLICATION->GetProperty('FULLWIDTH') === 'Y') {
            $class .= ' wide_page';
        }
        return $class;
    }

    /**
     * Кастомизация вывода var_dump
     * @param mixed ...$vars
     */
    public static function varDump(...$vars):void
    {
        global $APPLICATION;

        $APPLICATION->RestartBuffer();

        echo '<pre>';
        foreach ($vars as $var) {
            //var_dump($var);
            print_r($var);
        }
        echo '</pre>';
        exit(1);
    }

    /**
     * Проверить является ли страница главной
     *
     * @access public
     *
     * @param mixed $uri Путь к странице, по умолчанию false - текущая страница
     *
     * @return bool Вернет "true" если текущий страница главная, в противном случае "false"
     *
     */
    public static function IsMainPage($uri=false)
    {
        if(false == $uri)
            $uri = $GLOBALS["APPLICATION"]->GetCurPage();

        if(!defined("ERROR_404") && ($uri == "/index.php" || in_array(str_replace(SITE_DIR, "", $uri), array("", "index.php", "/"))))
            return true;

        return false;
    }

    /**
     * Получить ID инфоблока по его коду
     * @param string $code
     * @param string $type
     * @return int
     * @throws \Bitrix\Main\LoaderException
     */
    public static function getIDIblockByCode(string $code, string $type):int
    {
        if (\Bitrix\Main\Loader::includeModule('iblock')) {
            $result =   \CIBlock::GetList(['SORT' => 'ASC'], ['TYPE' => $type, '=CODE' => $code]);
            $data   =   $result->GetNext();
            return (int)$data['ID'];
        }
        return 0;
    }

    /**
     * Get Element by ID
     * @param int $elementID
     * @return array
     * @throws \Bitrix\Main\LoaderException
     */
    public static function getIblockElementByID(int $iblock, int $elementID):array
    {
        if (empty($elementID) && !is_int($elementID)) {
            die('ID is not specified.');
        }

        $elements   =   [];
        $arOrder    =   [];
        $arSelect   =   ['ID', 'IBLOCK_ID', 'TIMESTAMP_X', 'DATE_CREATE', 'CREATED_BY', 'MODIFIED_BY', 'ACTIVE', 'SORT', 'IBLOCK_SECTION', 'CODE', 'NAME', 'PREVIEW_TEXT', 'DETAIL_PAGE_URL', 'PROPERTY_*'];
        $arFilter   =   ['IBLOCK_ID' => $iblock, 'ACTIVE' => 'Y', 'ID' => $elementID];

        $elementsList = \CIBlockElement::GetList($arOrder, $arFilter, false, false, $arSelect);

        if ($el = $elementsList->GetNextElement()) {
            $elements               =   $el->GetFields();
            $elements['PROPERTIES'] =   $el->GetProperties();
        }

        return $elements;
    }

    /**
     * Get elements by conditions
     * @param array $filter
     * @param array $sort
     * @param array $selectElement
     * @return array
     * @throws \Bitrix\Main\LoaderException
     */
    public static function getIBlockElementsByConditions(int $iblock, array $filter = [], array $sort = [], array $selectElement = []):array
    {
        $key        =   0;
        $elements   =   [];

        if (empty($selectElement)) {
            $arSelect = ['ID', 'IBLOCK_ID', 'ACTIVE', 'SORT', 'IBLOCK_SECTION', 'CODE', 'NAME', 'PREVIEW_TEXT', 'DETAIL_PAGE_URL', 'PROPERTY_*'];
        } else {
            $arSelect = $selectElement;
        }

        if (empty($sort)) {
            $arOrder = [];
        } else {
            $arOrder = $sort;
        }

        $arFilter = array_merge(['IBLOCK_ID' => $iblock], $filter);

        $elementsList = \CIBlockElement::GetList($arOrder, $arFilter, false, false, $arSelect);

        while ($el = $elementsList->GetNextElement()) {
            $elements[$key]                 =   $el->GetFields();
            $elements[$key]['PROPERTIES']   =   $el->GetProperties();
            $key++;
        }

        return $elements;
    }

    /**
     * Create new element
     * @param array $fields
     * @param array $properties
     * @return int|null
     * @throws \Bitrix\Main\LoaderException
     */
    public static function createIBlockElement(int $iblock, array $fields, array $properties)
        //public static function createElement(array $fields, array $properties)
    {
        $el = new \CIBlockElement();

        $fields = array_merge([
            'IBLOCK_ID' => $iblock,
        ], $fields);

        $resultID = $el->Add($fields);

        if ($resultID) {
            foreach ($properties as $key => $property) {
                \CIBlockElement::SetPropertyValuesEx($resultID, false, [$key => $property]);
            }

            return (int)$resultID;
        }

        AddMessage2Log([$el->LAST_ERROR, $fields], 'ERROR.CREATE_ELEMENT');
        return strip_tags($el->LAST_ERROR);
    }


}