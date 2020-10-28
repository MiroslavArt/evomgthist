<?php

namespace iTrack\Custom\Integration\Sendpulse;

use Bitrix\Crm\ContactTable;
use Bitrix\Crm\DealTable;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use iTrack\Custom\Services;
use iTrack\Custom\Application;
use iTrack\Custom\Constants;

class Books
{
    private static $instance;
    private $enabled = false;
    private $api;
    private $ufValues = [];
    private $ufValuesRef = [];
    private $arRemoteBooksRef = ['id2value' => [], 'value2id' => []];

    const BOOK_WEBINAR_NAME = 'WEB';
    const BOOK_GREENBASE_NAME = 'GREENBASE';
    const BOOK_FP_NAME = 'FP';
    const BOOK_BE_NAME = 'BE';
    const BOOK_03_NAME = '03';
    const BOOK_911_NAME = '911';

    const BOOK_SEGMENT_COLD = 0;
    const BOOK_SEGMENT_WARM = 1;
    const BOOK_SEGMENT_REJECT = 2;

    const CONTACT_UF_BOOKS_WEBINAR_NAME = 'Web';
    const CONTACT_UF_BOOKS_GREENBASE_NAME = 'GB';
    const CONTACT_UF_BOOKS_FP_NAME = 'FP';
    const CONTACT_UF_BOOKS_BE_NAME = 'BE';
    const CONTACT_UF_BOOKS_03_NAME = '03';
    const CONTACT_UF_BOOKS_911_NAME = '911';

    private function __construct() {
        $integrationEnabled = Option::get('itrack.custom','sendpulse_sync_books','');
        $this->enabled = $integrationEnabled === 'Y';
        if($this->enabled) {
            try {
                $this->api = new Services\Sendpulse();
            } catch(\Exception $e) {
                Application::log('Error create sendpulse service, '.$e, __CLASS__.'::'.__METHOD__.'.log');
                $this->enabled = false;
            }
            $this->fetchAdditionalData();
        }
    }
    
    private function __clone() {}
    private function __wakeup() {}

    public static function getInstance() {
        return
            self::$instance===null
                ? self::$instance = new static()
                : self::$instance;
    }

    public function isEnabled()
    {
        return $this->enabled;
    }

    public function handleDeal($dealId, $eventType, $arDealFields)
    {
        // TODO: rectoring
        Loader::includeModule('crm');
        if($arDeal = $this->getDeal($dealId)) {
            switch ($eventType) {
                case \iTrack\Custom\Integration\CrmManager::DEAL_AFTER_CREATE:
                    $this->handleDealCreate($arDeal, $arDealFields);
                    break;
                case \iTrack\Custom\Integration\CrmManager::DEAL_AFTER_UPDATE:
                    $this->handleDealUpdate($arDeal, $arDealFields);
                    break;
            }
        }
        //Application::log($dealId." ".$eventType, __CLASS__.'::'.__METHOD__.'.log');
    }

    /**
     * @param $id
     * @return array|false|null
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    protected function getDeal($id)
    {
        $dbDeal = DealTable::query()
            ->where('ID', $id)
            ->setSelect(['ID','STAGE_ID','CATEGORY_ID','CONTACT_ID',
                Constants::UF_DEAL_POST_CODE,
                Constants::UF_DEAL_STAFF_CNT_CODE,
                Constants::UF_DEAL_WEBINAR_COMPLETE_CNT_CODE,
                Constants::UF_DEAL_WEBINAR_NAME_CODE,
                Constants::UF_DEAL_WEBINAR_DATE_CODE
            ])
            ->exec();
        if($arDeal = $dbDeal->fetch()) {
            if(!empty($arDeal['CONTACT_ID'])) {
                $arDeal['CONTACT'] = $this->getContact($arDeal['CONTACT_ID']);
            }
            return $arDeal;
        } else {
            return null;
        }
    }

    protected function getContact($id)
    {
        $dbContact = ContactTable::query()
            ->where('ID',$id)
            ->setSelect([
                'ID',
                'EMAIL',
                'PHONE',
                'NAME',
                'LAST_NAME',
                'SECOND_NAME',
                'FULL_NAME',
                Constants::UF_CONTACT_POST_CODE,
                Constants::UF_CONTACT_STAFF_CNT_CODE,
                Constants::UF_CONTACT_WEBINAR_COMPLETE_CODE,
                Constants::UF_CONTACT_SENDPULSE_BOOKS_HISTORY_CODE
            ])
            ->exec();
        if($arContact = $dbContact->fetch()) {
            return $arContact;
        } else {
            return ['EMAIL' => ''];
        }
    }

    protected function updateRemoteWebinarValue($bookId, $arDeal)
    {
        if(!empty($arDeal[Constants::UF_DEAL_WEBINAR_NAME_CODE])) {
            $this->api->updateEmailVariables($bookId, $arDeal['CONTACT']['EMAIL'], ['webinar' => $arDeal[Constants::UF_DEAL_WEBINAR_NAME_CODE].' '.$arDeal[Constants::UF_DEAL_WEBINAR_DATE_CODE]]);
        }
    }

    protected function handleDealCreate($arDeal, $arEventDealFields)
    {
        $email = $arDeal['CONTACT']['EMAIL'];
        if(!empty($email)) {
            $arEmailData = $this->api->getEmailGlobalInfo($email);
            $arContactUpdateFields = [];
            switch ($arDeal['CATEGORY_ID']) {
                case Application::DEAL_CATEGORY_WEBINAR_ID:
                    $bNeedAdd = true;
                    $bNeedMove = false;
                    if(is_array($arEmailData)) {
                        foreach ($arEmailData as $obBookEmail) {
                            if (in_array($obBookEmail->book_id, [
                                $this->arRemoteBooksRef['value2id'][self::BOOK_GREENBASE_NAME],
                                $this->arRemoteBooksRef['value2id'][self::BOOK_FP_NAME],
                                $this->arRemoteBooksRef['value2id'][self::BOOK_BE_NAME],
                                $this->arRemoteBooksRef['value2id'][self::BOOK_03_NAME],
                                $this->arRemoteBooksRef['value2id'][self::BOOK_911_NAME],
                            ])) {
                                $bNeedAdd = false;
                                $this->updateRemoteWebinarValue($obBookEmail->book_id, $arDeal);
                            } elseif(in_array($obBookEmail->book_id, [$this->arRemoteBooksRef['value2id'][self::BOOK_WEBINAR_NAME]])
                                && !empty($arDeal['CONTACT'][Constants::UF_CONTACT_WEBINAR_COMPLETE_CODE])) {
                                foreach ($obBookEmail->variables as $obVariable) {
                                    if($obVariable->name == 'ss' && ($obVariable->value == self::BOOK_SEGMENT_WARM || $obVariable->value == self::BOOK_SEGMENT_REJECT)) {
                                        if($obVariable->value ==  self::BOOK_SEGMENT_WARM && count($arDeal['CONTACT'][Constants::UF_CONTACT_WEBINAR_COMPLETE_CODE]) >= 2) {
                                            $bNeedMove = true;
                                        }
                                        $bNeedAdd = false;
                                        $this->updateRemoteWebinarValue($obBookEmail->book_id, $arDeal);
                                        break;
                                    }
                                }
                            }
                        }
                    }
                    if($bNeedAdd || $bNeedMove) {
                        $arFields = $this->makeEmailFields($arDeal['CONTACT']);
                        $arFields['variables']['webinar'] = $arDeal[Constants::UF_DEAL_WEBINAR_NAME_CODE].' '.$arDeal[Constants::UF_DEAL_WEBINAR_DATE_CODE];
                        $arFields['variables']['uf_crm_stage_id'] = $arDeal['ID'];
                        if($bNeedMove) {
                            $arFields['variables']['ss'] = self::BOOK_SEGMENT_WARM;
                        } else {
                            if($arDeal['STAGE_ID'] == Constants::DEAL_CAT_WEBINAR_STAGE_LOSE) {
                                $arFields['variables']['ss'] = self::BOOK_SEGMENT_REJECT;
                            } else {
                                $arFields['variables']['ss'] = !empty($arDeal['CONTACT'][Constants::UF_CONTACT_WEBINAR_COMPLETE_CODE])
                                    ? self::BOOK_SEGMENT_WARM
                                    : self::BOOK_SEGMENT_COLD;
                            }
                        }

                        if($bNeedAdd) {
                            $result = $this->api->addEmails($this->arRemoteBooksRef['value2id'][self::BOOK_WEBINAR_NAME], [$arFields]);
                            $this->checkContactUpdate($arDeal['CONTACT'], $arContactUpdateFields, self::CONTACT_UF_BOOKS_WEBINAR_NAME);
                        }

                        if($bNeedMove) {
                            $this->api->removeEmails($this->arRemoteBooksRef['value2id'][self::BOOK_WEBINAR_NAME], [$email]);
                            $result = $this->api->addEmails($this->arRemoteBooksRef['value2id'][self::BOOK_GREENBASE_NAME], [$arFields]);
                            $this->checkContactUpdate($arDeal['CONTACT'], $arContactUpdateFields, self::CONTACT_UF_BOOKS_GREENBASE_NAME);
                        }
                    }
                    break;
                case Application::DEAL_CATEGORY_ONLINE_PRACTICE_ID:
                case Application::DEAL_CATEGORY_SEMINAR_PRACTICE_ID:
                    if(in_array($arDeal['STAGE_ID'], [
                        Constants::DEAL_CAT_SEMINAR_PRACTICE_STAGE_NEW,
                        Constants::DEAL_CAT_SEMINAR_PRACTICE_STAGE_SEND,
                        Constants::DEAL_CAT_ONLINE_PRACTICE_STAGE_NEW,
                        Constants::DEAL_CAT_ONLINE_PRACTICE_STAGE_SEND,
                    ])) {
                        $bNeedAdd = true;
                        $bToWarm = false;
                        if(is_array($arEmailData)) {
                            foreach ($arEmailData as $obBookEmail) {
                                if (in_array($obBookEmail->book_id, [
                                    $this->arRemoteBooksRef['value2id'][self::BOOK_FP_NAME],
                                    $this->arRemoteBooksRef['value2id'][self::BOOK_BE_NAME],
                                    $this->arRemoteBooksRef['value2id'][self::BOOK_03_NAME],
                                    $this->arRemoteBooksRef['value2id'][self::BOOK_911_NAME],
                                ])) {
                                    $bNeedAdd = false;
                                    break;
                                } elseif ($obBookEmail->book_id == $this->arRemoteBooksRef['value2id'][self::BOOK_WEBINAR_NAME]) {
                                    $this->api->removeEmails($obBookEmail->book_id, [$email]);
                                } elseif ($obBookEmail->book_id == $this->arRemoteBooksRef['value2id'][self::BOOK_GREENBASE_NAME]){
                                    $bNeedAdd = false;
                                    if(in_array($arDeal['STAGE_ID'], [Constants::DEAL_CAT_SEMINAR_PRACTICE_STAGE_SEND, Constants::DEAL_CAT_ONLINE_PRACTICE_STAGE_SEND])) {
                                        $bToWarm = true;
                                        foreach ($obBookEmail->variables as $obVariable) {
                                            if ($obVariable->name == 'ss' && ($obVariable->value == self::BOOK_SEGMENT_WARM || $obVariable->value == self::BOOK_SEGMENT_REJECT)) {
                                                $bToWarm = false;
                                                break;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        if($bNeedAdd) {
                            $arFields = $this->makeEmailFields($arDeal['CONTACT']);
                            $arFields['variables']['uf_crm_stage_id'] = $arDeal['ID'];
                            if($arDeal['STAGE_ID'] == Constants::DEAL_CAT_SEMINAR_PRACTICE_STAGE_NEW
                                || $arDeal['STAGE_ID'] == Constants::DEAL_CAT_ONLINE_PRACTICE_STAGE_NEW) {
                                $arFields['variables']['ss'] = self::BOOK_SEGMENT_COLD;
                            } elseif($arDeal['STAGE_ID'] == Constants::DEAL_CAT_SEMINAR_PRACTICE_STAGE_LOSE
                                || $arDeal['STAGE_ID'] == Constants::DEAL_CAT_ONLINE_PRACTICE_STAGE_LOSE) {
                                $arFields['variables']['ss'] = self::BOOK_SEGMENT_REJECT;
                            } else {
                                $arFields['variables']['ss'] = self::BOOK_SEGMENT_WARM;
                            }

                            $result = $this->api->addEmails($this->arRemoteBooksRef['value2id'][self::BOOK_GREENBASE_NAME], [$arFields]);

                            $this->checkContactUpdate($arDeal['CONTACT'], $arContactUpdateFields, self::CONTACT_UF_BOOKS_GREENBASE_NAME);
                        }

                        if($bToWarm) {
                            $this->api->updateEmailVariables($this->arRemoteBooksRef['value2id'][self::BOOK_GREENBASE_NAME], $email, ['ss' => self::BOOK_SEGMENT_WARM]);
                        }
                    }
                    break;
                case Application::DEAL_CATEGORY_BE4MSK_ID:
                    $bNeedAdd = true;
                    $bToWarm = false;
                    $bToNext = false;
                    if(is_array($arEmailData)) {
                        foreach ($arEmailData as $obBookEmail) {
                            if (in_array($obBookEmail->book_id, [
                                $this->arRemoteBooksRef['value2id'][self::BOOK_BE_NAME],
                                $this->arRemoteBooksRef['value2id'][self::BOOK_03_NAME],
                                $this->arRemoteBooksRef['value2id'][self::BOOK_911_NAME],
                            ])) {
                                $bNeedAdd = false;
                                break;
                            } elseif (in_array($obBookEmail->book_id, [
                                $this->arRemoteBooksRef['value2id'][self::BOOK_WEBINAR_NAME],
                                $this->arRemoteBooksRef['value2id'][self::BOOK_GREENBASE_NAME]
                            ])) {
                                $this->api->removeEmails($obBookEmail->book_id, [$email]);
                            } elseif ($obBookEmail->book_id == $this->arRemoteBooksRef['value2id'][self::BOOK_FP_NAME]){
                                $bNeedAdd = false;
                                if($arDeal['STAGE_ID'] == Constants::DEAL_CAT_BE4MSK_STAGE_VISITED) {
                                    $bToWarm = true;
                                    foreach ($obBookEmail->variables as $obVariable) {
                                        if ($obVariable->name == 'ss' && ($obVariable->value == self::BOOK_SEGMENT_WARM || $obVariable->value == self::BOOK_SEGMENT_REJECT)) {
                                            $bToWarm = false;
                                            break;
                                        }
                                    }
                                } elseif($arDeal['STAGE_ID'] == Constants::DEAL_CAT_BE4MSK_STAGE_PAYBE) {
                                    $bToNext = true;
                                }
                            }
                        }
                    }
                    $arFields = $this->makeEmailFields($arDeal['CONTACT']);
                    $arFields['variables']['uf_crm_stage_id'] = $arDeal['ID'];
                    if($bNeedAdd) {
                        $arFields['variables']['ss'] = $arDeal['STAGE_ID'] == Constants::DEAL_CAT_BE4MSK_STAGE_NEW
                            ? self::BOOK_SEGMENT_COLD
                            : self::BOOK_SEGMENT_WARM;
                        $result = $this->api->addEmails($this->arRemoteBooksRef['value2id'][self::BOOK_FP_NAME], [$arFields]);

                        $this->checkContactUpdate($arDeal['CONTACT'], $arContactUpdateFields, self::CONTACT_UF_BOOKS_FP_NAME);
                    }

                    if($bToNext) {
                        $result = $this->api->addEmails($this->arRemoteBooksRef['value2id'][self::BOOK_BE_NAME], [$arFields]);

                        $this->checkContactUpdate($arDeal['CONTACT'], $arContactUpdateFields, self::CONTACT_UF_BOOKS_BE_NAME);
                    }

                    if($bToWarm) {
                        $this->api->updateEmailVariables($this->arRemoteBooksRef['value2id'][self::BOOK_FP_NAME], $email, ['ss' => self::BOOK_SEGMENT_WARM]);
                    }

                    break;
            }

            if($bNeedAdd || $bNeedMove || $bToNext) {
                if (!empty($result)) {
                    if ($result instanceof \stdClass) {
                        if (!isset($result->result) || !$result->result) {
                            Application::log(
                                'Error add email to sendpulse. Deal: ' . $arDeal['ID'] . PHP_EOL
                                . 'fields: ' . print_r($arFields, true) . PHP_EOL
                                . 'result: ' . print_r($result, true),
                                __CLASS__ . '::' . __METHOD__ . '.log'
                            );
                        } else {
                            if(!empty($arContactUpdateFields)) {
                                $obContact = new \CCrmContact(false);
                                $updr = $obContact->Update($arDeal['CONTACT']['ID'], $arContactUpdateFields);
                                if(!$updr) {
                                    Application::log(
                                        'Error update contact history field. Deal: ' . $arDeal['ID'] . PHP_EOL
                                        . 'Contact: ' . print_r($arDeal['CONTACT'], true) . PHP_EOL
                                        . 'fields: ' .print_r($arContactUpdateFields, true) . PHP_EOL
                                        . 'error: ' . $obContact->LAST_ERROR,
                                        __CLASS__ . '::' . __METHOD__ . '.log'
                                    );
                                }
                            }
                        }
                    } else {
                        Application::log(
                            'Error add email to sendpulse. Deal: ' . $arDeal['ID'] . PHP_EOL
                            . 'fields: ' . print_r($arFields, true) . PHP_EOL
                            . 'result: ' . print_r($result, true),
                            __CLASS__ . '::' . __METHOD__ . '.log'
                        );
                    }
                }
            }
        }
    }

    protected function handleDealUpdate($arDeal, $arEventDealFields)
    {

        if(!empty($arEventDealFields)
            && !empty($arEventDealFields['C_OLD_FIELDS'])
            && ((!empty($arEventDealFields['C_OLD_FIELDS']['STAGE_ID'])
                    && $arEventDealFields['C_OLD_FIELDS']['STAGE_ID'] !== $arDeal['STAGE_ID'])
                || (!empty($arEventDealFields['C_OLD_FIELDS'][Constants::UF_DEAL_WEBINAR_COMPLETE_CNT_CODE])
                    && $arEventDealFields['C_OLD_FIELDS'][Constants::UF_DEAL_WEBINAR_COMPLETE_CNT_CODE] !== $arDeal[Constants::UF_DEAL_WEBINAR_COMPLETE_CNT_CODE]))
            && in_array($arDeal['CATEGORY_ID'], [
                Application::DEAL_CATEGORY_WEBINAR_ID,
                Application::DEAL_CATEGORY_ONLINE_PRACTICE_ID,
                Application::DEAL_CATEGORY_SEMINAR_PRACTICE_ID,
                Application::DEAL_CATEGORY_BE4MSK_ID
            ])
        ) {
            $email = $arDeal['CONTACT']['EMAIL'];
            if (!empty($email)) {
                $arEmailData = $this->api->getEmailGlobalInfo($email);
                $arContactUpdateFields = [];
                switch ($arDeal['CATEGORY_ID']) {
                    case Application::DEAL_CATEGORY_WEBINAR_ID:
                        switch($arDeal['STAGE_ID']) {
                            case Constants::DEAL_CAT_WEBINAR_STAGE_LOSE:
                                $bNeedMove = true;
                                if(is_array($arEmailData)) {
                                    foreach ($arEmailData as $obBookEmail) {
                                        if (in_array($obBookEmail->book_id, [
                                            $this->arRemoteBooksRef['value2id'][self::BOOK_GREENBASE_NAME],
                                            $this->arRemoteBooksRef['value2id'][self::BOOK_FP_NAME],
                                            $this->arRemoteBooksRef['value2id'][self::BOOK_BE_NAME],
                                            $this->arRemoteBooksRef['value2id'][self::BOOK_03_NAME],
                                            $this->arRemoteBooksRef['value2id'][self::BOOK_911_NAME],
                                        ])) {
                                            $bNeedMove = false;
                                            break;
                                        } elseif ($obBookEmail->book_id == $this->arRemoteBooksRef['value2id'][self::BOOK_WEBINAR_NAME]){
                                            foreach ($obBookEmail->variables as $obVariable) {
                                                if ($obVariable->name == 'ss' && $obVariable->value == self::BOOK_SEGMENT_REJECT) {
                                                    $bNeedMove = false;
                                                    break;
                                                }
                                            }
                                        }
                                    }
                                }
                                if($bNeedMove) {
                                    $this->api->updateEmailVariables($this->arRemoteBooksRef['value2id'][self::BOOK_WEBINAR_NAME], $email, ['ss' => self::BOOK_SEGMENT_REJECT]);
                                }
                                break;
                            case Constants::DEAL_CAT_WEBINAR_STAGE_WON:
                                $bNeedMove = true;
                                $bToWarm = false;
                                if(is_array($arEmailData)) {
                                    foreach ($arEmailData as $obBookEmail) {
                                        if (in_array($obBookEmail->book_id, [
                                            $this->arRemoteBooksRef['value2id'][self::BOOK_GREENBASE_NAME],
                                            $this->arRemoteBooksRef['value2id'][self::BOOK_FP_NAME],
                                            $this->arRemoteBooksRef['value2id'][self::BOOK_BE_NAME],
                                            $this->arRemoteBooksRef['value2id'][self::BOOK_03_NAME],
                                            $this->arRemoteBooksRef['value2id'][self::BOOK_911_NAME],
                                        ])) {
                                            $bNeedMove = false;
                                            break;
                                        } elseif($obBookEmail->book_id == $this->arRemoteBooksRef['value2id'][self::BOOK_WEBINAR_NAME]
                                            && !empty($arDeal['CONTACT'][Constants::UF_CONTACT_WEBINAR_COMPLETE_CODE])) {
                                            $bNeedMove = false;
                                            $bToWarm = true;
                                            foreach ($obBookEmail->variables as $obVariable) {
                                                if($obVariable->name == 'ss' && ($obVariable->value == self::BOOK_SEGMENT_WARM || $obVariable->value == self::BOOK_SEGMENT_REJECT)) {
                                                    if($obVariable->value ==  self::BOOK_SEGMENT_WARM && count($arDeal['CONTACT'][Constants::UF_CONTACT_WEBINAR_COMPLETE_CODE]) >= 2) {
                                                        $bNeedMove = true;
                                                    }
                                                    $bToWarm = false;
                                                    break;
                                                }
                                            }
                                        }
                                    }
                                }

                                if($bNeedMove) {
                                    $arFields = $this->makeEmailFields($arDeal['CONTACT']);
                                    $arFields['variables']['ss'] = self::BOOK_SEGMENT_WARM;

                                    $this->api->removeEmails($this->arRemoteBooksRef['value2id'][self::BOOK_WEBINAR_NAME], [$email]);
                                    $result = $this->api->addEmails($this->arRemoteBooksRef['value2id'][self::BOOK_GREENBASE_NAME], [$arFields]);

                                    $this->checkContactUpdate($arDeal['CONTACT'], $arContactUpdateFields, self::CONTACT_UF_BOOKS_GREENBASE_NAME);
                                }

                                if($bToWarm) {
                                    $this->api->updateEmailVariables($this->arRemoteBooksRef['value2id'][self::BOOK_WEBINAR_NAME], $email, ['ss' => self::BOOK_SEGMENT_WARM]);
                                }

                                break;
                        }
                        break;
                    case Application::DEAL_CATEGORY_ONLINE_PRACTICE_ID:
                    case Application::DEAL_CATEGORY_SEMINAR_PRACTICE_ID:
                        switch ($arDeal['STAGE_ID']) {
                            case Constants::DEAL_CAT_ONLINE_PRACTICE_STAGE_SEND:
                            case Constants::DEAL_CAT_SEMINAR_PRACTICE_STAGE_SEND:
                                if (is_array($arEmailData)) {
                                    $bToWarm = true;
                                    foreach ($arEmailData as $obBookEmail) {
                                        if (in_array($obBookEmail->book_id, [
                                            $this->arRemoteBooksRef['value2id'][self::BOOK_FP_NAME],
                                            $this->arRemoteBooksRef['value2id'][self::BOOK_BE_NAME],
                                            $this->arRemoteBooksRef['value2id'][self::BOOK_03_NAME],
                                            $this->arRemoteBooksRef['value2id'][self::BOOK_911_NAME],
                                        ])) {
                                            $bToWarm = false;
                                            break;
                                        } elseif ($obBookEmail->book_id == $this->arRemoteBooksRef['value2id'][self::BOOK_GREENBASE_NAME]) {
                                            foreach ($obBookEmail->variables as $obVariable) {
                                                if ($obVariable->name == 'ss' && ($obVariable->value == self::BOOK_SEGMENT_WARM || $obVariable->value == self::BOOK_SEGMENT_REJECT)) {
                                                    $bToWarm = false;
                                                    break;
                                                }
                                            }
                                        }
                                    }

                                    if($bToWarm) {
                                        $this->api->updateEmailVariables($this->arRemoteBooksRef['value2id'][self::BOOK_GREENBASE_NAME], $email, ['ss' => self::BOOK_SEGMENT_WARM]);
                                    }
                                }
                                break;
                            case Constants::DEAL_CAT_ONLINE_PRACTICE_STAGE_WON:
                            case Constants::DEAL_CAT_SEMINAR_PRACTICE_STAGE_WON:
                            case Constants::DEAL_CAT_SEMINAR_PRACTICE_STAGE_PAY:
                                if (is_array($arEmailData)) {
                                    $bNeedMove = true;
                                    foreach ($arEmailData as $obBookEmail) {
                                        if (in_array($obBookEmail->book_id, [
                                            $this->arRemoteBooksRef['value2id'][self::BOOK_FP_NAME],
                                            $this->arRemoteBooksRef['value2id'][self::BOOK_BE_NAME],
                                            $this->arRemoteBooksRef['value2id'][self::BOOK_03_NAME],
                                            $this->arRemoteBooksRef['value2id'][self::BOOK_911_NAME],
                                        ])) {
                                            $bNeedMove = false;
                                            break;
                                        }
                                    }

                                    if($bNeedMove) {
                                        $arFields = $this->makeEmailFields($arDeal['CONTACT']);
                                        $arFields['variables']['ss'] = Constants::DEAL_CAT_SEMINAR_PRACTICE_STAGE_PAY
                                            ? self::BOOK_SEGMENT_COLD
                                            : self::BOOK_SEGMENT_WARM;

                                        $this->api->removeEmails($this->arRemoteBooksRef['value2id'][self::BOOK_GREENBASE_NAME], [$email]);
                                        $result = $this->api->addEmails($this->arRemoteBooksRef['value2id'][self::BOOK_FP_NAME], [$arFields]);

                                        $this->checkContactUpdate($arDeal['CONTACT'], $arContactUpdateFields, self::CONTACT_UF_BOOKS_FP_NAME);
                                    }
                                }
                                break;
                            case Constants::DEAL_CAT_SEMINAR_PRACTICE_STAGE_LOSE:
                            case Constants::DEAL_CAT_ONLINE_PRACTICE_STAGE_LOSE:
                                $bToCold = true;
                                foreach ($arEmailData as $obBookEmail) {
                                    if (in_array($obBookEmail->book_id, [
                                        $this->arRemoteBooksRef['value2id'][self::BOOK_FP_NAME],
                                        $this->arRemoteBooksRef['value2id'][self::BOOK_BE_NAME],
                                        $this->arRemoteBooksRef['value2id'][self::BOOK_03_NAME],
                                        $this->arRemoteBooksRef['value2id'][self::BOOK_911_NAME],
                                    ])) {
                                        $bToCold = false;
                                        break;
                                    } elseif ($obBookEmail->book_id == $this->arRemoteBooksRef['value2id'][self::BOOK_GREENBASE_NAME]) {
                                        foreach ($obBookEmail->variables as $obVariable) {
                                            if ($obVariable->name == 'ss' && $obVariable->value == self::BOOK_SEGMENT_REJECT) {
                                                $bToCold = false;
                                                break;
                                            }
                                        }
                                    }
                                }

                                if($bToCold) {
                                    $this->api->updateEmailVariables($this->arRemoteBooksRef['value2id'][self::BOOK_GREENBASE_NAME], $email, ['ss' => self::BOOK_SEGMENT_REJECT]);
                                }

                                break;
                        }
                        break;
                    case Application::DEAL_CATEGORY_BE4MSK_ID:
                        switch($arDeal['STAGE_ID']) {
                            case Constants::DEAL_CAT_BE4MSK_STAGE_VISITED:
                                $bToWarm = true;
                                foreach ($arEmailData as $obBookEmail) {
                                    if (in_array($obBookEmail->book_id, [
                                        $this->arRemoteBooksRef['value2id'][self::BOOK_BE_NAME],
                                        $this->arRemoteBooksRef['value2id'][self::BOOK_03_NAME],
                                        $this->arRemoteBooksRef['value2id'][self::BOOK_911_NAME],
                                    ])) {
                                        $bToWarm = false;
                                        break;
                                    } elseif ($obBookEmail->book_id == $this->arRemoteBooksRef['value2id'][self::BOOK_FP_NAME]) {
                                        foreach ($obBookEmail->variables as $obVariable) {
                                            if ($obVariable->name == 'ss' && $obVariable->value == self::BOOK_SEGMENT_WARM) {
                                                $bToWarm = false;
                                                break;
                                            }
                                        }
                                    }
                                }

                                if($bToWarm) {
                                    $this->api->updateEmailVariables($this->arRemoteBooksRef['value2id'][self::BOOK_FP_NAME], $email, ['ss' => self::BOOK_SEGMENT_WARM]);
                                }

                                break;
                            case Constants::DEAL_CAT_BE4MSK_STAGE_PAYBE:
                                $bNeedMove = true;
                                foreach ($arEmailData as $obBookEmail) {
                                    if (in_array($obBookEmail->book_id, [
                                        $this->arRemoteBooksRef['value2id'][self::BOOK_BE_NAME],
                                        $this->arRemoteBooksRef['value2id'][self::BOOK_03_NAME],
                                        $this->arRemoteBooksRef['value2id'][self::BOOK_911_NAME],
                                    ])) {
                                        $bNeedMove = false;
                                        break;
                                    }
                                }

                                if($bNeedMove) {
                                    $arFields = $this->makeEmailFields($arDeal['CONTACT']);

                                    $this->api->removeEmails($this->arRemoteBooksRef['value2id'][self::BOOK_FP_NAME], [$email]);
                                    $result = $this->api->addEmails($this->arRemoteBooksRef['value2id'][self::BOOK_BE_NAME], [$arFields]);

                                    $this->checkContactUpdate($arDeal['CONTACT'], $arContactUpdateFields, self::CONTACT_UF_BOOKS_BE_NAME);
                                }

                                break;
                        }
                        break;
                }

                if($bNeedMove) {
                    if (!empty($result)) {
                        if ($result instanceof \stdClass) {
                            if (!isset($result->result) || !$result->result) {
                                Application::log(
                                    'Error add email to sendpulse. Deal: ' . $arDeal['ID'] . PHP_EOL
                                    . 'fields: ' . print_r($arFields, true) . PHP_EOL
                                    . 'result: ' . print_r($result, true),
                                    __CLASS__ . '::' . __METHOD__ . '.log'
                                );
                            } else {
                                if(!empty($arContactUpdateFields)) {
                                    $obContact = new \CCrmContact(false);
                                    $updr = $obContact->Update($arDeal['CONTACT']['ID'], $arContactUpdateFields);
                                    if(!$updr) {
                                        Application::log(
                                            'Error update contact history field. Deal: ' . $arDeal['ID'] . PHP_EOL
                                            . 'Contact: ' . print_r($arDeal['CONTACT'], true) . PHP_EOL
                                            . 'fields: ' .print_r($arContactUpdateFields, true) . PHP_EOL
                                            . 'error: ' . $obContact->LAST_ERROR,
                                            __CLASS__ . '::' . __METHOD__ . '.log'
                                        );
                                    }
                                }
                            }
                        } else {
                            Application::log(
                                'Error add email to sendpulse. Deal: ' . $arDeal['ID'] . PHP_EOL
                                . 'fields: ' . print_r($arFields, true) . PHP_EOL
                                . 'result: ' . print_r($result, true),
                                __CLASS__ . '::' . __METHOD__ . '.log'
                            );
                        }
                    }
                }
            }
        }
    }

    /**
     * заполним стандартные поля для контакта в sendpuls`е
     *
     * @param $arContact
     * @return array
     */
    protected function makeEmailFields($arContact)
    {
        return [
            'email' => $arContact['EMAIL'],
            'variables' => [
                'имя' => $arContact['FULL_NAME'],
                'phone' => $arContact['PHONE'],
                'Должность' => !empty($arContact[Constants::UF_CONTACT_POST_CODE])
                    ? $this->ufValuesRef[Constants::UF_CONTACT_POST_CODE][$arContact[Constants::UF_CONTACT_POST_CODE]]
                    : $this->ufValuesRef[Constants::UF_DEAL_POST_CODE][$arContact[Constants::UF_DEAL_POST_CODE]],
                'Кол-во сотрудников' => !empty($arContact[Constants::UF_CONTACT_STAFF_CNT_CODE])
                    ? $this->ufValuesRef[Constants::UF_CONTACT_STAFF_CNT_CODE][$arContact[Constants::UF_CONTACT_STAFF_CNT_CODE]]
                    : $this->ufValuesRef[Constants::UF_DEAL_STAFF_CNT_CODE][$arContact[Constants::UF_DEAL_STAFF_CNT_CODE]]
            ]
        ];
    }

    /**
     * проверим, надо ли добавить в контакт инфу о помещении в адресную книгу
     *
     * @param $arContact
     * @param $arFields
     * @param $book
     */
    protected function checkContactUpdate(&$arContact, &$arFields, $book)
    {
        if (empty($arContact[Constants::UF_CONTACT_SENDPULSE_BOOKS_HISTORY_CODE])
            || (is_array($arContact[Constants::UF_CONTACT_SENDPULSE_BOOKS_HISTORY_CODE])
                && !in_array($this->ufValues[Constants::UF_CONTACT_SENDPULSE_BOOKS_HISTORY_CODE][$book], $arContact[Constants::UF_CONTACT_SENDPULSE_BOOKS_HISTORY_CODE]))
        ) {
            $arContact[Constants::UF_CONTACT_SENDPULSE_BOOKS_HISTORY_CODE][] = $this->ufValues[Constants::UF_CONTACT_SENDPULSE_BOOKS_HISTORY_CODE][$book];
            $arFields[Constants::UF_CONTACT_SENDPULSE_BOOKS_HISTORY_CODE] = $arContact[Constants::UF_CONTACT_SENDPULSE_BOOKS_HISTORY_CODE];
        }
    }

    private function fetchAdditionalData()
    {
        // TODO: cache
        $arEnumFields = [
            Constants::UF_CONTACT_POST_CODE,
            Constants::UF_CONTACT_STAFF_CNT_CODE,
            Constants::UF_CONTACT_SENDPULSE_BOOKS_HISTORY_CODE,
            Constants::UF_DEAL_POST_CODE,
            Constants::UF_DEAL_STAFF_CNT_CODE
        ];
        foreach($arEnumFields as $fieldName) {
            $rsEnum = \CUserFieldEnum::GetList(array(), array("USER_FIELD_NAME" => $fieldName));
            while ($arEnum = $rsEnum->Fetch()) {
                $this->ufValues[$fieldName][$arEnum['VALUE']] = $arEnum['ID'];
                $this->ufValuesRef[$fieldName][$arEnum['ID']] = $arEnum['VALUE'];
            }
        }

        try {
            $arBooks = $this->api->listAddressBooks();
            if(!empty($arBooks)) {
                foreach($arBooks as $obBook) {
                    if($obBook instanceof \stdClass) {
                        $this->arRemoteBooksRef['id2value'][$obBook->id] = $obBook->name;
                        $this->arRemoteBooksRef['value2id'][$obBook->name] = $obBook->id;
                    }
                }
            }
        } catch(\Exception $e) {
            Application::log('Error get sendpulse books, '.$e, __CLASS__.'::'.__METHOD__.'.log');
            $this->enabled = false;
        }
    }
}