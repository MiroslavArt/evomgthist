<?php

namespace iTrack\Custom\Entity;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\TextField;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\Type;

class SendpulseBooksQueueTable extends DataManager
{
    /**
     * Returns DB table name for entity.
     *
     * @return string
     */
    public static function getTableName()
    {
        return 'itrack_sendpulse_books_queue';
    }

    /**
     * Returns entity map definition.
     *
     * @return array
     */
    public static function getMap()
    {
        return array(
            'ID' => new IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true,
            ]),
            'TYPE' => new IntegerField('TYPE', [
                'required' => true,
            ]),
            'STATUS' => new StringField('STATUS',[
                'required' => true,
            ]),
            'DEAL_ID' => new IntegerField('DEAL_ID', [
                'required' => true,
            ]),
            'FIELDS' => new TextField('FIELDS',[
                'required' => true,
            ]),
            'TIMESTAMP' => new DatetimeField('TIMESTAMP'),
            'EXEC_TIME' => new DatetimeField('EXEC_TIME')
        );
    }
}