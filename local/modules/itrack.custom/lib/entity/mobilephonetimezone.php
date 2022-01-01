<?php

namespace iTrack\Custom\Entity;

use Bitrix\Main\ORM\Data\DataManager;

class MobilePhoneTimezoneTable extends DataManager
{
    /**
     * Returns DB table name for entity.
     *
     * @return string
     */
    public static function getTableName()
    {
        return 'itrack_mobilephone_timezone';
    }

    /**
     * Returns entity map definition.
     *
     * @return array
     */
    public static function getMap()
    {
        return array(
            'ID' => array(
                'data_type' => 'integer',
                'primary' => true,
                'autocomplete' => true,
                //'title' => Loc::getMessage('TIMEZONE_ENTITY_ID_FIELD'),
            ),
            'DEF_CODE' => array(
                'data_type' => 'integer',
                'required' => true,
                //'title' => Loc::getMessage('TIMEZONE_ENTITY_DEF_CODE_FIELD'),
            ),
            'FROM_CODE' => array(
                'data_type' => 'integer',
                'required' => true,
                //'title' => Loc::getMessage('TIMEZONE_ENTITY_FROM_CODE_FIELD'),
            ),
            'TO_CODE' => array(
                'data_type' => 'integer',
                'required' => true,
                //'title' => Loc::getMessage('TIMEZONE_ENTITY_TO_CODE_FIELD'),
            ),
            'BLOCK_SIZE' => array(
                'data_type' => 'integer',
                'required' => true,
                //'title' => Loc::getMessage('TIMEZONE_ENTITY_BLOCK_SIZE_FIELD'),
            ),
            'OPERATOR' => array(
                'data_type' => 'string',
                //'validation' => array(__CLASS__, 'validateOperator'),
                //'title' => Loc::getMessage('TIMEZONE_ENTITY_OPERATOR_FIELD'),
            ),
            'REGION_CODE' => array(
                'data_type' => 'string',
                //'validation' => array(__CLASS__, 'validateRegionCode'),
                //'title' => Loc::getMessage('TIMEZONE_ENTITY_REGION_CODE_FIELD'),
            ),
            'REGION_NAME' => array(
                'data_type' => 'string',
                //'validation' => array(__CLASS__, 'validateRegionName'),
                //'title' => Loc::getMessage('TIMEZONE_ENTITY_REGION_NAME_FIELD'),
            ),
            'TIMEZONE' => array(
                'data_type' => 'string',
                'required' => true,
                //'validation' => array(__CLASS__, 'validateTimezone'),
                //'title' => Loc::getMessage('TIMEZONE_ENTITY_TIMEZONE_FIELD'),
            ),
            'PHONE_TYPE' => array(
                'data_type' => 'string',
                'required' => true,
                //'validation' => array(__CLASS__, 'validatePhoneType'),
                //'title' => Loc::getMessage('TIMEZONE_ENTITY_PHONE_TYPE_FIELD'),
            ),
            'GMT' => array(
                'data_type' => 'string',
                'required' => true,
                //'validation' => array(__CLASS__, 'validateGmt'),
                //'title' => Loc::getMessage('TIMEZONE_ENTITY_GMT_FIELD'),
            ),
            'MNC' => array(
                'data_type' => 'string',
                'required' => true,
                //'validation' => array(__CLASS__, 'validateMnc'),
                //'title' => Loc::getMessage('TIMEZONE_ENTITY_MNC_FIELD'),
            ),
        );
    }
}