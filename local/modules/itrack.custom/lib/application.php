<?php

namespace iTrack\Custom;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Page\Asset;

class Application
{
    public static function init()
    {
        self::initPhoneTimezones();
    }

    protected static function initPhoneTimezones()
    {
        $urlTemplates = [
            'lead_detail' => ltrim(Option::get('crm', 'path_to_lead_details', '', SITE_ID), '/'),
            'deal_detail' => ltrim(Option::get('crm', 'path_to_deal_details', '', SITE_ID), '/'),
            'contact_detail' => ltrim(Option::get('crm', 'path_to_contact_details', '', SITE_ID), '/'),
            'company_detail' => ltrim(Option::get('crm', 'path_to_company_details', '', SITE_ID), '/'),
            'lead_kanban' => ltrim(Option::get('crm', 'path_to_lead_kanban', '', SITE_ID), '/'),
            'deal_kanban' => ltrim(Option::get('crm', 'path_to_deal_kanban', '', SITE_ID), '/'),
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
    }
}