<?php

namespace iTrack\Custom\Handlers;

use Bitrix\Main\Loader;
use iTrack\Custom\Application;

class Tasks
{
    public static function onTaskAdd($id, &$arFields)
    {
        /**
         * для автоматически созданных задач из сделки устанавливаем пользовательское поле "сессия", если сделка в
         * воронке обучения
         */
        if(!empty($arFields['UF_CRM_TASK']) && empty($arFields['UF_SESSION'])) {
            $linkedEntity = $arFields['UF_CRM_TASK'][0];
            if(strpos($linkedEntity, 'D_') !== false) {
                $dealID = (int)str_replace('D_','', $linkedEntity);
                if(Loader::includeModule('crm')) {
                    $dbDeal = \CCrmDeal::GetListEx(
                        [],
                        ['=ID' => $dealID],
                        false,
                        false,
                        ['ID', 'STAGE_ID', 'CATEGORY_ID']
                    );
                    if ($arDeal = $dbDeal->Fetch()) {
                        if ($arDeal['CATEGORY_ID'] == Application::DEAL_CATEGORY_EDUCATION_ID) {
                            global $USER;
                            $userId = $USER->GetID();
                            $oTaskItem = new \CTaskItem($id, $userId);
                            $res = $oTaskItem->Update(['UF_SESSION' => $arDeal['STAGE_ID']]);
                        }
                    }
                }
            }
        }
    }
}