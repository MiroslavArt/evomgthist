<?php

if(file_exists($_SERVER['DOCUMENT_ROOT'].'/bitrix/vendor/autoload.php')) {
    require_once ($_SERVER['DOCUMENT_ROOT'].'/bitrix/vendor/autoload.php');
}

function funcOnAfterCrmTimelineCommentAdd($ID)
{
   $bExcept=is_numeric($ID);
	if($bExcept) {
		CModule::IncludeModule("crm");
		$checkData = \Bitrix\Crm\Timeline\Entity\TimelineTable::getList(
			array(
				'select' => [
					'ID'
					, 'TYPE_ID'
					, 'BINDING.ENTITY_TYPE_ID'
					, 'BINDING.ENTITY_ID'
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
		// Bitrix\Crm\Timeline\CommentEntry::registerBindings($ID, $fields); // $ID(TimelineTable.OWNER_ID)
		if (!empty($strIdDeal)) {
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

}
// $eventManager->addEventHandler("crm", "OnAfterCrmTimelineCommentAdd", funcOnAfterCrmTimelineCommentAdd);
AddEventHandler("crm", "OnAfterCrmTimelineCommentAdd", "funcOnAfterCrmTimelineCommentAdd");

AddEventHandler("crm", "OnAfterCrmDealAdd", "fOnAfterCrmDealAdd");

if(Bitrix\Main\Loader::includeModule('itrack.custom')) {
    \iTrack\Custom\Application::init();
}