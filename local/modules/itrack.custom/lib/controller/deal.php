<?php

namespace iTrack\Custom\Controller;

use Bitrix\Crm\ContactTable;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Error;
use Bitrix\Main\Loader;

class Deal extends Controller
{
    public function configureActions()
    {
        return [
            'getContactData' => []
        ];
    }

    public function getContactDataAction($id)
    {
        $result = [];

        try {
            $result = $this->getContactData($id);
        } catch(\Exception $e) {
            $this->addError(new Error($e->getMessage()));
        }

        return $result;
    }

    protected function getContactData($id)
    {
        Loader::includeModule('crm');
        $result = [];

        if(($id) > 0) {
            $dbContact = ContactTable::query()
                ->where('ID', $id)
                ->setSelect(['ID'])
                ->exec();
            if($dbContact->getSelectedRowsCount() > 0) {
                $userPermissions = \CCrmPerms::GetCurrentUserPermissions();
                $isEntityReadPermitted = \CCrmContact::CheckReadPermission($id, $userPermissions);
                if($isEntityReadPermitted) {
                    $result = $dbContact->fetch();
                    $result['CONTACT_DATA'] = \CCrmEntitySelectorHelper::PrepareEntityInfo(
                        \CCrmOwnerType::ContactName,
                        $id,
                        array(
                            'ENTITY_EDITOR_FORMAT' => true,
                            'IS_HIDDEN' => !$isEntityReadPermitted,
                            'USER_PERMISSIONS' => $userPermissions,
                            'REQUIRE_REQUISITE_DATA' => true,
                            'REQUIRE_MULTIFIELDS' => true,
                            'NORMALIZE_MULTIFIELDS' => true,
                            'REQUIRE_BINDINGS' => true,
                            'NAME_TEMPLATE' => \Bitrix\Crm\Format\PersonNameFormatter::getFormat(),
                        )
                    );

                    /*
                    $result['FM'] = array();
                    $fmResult = \CCrmFieldMulti::GetList(
                        array('ID' => 'asc'),
                        array(
                            'ENTITY_ID' => \CCrmOwnerType::ResolveName(\CCrmOwnerType::Contact),
                            'ELEMENT_ID' => $id
                        )
                    );

                    while ($fm = $fmResult->Fetch()) {
                        $fmTypeID = $fm['TYPE_ID'];
                        if (!isset($result['FM'][$fmTypeID])) {
                            $result['FM'][$fmTypeID] = array();
                        }

                        $result['FM'][$fmTypeID][] = array('ID' => $fm['ID'], 'VALUE_TYPE' => $fm['VALUE_TYPE'], 'VALUE' => $fm['VALUE']);
                    }*/
                }
            }
        } else {
            throw new ArgumentException('wrong contact id');
        }

        return $result;
    }
}