<?php

namespace iTrack\Custom\Handlers;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Page\Asset;
use iTrack\Custom\Constants;

class Main
{
    public static function onProlog()
    {
        global $USER;
        if($USER->IsAuthorized() && $USER->GetID() == Constants::ITRACK_USER_ID) {
            $asset = Asset::getInstance();
            $asset->addString('<script>BX.ready(function () {document.querySelector("body").classList.add("itrack-user");});</script>');
        }
    }

    public static function onEpilog()
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
            'tasks_list' => ltrim(Option::get('tasks', 'paths_task_user', '', SITE_ID), '/'),
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

        if($page !== false && ($page === 'deal_detail' || $page === 'contact_detail')) {
            \CJSCore::init(['jquery', 'itrack_crm_detail_editor_ext']);
            $asset->addString('<link href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css" rel="stylesheet"></link>');
            $asset->addString('<script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>');
            $asset->addString('<script>BX.ready(function () {BX.iTrack.Crm.DetailEditorExt.init();});</script>');
        }

        if($page !== false && $page === 'tasks_list') {
            // todo: add check if realy kanban page, see bitrix/components/bitrix/socialnetwork_user/templates/.default/user_tasks.php str 27
            \CJSCore::init('itrack_crm_tasks_kanban_ext');
        }

        if($page !== false) {
            \CJSCore::init('itrack_crm_styles_ext');
        }
    }
}