<?php

namespace iTrack\Custom;

use Bitrix\Main\Config\Option;
use Bitrix\Main\EventManager;
use Bitrix\Main\Page\Asset;


class Application
{
    const DEAL_CATEGORY_EDUCATION_ID = 47;
    const DEAL_CATEGORY_WEBINAR_ID = 50;
    const DEAL_CATEGORY_ONLINE_PRACTICE_ID = 3;
    const DEAL_CATEGORY_SEMINAR_PRACTICE_ID = 1;
    const DEAL_CATEGORY_BE4MSK_ID = 30;
    const DEAL_CATEGORY_PROJECT_BE_ID = 56;

    public static function init()
    {
        self::initJsHandlers();
        self::initEventHandlers();
    }

    protected static function initJsHandlers()
    {

    }

    public static function initEventHandlers()
    {
        $eventManager = EventManager::getInstance();

        $eventManager->addEventHandler('tasks','OnTaskAdd', ['\iTrack\Custom\Handlers\Tasks','onTaskAdd']);
        $eventManager->addEventHandler('crm','OnBeforeCrmDealUpdate', ['\iTrack\Custom\Handlers\Crm','onBeforeCrmDealUpdate']);
        $eventManager->addEventHandler('crm','OnAfterCrmDealUpdate', ['\iTrack\Custom\Handlers\Crm','onAfterCrmDealUpdate']);
        $eventManager->addEventHandler('crm','OnAfterCrmDealAdd', ['\iTrack\Custom\Handlers\Crm','onAfterCrmDealAdd']);
        $eventManager->addEventHandler('main','OnProlog', ['\iTrack\Custom\Handlers\Main','onProlog']);
        $eventManager->addEventHandler('main','OnEpilog', ['\iTrack\Custom\Handlers\Main','onEpilog']);
        $eventManager->addEventHandler('im','OnBeforeMessageNotifyAdd', ['\iTrack\Custom\Handlers\Im','onBeforeMessageNotifyAdd']);
        // $eventManager->addEventHandler("crm", "OnAfterCrmTimelineCommentAdd", ['\iTrack\Custom\Handlers\Crm','funcOnAfterCrmTimelineCommentAdd']);
        


    }

    public static function log($msg, $file = 'main.log')
    {
        file_put_contents($_SERVER['DOCUMENT_ROOT'].'/local/logs/'.$file, date(DATE_COOKIE).': '.$msg."\n", FILE_APPEND);
    }
}