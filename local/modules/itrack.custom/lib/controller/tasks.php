<?php

namespace iTrack\Custom\Controller;

use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Loader;

class Tasks extends Controller
{
    public function configureActions()
    {
        return [
            'getAdditionalData' => []
        ];
    }

    public function getAdditionalDataAction($ids = [])
    {
        $result = ['items' => []];
        if(!empty($ids)) {
            $result['items'] = $this->getAdditionalData($ids);
        }
        return $result;
    }

    protected function getAdditionalData(array $ids)
    {
        $result = [];

        if(Loader::includeModule('tasks')) {
            $dbTasks = \CTasks::GetList([], ['=ID' => $ids], ['ID','TITLE', 'DESCRIPTION']);
            $obParser = new \CTextParser;
            while($arTask = $dbTasks->Fetch()) {
                $item = [
                    'id' => $arTask['ID']
                ];
                if(!empty($arTask['DESCRIPTION'])) {
                    if(strlen($arTask['DESCRIPTION']) > 150) {
                        $item['description'] = \TruncateText(HTMLToTxt($arTask['DESCRIPTION']), 150);
                    } else {
                        $item['description'] = $arTask['DESCRIPTION'];
                    }
                }
                $arUserFields = $GLOBALS["USER_FIELD_MANAGER"]->GetUserFields("TASKS_TASK", $arTask['ID'], LANGUAGE_ID);
                foreach($arUserFields as $arUserField) {
                    if($arUserField['FIELD_NAME'] === 'UF_CRM_TASK' && !empty($arUserField['VALUE'])) {
                        $html = '';
                        ob_start();
                        global $APPLICATION;
                        $APPLICATION->IncludeComponent(
                            "bitrix:system.field.view",
                            'crm_kanban',
                            array("arUserField" => $arUserField, 'PREFIX' => false),
                            null,
                            array("HIDE_ICONS"=>"Y")
                        );
                        $html = ob_get_clean();
                        $arParts = explode('<script', $html);
                        $item['crmLink'] = $arParts[0];
                    }
                }
                $result[] = $item;
            }
        }

        return $result;
    }
}