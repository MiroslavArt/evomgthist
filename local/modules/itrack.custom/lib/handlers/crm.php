<?php

namespace iTrack\Custom\Handlers;

use Bitrix\Crm\DealTable;
use Bitrix\Main\Loader;
use iTrack\Custom\Integration\CrmManager;

class Crm
{
    private static $oldAssignedId;
    private static $isFinalStage = false;
    private static $oldFields = [];
    private static $ufWEbinarViewedCountData = [];

    public static function onBeforeCrmDealUpdate(&$arFields)
    {
        $rsDeal = DealTable::query()
            ->setFilter(['=ID' => $arFields['ID']])
            ->setSelect(['ID','ASSIGNED_BY_ID','STAGE_ID'])
            ->exec();
        $arDeal = $rsDeal->fetch();
        self::$oldFields = $arDeal;
        if(\CCrmDeal::GetSemanticID($arDeal['STAGE_ID']) == \Bitrix\Crm\PhaseSemantics::FAILURE
            || \CCrmDeal::GetSemanticID($arDeal['STAGE_ID']) == \Bitrix\Crm\PhaseSemantics::SUCCESS) {
            self::$isFinalStage = true;
        }
        if(!empty($arDeal['ASSIGNED_BY_ID'])) {
            self::$oldAssignedId = (int)$arDeal['ASSIGNED_BY_ID'];
        }
    }

	public static function funcOnAfterCrmTimelineCommentAdd($ID)
	{

		$checkData = Bitrix\Crm\Timeline\Entity\TimelineTable::getList(
			array(
				'select' => [
					'ID'
					, 'TYPE_ID'
					, 'BINDING.ENTITY_TYPE_ID'
					// ,'BINDING.ENTITY_ID'
					// ,'AUTHOR_ID'
					// ,'COMMENT'
					// ,'SETTINGS'
				]
			, 'order' => ['CREATED' => 'desc']
			, 'filter' => array(
				'=BINDING.ENTITY_TYPE_ID' => $ownerTypeID = CCrmOwnerType::Deal
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
				'ENTITY_TYPE_ID' => CCrmOwnerType::Contact,
				'ENTITY_ID' => $strIdContact,
			);

			$connection = Bitrix\Main\Application::getConnection();
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

    
    public static function onAfterCrmDealUpdate(&$arFields)
    {
        if(!empty(self::$oldFields)) {
            $arFields['C_OLD_FIELDS'] = self::$oldFields;
            self::$oldFields = [];
        }

        if(!empty($arFields['ASSIGNED_BY_ID']) && !empty(self::$oldAssignedId) && (int)$arFields['ASSIGNED_BY_ID'] !== self::$oldAssignedId && !self::$isFinalStage) {
            Loader::includeModule('tasks');

            $rsTasks = \CTasks::GetList(
                [],
                ['UF_CRM_TASK' => 'D_'.$arFields['ID']],
                ['ID','RESPONSIBLE_ID','STATUS','AUDITORS'],
                ['USER_ID' => 1]
            );
            while($arTask = $rsTasks->Fetch()) {
                if((int)$arTask['RESPONSIBLE_ID'] === self::$oldAssignedId) {
                    $obTask = \CTaskItem::getInstance($arTask['ID'], 1);
                    try {
                        if($arTask['STATUS'] < \CTasks::STATE_SUPPOSEDLY_COMPLETED) {
                            $rs = $obTask->update(array("RESPONSIBLE_ID" => $arFields['ASSIGNED_BY_ID']));
                        } else {
                            $rs = $obTask->update(array("AUDITORS" => [$arFields['ASSIGNED_BY_ID']]));
                        }
                    } catch(\Exception $e) {
                        // log ?
                    }
                }
            }

            $task = new \Bitrix\Tasks\Item\Task(0, 1);
            $deadline = new \Bitrix\Main\Type\DateTime();
            $deadline->add('5 hours');
            $task['TITLE'] = 'Вас назначили ответственным за сделку. Свяжитесь с клиентом!';
            $task['DESCRIPTION'] = 'Вас назначили ответственным за сделку. Свяжитесь с клиентом!';
            $task['RESPONSIBLE_ID'] = $arFields['ASSIGNED_BY_ID'];
            $task['UF_CRM_TASK'] = ['D_'.$arFields['ID']];
            $task['DEADLINE'] = $deadline;
            $task->save();

            self::$oldAssignedId = null;
        }

        $dbDeal = DealTable::query()
            ->where('ID', $arFields['ID'])
            ->setSelect(['ID','UF_CRM_1582269904','UF_CRM_1591020493'])
            ->exec();
        if($arDeal = $dbDeal->fetch()) {

            // установка ПП кол-во просмотренных вебинаров

            self::getWebinarViewedCountUFValues(); // получим инфу по значениям списка
            $countCurrent = is_array($arDeal['UF_CRM_1582269904']) ? count($arDeal['UF_CRM_1582269904']) : 0;
            if(!empty($arDeal['UF_CRM_1591020493'])) {
                if((int)self::$ufWEbinarViewedCountData['VALUES_REF'][$arDeal['UF_CRM_1591020493']] !== $countCurrent) {
                    $newCount = $countCurrent;
                }
            } else {
                $newCount = $countCurrent;
            }

            if(isset($newCount)) {
                $enumId = null;
                if(empty(self::$ufWEbinarViewedCountData['VALUES'][$newCount])) {
                    // если такого значения в списке нет - добавим его
                    $obEnum = new \CUserFieldEnum;
                    $dbUf = \CUserTypeEntity::GetList([], ['FIELD_NAME' => 'UF_CRM_1591020493']);
                    if($arUf = $dbUf->Fetch()){
                        $obEnum->SetEnumValues($arUf['ID'], array(
                            "n0" => array(
                                "VALUE" => $newCount,
                            ),
                        ));
                        self::getWebinarViewedCountUFValues();
                    }
                }

                $enumId = self::$ufWEbinarViewedCountData['VALUES'][$newCount];
                if(!empty($enumId)) {
                    $obDeal = new \CCrmDeal(false);
                    $arUpdateFields = ['UF_CRM_1591020493' => $enumId];
                    $arFields['C_OLD_FIELDS']['UF_CRM_1591020493'] = $arDeal['UF_CRM_1582269904'];
                    $obDeal->Update($arDeal['ID'], $arUpdateFields);
                }
            }
        }

        CrmManager::handleDealEvent($arFields, CrmManager::DEAL_AFTER_UPDATE);
    }

    protected static function getWebinarViewedCountUFValues()
    {
        $rsEnum = \CUserFieldEnum::GetList(array(), array("USER_FIELD_NAME" => 'UF_CRM_1591020493'));
        $arValues = $arValuesRef = [];
        while ($arEnum = $rsEnum->Fetch()) {
            $arValues[(int)$arEnum['VALUE']] = $arEnum['ID'];
            $arValuesRef[$arEnum['ID']] = $arEnum['VALUE'];
        }
        self::$ufWEbinarViewedCountData = ['VALUES' => $arValues, 'VALUES_REF' => $arValuesRef];
    }

    public static function onAfterCrmDealAdd(&$arFields)
    {
        CrmManager::handleDealEvent($arFields, CrmManager::DEAL_AFTER_CREATE);
    }
}