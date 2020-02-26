<?php

namespace iTrack\Custom;

use Bitrix\Main\Config\Option;
use Bitrix\Main\EventManager;
use Bitrix\Main\Page\Asset;

class Application
{
    const DEAL_CATEGORY_EDUCATION_ID = 47;

    public static function init()
    {
        self::initJsHandlers();
        self::initEventHandlers();
    }

    protected static function initJsHandlers()
    {
        $urlTemplates = [
            'lead_detail' => ltrim(Option::get('crm', 'path_to_lead_details', '', SITE_ID), '/'),
            'deal_detail' => ltrim(Option::get('crm', 'path_to_deal_details', '', SITE_ID), '/'),
            'contact_detail' => ltrim(Option::get('crm', 'path_to_contact_details', '', SITE_ID), '/'),
            'company_detail' => ltrim(Option::get('crm', 'path_to_company_details', '', SITE_ID), '/'),
            'lead_kanban' => ltrim(Option::get('crm', 'path_to_lead_kanban', '', SITE_ID), '/'),
            'deal_kanban' => ltrim(Option::get('crm', 'path_to_deal_kanban', '', SITE_ID), '/'),
            'deal_kanban_category' => ltrim(Option::get('crm', 'path_to_deal_kanban', '', SITE_ID), '/').'category/#category_id#/',
            'contact_list' => ltrim(Option::get('crm', 'path_to_contact_list', '', SITE_ID), '/'),
            'company_list' => ltrim(Option::get('crm', 'path_to_company_list', '', SITE_ID), '/'),
        ];

        $page = \CComponentEngine::parseComponentPath('/', $urlTemplates, $arVars);
        $type = '';
        if($page !== false) {
            switch($page) {
                case 'lead_detail':
                case 'deal_detail':
                case 'contact_detail':
                case 'company_detail':
                    $type = 'detail';
                    break;
                case 'lead_kanban':
                case 'deal_kanban':
                case 'deal_kanban_category':
                    $type = 'kanban';
                    break;
                case 'contact_list':
                case 'company_list':
                    $type = 'list';
                    break;
            }
        }

        if(!empty($type)) {
            \CJSCore::init('crm_phone_timezone');
            $asset = Asset::getInstance();
            $asset->addString('<script>BX.ready(function () {BX.iTrack.Crm.PhoneTimezone.init("'.$type.'");});</script>');
        }

        if($page !== false && ($page === 'lead_detail' || $page === 'deal_detail')) {
            \CJSCore::init('itrack_crm_contact_widget_ext');
            $asset = Asset::getInstance();
            $asset->addString('<script>BX.ready(function () {BX.iTrack.Crm.ContactWidgetExt.init();});</script>');
        }
    }

    public static function initEventHandlers()
    {
        $eventManager = EventManager::getInstance();

        $eventManager->addEventHandler('tasks','OnTaskAdd', ['\iTrack\Custom\Handlers\Tasks','onTaskAdd']);
        $eventManager->addEventHandler('crm','OnBeforeCrmDealUpdate', ['\iTrack\Custom\Handlers\Crm','onBeforeCrmDealUpdate']);
        $eventManager->addEventHandler('crm','OnAfterCrmDealUpdate', ['\iTrack\Custom\Handlers\Crm','onAfterCrmDealUpdate']);
    }
}