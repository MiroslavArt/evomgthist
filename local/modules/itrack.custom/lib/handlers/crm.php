<?php

namespace iTrack\Custom\Handlers;

use Bitrix\Crm\DealTable;
use Bitrix\Main\Loader;

class Crm
{
    private static $oldAssignedId;

    public static function onBeforeCrmDealUpdate(&$arFields)
    {
        $rsDeal = DealTable::query()
            ->setFilter(['=ID' => $arFields['ID']])
            ->setSelect(['ID','ASSIGNED_BY_ID'])
            ->exec();
        $arDeal = $rsDeal->fetch();
        if(!empty($arDeal['ASSIGNED_BY_ID'])) {
            self::$oldAssignedId = (int)$arDeal['ASSIGNED_BY_ID'];
        }
    }

    public static function onAfterCrmDealUpdate(&$arFields)
    {
        if(!empty($arFields['ASSIGNED_BY_ID']) && !empty(self::$oldAssignedId) && (int)$arFields['ASSIGNED_BY_ID'] !== self::$oldAssignedId) {
            Loader::includeModule('tasks');

            $rsTasks = \CTasks::GetList(
                [],
                ['UF_CRM_TASK' => 'D_'.$arFields['ID']],
                ['ID','RESPONSIBLE_ID'],
                ['USER_ID' => 1]
            );
            while($arTask = $rsTasks->Fetch()) {
                if((int)$arTask['RESPONSIBLE_ID'] === self::$oldAssignedId) {
                    $obTask = \CTaskItem::getInstance($arTask['ID'], 1);
                    try {
                        $rs = $obTask->update(array("RESPONSIBLE_ID" => $arFields['ASSIGNED_BY_ID']));
                    } catch(\Exception $e) {
                        // log ?
                    }
                }
            }

            $task = new \Bitrix\Tasks\Item\Task(0, 1);
            $task['TITLE'] = 'Вас назначили ответственным за сделку. Свяжитесь с клиентом!';
            $task['DESCRIPTION'] = 'Вас назначили ответственным за сделку. Свяжитесь с клиентом!';
            $task['RESPONSIBLE_ID'] = $arFields['ASSIGNED_BY_ID'];
            $task['UF_CRM_TASK'] = 'D_'.$arFields['ID'];
            $task->save();
        }
    }
}