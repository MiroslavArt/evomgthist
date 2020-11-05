<?php

namespace iTrack\Custom;

use Bitrix\Main\Config\Option;
use Bitrix\Main\EventManager;
use Bitrix\Main\Page\Asset;

function funcOnAfterCrmTimelineCommentAdd($ID)
{

	$checkData = \Bitrix\Crm\Timeline\Entity\TimelineTable::getList(
		array(
			'select' => [
				'ID'
				, 'TYPE_ID'
				, 'BINDING.ENTITY_TYPE_ID'
				,'BINDING.ENTITY_ID'
				// ,'AUTHOR_ID'
				// ,'COMMENT'
				// ,'SETTINGS'
			]
		, 'order' => ['CREATED' => 'desc']
		, 'filter' => array(
			'=BINDING.ENTITY_TYPE_ID' => $ownerTypeID = \CCrmOwnerType::Deal
		, '=ID' => $ID
			// '=BINDING.ENTITY_ID' => $ownerID =999,
			// 'BINDINGS.ENTITY_ID' => 111388
		),
			'runtime' => array(
				new \Bitrix\Main\Entity\ReferenceField(
					'BINDING',
					'\Bitrix\Crm\Timeline\Entity\TimelineBindingTable',
					array("=ref.OWNER_ID" => "this.ID"),
					array("join_type" => "INNER")
				)
			),
			'limit' => 2
		)
	);
	$strIdDeal = $checkData->fetch()['CRM_TIMELINE_ENTITY_TIMELINE_BINDING_ENTITY_ID'];

	if(!empty($strIdDeal)) {
		$currentDbResult = \CCrmDeal::GetList(
			[],
			['=ID' => $strIdDeal, 'CHECK_PERMISSIONS' => 'N'],
			['CONTACT_ID'],
			false,
			);
		$strIdContact = $currentDbResult->Fetch()['CONTACT_ID'];

		$fields = array(
			'OWNER_ID' => $ID,
			'ENTITY_TYPE_ID' => \CCrmOwnerType::Contact,
			'ENTITY_ID' => $strIdContact,
		);

		$connection = \Bitrix\Main\Application::getConnection();
		$queries = $connection->getSqlHelper()->prepareMerge(
			'b_crm_timeline_bind',
			array('OWNER_ID', 'ENTITY_TYPE_ID', 'ENTITY_ID'),
			$fields,
			$fields
		);

		foreach ($queries as $query) {
			$connection->queryExecute($query);
		}
	}

}


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
        $eventManager->addEventHandler("crm", "OnAfterCrmTimelineCommentAdd", funcOnAfterCrmTimelineCommentAdd);


    }

    public static function log($msg, $file = 'main.log')
    {
        file_put_contents($_SERVER['DOCUMENT_ROOT'].'/local/logs/'.$file, date(DATE_COOKIE).': '.$msg."\n", FILE_APPEND);
    }
}