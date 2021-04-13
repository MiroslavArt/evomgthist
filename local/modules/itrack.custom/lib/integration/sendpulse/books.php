<?php

namespace iTrack\Custom\Integration\Sendpulse;

use Bitrix\Crm\ContactTable;
use Bitrix\Crm\DealTable;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use iTrack\Custom\Services;
use iTrack\Custom\Application;
use iTrack\Custom\Constants;
use Bitrix\Main\Type;
use iTrack\Custom\Entity\SendpulseBooksQueueTable;

class Books
{
    private static $instance;
    private $enabled = false;
    private $api;
    private $ufValues = [];
    private $ufValuesRef = [];
    private $arRemoteBooksRef = ['id2value' => [], 'value2id' => []];
    private $arCategories = [];

    const BOOK_WEBINAR_NAME = 'WEB';
    const BOOK_ONLINECOURSE_NAME = 'ONLINE_COURSE';
    const BOOK_GREENBASE_NAME = 'GREENBASE';
    const BOOK_FP_NAME = 'FP';
    const BOOK_BE_NAME = 'BE';
    const BOOK_03_NAME = '03';
    const BOOK_911_NAME = '911';

    const BOOK_SEGMENT_COLD = 0;
    const BOOK_SEGMENT_WARM = 1;
    const BOOK_SEGMENT_REJECT = 2;

    const CONTACT_UF_BOOKS_WEBINAR_NAME = 'Web';
    const CONTACT_UF_BOOKS_ONLINECOURSE_NAME = 'OC';
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
            Loader::includeModule('crm');
            $this->arCategories = [
                Application::DEAL_CATEGORY_WEBINAR_ID,
                Application::DEAL_CATEGORY_ONLINE_COURSE_ID,
                Application::DEAL_CATEGORY_ONLINE_PRACTICE_ID,
                Application::DEAL_CATEGORY_SEMINAR_PRACTICE_ID,
                Application::DEAL_CATEGORY_BOOKS_TW_ID,
                Application::DEAL_CATEGORY_BOOKS_MOSCOW_ID,
                Application::DEAL_CATEGORY_FINEVOLUTION_ID,
                Application::DEAL_CATEGORY_PROJECT_BE_ID
            ];
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
        // TODO: refactoring
        if($arDeal = $this->getDeal($dealId)) {
            if($this->checkValid($arDeal, $eventType, $arDealFields)) {
                switch ($eventType) {
                    case \iTrack\Custom\Integration\CrmManager::DEAL_AFTER_CREATE:
                        $this->handleDealCreate($arDeal, $arDealFields);
                        break;
                    case \iTrack\Custom\Integration\CrmManager::DEAL_AFTER_UPDATE:
                        $this->addToQueue($dealId, $eventType, $arDealFields);
                        break;
                }
            }
        }
        //Application::log($dealId." ".$eventType, __CLASS__.'::'.__METHOD__.'.log');
    }

    public function checkQueue()
    {
        $dbQueue = SendpulseBooksQueueTable::query()
            ->setFilter(['STATUS' => 'N'])
            ->setOrder(['ID' => 'ASC'])
            ->setSelect(['ID','TYPE','DEAL_ID','FIELDS'])
            ->setLimit(50)
            ->exec();
        while($arQueue = $dbQueue->fetch()) {
            if($arDeal = $this->getDeal($arQueue['DEAL_ID'])) {
                $this->handleDealUpdate($arDeal, \Bitrix\Main\Web\Json::decode($arQueue['FIELDS']));
                SendpulseBooksQueueTable::update($arQueue['ID'], ['STATUS' => 'Y','EXEC_TIME' => new Type\DateTime()]);
            }
        }
    }

    public function reIndex()
    {
        $dbDeals = DealTable::query()
            ->whereIn('CATEGORY_ID', $this->arCategories)
            ->setSelect(['ID','STAGE_ID','CATEGORY_ID','CONTACT_ID',
                Constants::UF_DEAL_POST_CODE,
                Constants::UF_DEAL_STAFF_CNT_CODE,
                Constants::UF_DEAL_WEBINAR_COMPLETE_CNT_CODE,
                Constants::UF_DEAL_WEBINAR_NAME_CODE,
                Constants::UF_DEAL_WEBINAR_DATE_CODE,
                'CONTACT.EMAIL'
            ])
            ->exec();
        $arData = [];
        $contactIds = [];
        while($arDeal = $dbDeals->fetch()) {
            $contactIds[] = $arDeal['CONTACT_ID'];
            if(!empty($arDeal['CRM_DEAL_CONTACT_EMAIL'])) {
                $arData[strtolower($arDeal['CRM_DEAL_CONTACT_EMAIL'])][] = $arDeal;
            }
        }

        $dbContacts = ContactTable::query()
            ->whereIn('ID',$contactIds)
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
        $arContacts = [];
        while($arContact = $dbContacts->fetch()) {
            if(!empty($arContact['EMAIL'])) {
                $arContacts[strtolower($arContact['EMAIL'])][] = $arContact;
            }
        }

        $arEmailsData = [];
        //$arEmailsData = \Bitrix\Main\Web\Json::decode(file_get_contents($_SERVER['DOCUMENT_ROOT'].'/dev-emails.json'));
        $arBooks = [
            $this->arRemoteBooksRef['value2id'][self::BOOK_WEBINAR_NAME],
            $this->arRemoteBooksRef['value2id'][self::BOOK_ONLINECOURSE_NAME],
            $this->arRemoteBooksRef['value2id'][self::BOOK_GREENBASE_NAME],
            $this->arRemoteBooksRef['value2id'][self::BOOK_FP_NAME],
            $this->arRemoteBooksRef['value2id'][self::BOOK_BE_NAME]
        ];
        foreach($arBooks as $bookId) {
            $start = 0;
            while (true) {
                $arEmails = $this->api->getEmailsFromBook($bookId, 100, $start);
                if (!empty($arEmails)) {
                    foreach ($arEmails as $obEmail) {
                        if ($obEmail instanceof \stdClass) {
                            $arEmailsData[$obEmail->email][$bookId] = $obEmail;
                        }
                    }
                    if (count($arEmails) < 100) {
                        break;
                    } else {
                        $start += 100;
                    }
                }
            }
        }
        file_put_contents($_SERVER['DOCUMENT_ROOT'].'/dev-emails.json', \Bitrix\Main\Web\Json::encode($arEmailsData));
        //foreach ($arEmailsData as $email=>$bookEmails) {
        foreach ($arData as $email=>$arDeals) {
            print $email.PHP_EOL;
            /*print_r($arContacts[$email]);
            print PHP_EOL;
            print_r($arDeals);
            print PHP_EOL;*/
            list($correctBookId, $correctSegment) = $this->getCorrectBook($arContacts[$email], $arDeals);
            $bookEmails = $arEmailsData[$email];
            if((int)$correctBookId > 0) {
                if (!empty($bookEmails)) {
                    $bookFound = false;
                    foreach ($bookEmails as $bookId => $obEmail) {
                        if ($correctBookId !== $bookId) {
                            $this->api->removeEmails($bookId, [$email]);
                            print 'delete ' . $this->arRemoteBooksRef['id2value'][$bookId] . PHP_EOL;
                            unset($bookEmails[$bookId]);
                        } else {
                            $bookFound = true;
                            foreach ($obEmail->variables as $vname=>$vval) {
                                if ($vname === 'ss' && $vval !== $correctSegment) {
                                //if (!empty($obVariable->ss) && $obVariable->ss !== $correctSegment) {
                                    $this->api->updateEmailVariables($bookId, $email, ['ss' => $correctSegment]);
                                    break;
                                }
                            }
                        }
                    }
                    if (!$bookFound) {
                        $arFields = $this->makeEmailFields($arContacts[$email][0]);
                        $arFields['variables']['ss'] = $correctSegment;
                        $result = $this->api->addEmails($correctBookId, [$arFields]);
                        print "correct book added" . PHP_EOL;
                    }
                    print 'correct: ' . $this->arRemoteBooksRef['id2value'][$correctBookId] . PHP_EOL;
                    print 'correct segment: ' . $correctSegment . PHP_EOL;
                } else {
                    $arFields = $this->makeEmailFields($arContacts[$email][0]);
                    $arFields['variables']['ss'] = $correctSegment;
                    $result = $this->api->addEmails($correctBookId, [$arFields]);
                    print 'not found' . PHP_EOL;
                    print 'correct: ' . $this->arRemoteBooksRef['id2value'][$correctBookId] . PHP_EOL;
                    print 'correct segment: ' . $correctSegment . PHP_EOL;
                }
            }
        }
    }

    protected function getCorrectBook($arContacts, $arDeals)
    {
        $bookId = null;
        $segment = null;

        $category = null;

        foreach($arDeals as $arDeal) {
            if($arDeal['CATEGORY_ID'] == Application::DEAL_CATEGORY_WEBINAR_ID) {
                if($category == Application::DEAL_CATEGORY_PROJECT_BE_ID
                    || $category == Application::DEAL_CATEGORY_FINEVOLUTION_ID
                    || $bookId == $this->arRemoteBooksRef['value2id'][self::BOOK_GREENBASE_NAME]
                    || $bookId == $this->arRemoteBooksRef['value2id'][self::BOOK_BE_NAME]
                    || $bookId == $this->arRemoteBooksRef['value2id'][self::BOOK_FP_NAME] ) {
                    continue;
                }

                $completeWebinars = 0;
                if(count($arContacts) === 1) {
                    if(!empty($arContacts[0][Constants::UF_CONTACT_WEBINAR_COMPLETE_CODE])) {
                        $completeWebinars = count($arContacts[0][Constants::UF_CONTACT_WEBINAR_COMPLETE_CODE]);
                    }
                } else {
                    $arWebinars = [];
                    foreach ($arContacts as $arContact) {
                        if(!empty($arContact[Constants::UF_CONTACT_WEBINAR_COMPLETE_CODE])) {
                            $arWebinars = array_merge($arWebinars, $arContact[Constants::UF_CONTACT_WEBINAR_COMPLETE_CODE]);
                        }
                    }
                    $arWebinars = array_unique($arWebinars);
                    $completeWebinars = count($arWebinars);
                }

                if(($category == Application::DEAL_CATEGORY_ONLINE_COURSE_ID || $bookId == $this->arRemoteBooksRef['value2id'][self::BOOK_ONLINECOURSE_NAME]) && $completeWebinars < 2) {
                    continue;
                } else {
                    $category = Application::DEAL_CATEGORY_WEBINAR_ID;
                    $bookId = $this->arRemoteBooksRef['value2id'][self::BOOK_WEBINAR_NAME];

                    if ($completeWebinars > 0) {
                        $segment = self::BOOK_SEGMENT_WARM;
                        if ($completeWebinars >= 2) {
                            $bookId = $this->arRemoteBooksRef['value2id'][self::BOOK_GREENBASE_NAME];
                            $segment = self::BOOK_SEGMENT_WARM;
                        }
                    } else {
                        $segment = self::BOOK_SEGMENT_COLD;
                    }
                }
                /*if($arDeal['STAGE_ID'] == Constants::DEAL_CAT_WEBINAR_STAGE_LOSE && $segment === null) {
                    $segment = self::BOOK_SEGMENT_REJECT;
                }*/
            }
            if($arDeal['CATEGORY_ID'] == Application::DEAL_CATEGORY_ONLINE_COURSE_ID) {
                if($category == Application::DEAL_CATEGORY_PROJECT_BE_ID
                    || $category == Application::DEAL_CATEGORY_FINEVOLUTION_ID
                    || $bookId == $this->arRemoteBooksRef['value2id'][self::BOOK_GREENBASE_NAME]
                    || $bookId == $this->arRemoteBooksRef['value2id'][self::BOOK_BE_NAME]
                    || $bookId == $this->arRemoteBooksRef['value2id'][self::BOOK_FP_NAME]) {
                    continue;
                }

                $category = Application::DEAL_CATEGORY_ONLINE_COURSE_ID;
                $bookId = $this->arRemoteBooksRef['value2id'][self::BOOK_ONLINECOURSE_NAME];
                if(in_array($arDeal['STAGE_ID'], ['C54:PREPARATION','C54:4','C54:WON'])) {
                    $segment = self::BOOK_SEGMENT_WARM;
                } else {
                    $segment = self::BOOK_SEGMENT_COLD;
                }
            }
            if($arDeal['CATEGORY_ID'] == Application::DEAL_CATEGORY_BOOKS_TW_ID || $arDeal['CATEGORY_ID'] == Application::DEAL_CATEGORY_BOOKS_MOSCOW_ID) {
                if($category == Application::DEAL_CATEGORY_PROJECT_BE_ID
                    || $category == Application::DEAL_CATEGORY_FINEVOLUTION_ID
                    || $bookId == $this->arRemoteBooksRef['value2id'][self::BOOK_GREENBASE_NAME]
                    || $bookId == $this->arRemoteBooksRef['value2id'][self::BOOK_BE_NAME]
                    || $bookId == $this->arRemoteBooksRef['value2id'][self::BOOK_FP_NAME]) {
                    continue;
                }

                if(in_array($arDeal['STAGE_ID'], ['C5:WON','C5:8','C41:WON'])) {
                    $category = $arDeal['CATEGORY_ID'];
                    $bookId = $this->arRemoteBooksRef['value2id'][self::BOOK_GREENBASE_NAME];
                    $segment = self::BOOK_SEGMENT_COLD;
                }
            }
            if($arDeal['CATEGORY_ID'] == Application::DEAL_CATEGORY_ONLINE_PRACTICE_ID
                || $arDeal['CATEGORY_ID'] == Application::DEAL_CATEGORY_SEMINAR_PRACTICE_ID) {
                if($category == Application::DEAL_CATEGORY_PROJECT_BE_ID
                    || $category == Application::DEAL_CATEGORY_FINEVOLUTION_ID
                    || $bookId == $this->arRemoteBooksRef['value2id'][self::BOOK_FP_NAME]) {
                    continue;
                }
                $category = $arDeal['CATEGORY_ID'];
                $bookId = $this->arRemoteBooksRef['value2id'][self::BOOK_GREENBASE_NAME];
                if($arDeal['STAGE_ID'] == Constants::DEAL_CAT_SEMINAR_PRACTICE_STAGE_WON) {
                    $bookId = $this->arRemoteBooksRef['value2id'][self::BOOK_FP_NAME];
                    $segment = self::BOOK_SEGMENT_COLD;
                } else {
                    $completeWebinars = 0;
                    if(count($arContacts) == 1) {
                        if(!empty($arContacts[0][Constants::UF_CONTACT_WEBINAR_COMPLETE_CODE])) {
                            $completeWebinars = count($arContacts[0][Constants::UF_CONTACT_WEBINAR_COMPLETE_CODE]);
                        }
                    } else {
                        $arWebinars = [];
                        foreach ($arContacts as $arContact) {
                            if(!empty($arContact[Constants::UF_CONTACT_WEBINAR_COMPLETE_CODE])) {
                                $arWebinars = array_merge($arWebinars, $arContact[Constants::UF_CONTACT_WEBINAR_COMPLETE_CODE]);
                            }
                        }
                        $arWebinars = array_unique($arWebinars);
                        $completeWebinars = count($arWebinars);
                    }
                    if($completeWebinars >= 2) {
                        $segment = self::BOOK_SEGMENT_WARM;
                    } else {
                        $segment = self::BOOK_SEGMENT_COLD;
                    }
                }
            }
            if($arDeal['CATEGORY_ID'] == Application::DEAL_CATEGORY_FINEVOLUTION_ID) {
                if($category == Application::DEAL_CATEGORY_PROJECT_BE_ID || $bookId == $this->arRemoteBooksRef['value2id'][self::BOOK_BE_NAME]) {
                    continue;
                }

                $category = $arDeal['CATEGORY_ID'];
                $bookId = $this->arRemoteBooksRef['value2id'][self::BOOK_FP_NAME];
                if($arDeal['STAGE_ID'] == 'C58:WON') {
                    $segment = self::BOOK_SEGMENT_WARM;
                } else {
                    $segment = self::BOOK_SEGMENT_COLD;
                }
            }
            if($arDeal['CATEGORY_ID'] == Application::DEAL_CATEGORY_PROJECT_BE_ID) {
                if($bookId == $this->arRemoteBooksRef['value2id'][self::BOOK_BE_NAME] || $arDeal['STAGE_ID'] === 'C56:NEW') {
                    continue;
                }
                $category = $arDeal['CATEGORY_ID'];
                $bookId = $this->arRemoteBooksRef['value2id'][self::BOOK_FP_NAME];
                if($arDeal['STAGE_ID'] === 'C56:WON') {
                    $bookId = $this->arRemoteBooksRef['value2id'][self::BOOK_BE_NAME];
                    $segment = '';
                } else {
                    $segment = self::BOOK_SEGMENT_WARM;
                }
            }
        }

        return [$bookId, $segment];
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

    /**
     * @param $arDeal - deal fields
     * @param $eventType - type of handled event
     * @param $arEventDealFields - fields from event
     * @return bool
     */
    protected function checkValid($arDeal, $eventType, $arEventDealFields)
    {
        $bValid = in_array($arDeal['CATEGORY_ID'], $this->arCategories);

        if($eventType === \iTrack\Custom\Integration\CrmManager::DEAL_AFTER_UPDATE && $bValid) {
            $bValid = !empty($arEventDealFields)
                && !empty($arEventDealFields['C_OLD_FIELDS'])
                && ((!empty($arEventDealFields['C_OLD_FIELDS']['STAGE_ID'])
                        && $arEventDealFields['C_OLD_FIELDS']['STAGE_ID'] !== $arDeal['STAGE_ID'])
                    || (!empty($arEventDealFields['C_OLD_FIELDS'][Constants::UF_DEAL_WEBINAR_COMPLETE_CNT_CODE])
                        && $arEventDealFields['C_OLD_FIELDS'][Constants::UF_DEAL_WEBINAR_COMPLETE_CNT_CODE] !== $arDeal[Constants::UF_DEAL_WEBINAR_COMPLETE_CNT_CODE]));
        }

        return $bValid;
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

    protected function addToQueue($dealId, $eventType, $arDealFields)
    {
        $dbQueue = SendpulseBooksQueueTable::query()
            ->setFilter(['TYPE' => $eventType,'STATUS' => 'N','=DEAL_ID' => $dealId])
            ->setSelect(['ID'])
            ->exec();
        if($dbQueue->getSelectedRowsCount() > 0) {
            $arQueue = $dbQueue->fetch();
            SendpulseBooksQueueTable::update($arQueue['ID'], ['FIELDS' => \Bitrix\Main\Web\Json::encode($arDealFields), 'TIMESTAMP' => new Type\DateTime()]);
        } else {
            SendpulseBooksQueueTable::add([
                'TYPE' => $eventType,
                'STATUS' => 'N',
                'DEAL_ID' => $dealId,
                'FIELDS' => \Bitrix\Main\Web\Json::encode($arDealFields),
                'TIMESTAMP' => new Type\DateTime()
            ]);
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
        $this->processDeal($arDeal);
    }

    protected function handleDealUpdate($arDeal, $arEventDealFields)
    {
        $this->processDeal($arDeal);
    }

    protected function processDeal($arDeal)
    {
        $email = $arDeal['CONTACT']['EMAIL'];
        if (!empty($email)) {

            $arContactUpdateFields = [];

            list($arContacts, $arDeals) = $this->getCrmInfoByEmail($email);
            list($correctBookId, $correctSegment) = $this->getCorrectBook($arContacts, $arDeals);

            $this->sync($email, $correctBookId, $correctSegment, $arDeal);
            $this->checkContactUpdate($arDeal['CONTACT'], $arContactUpdateFields, $correctBookId);

            if (!empty($arContactUpdateFields)) {
                $obContact = new \CCrmContact(false);
                $updr = $obContact->Update($arDeal['CONTACT']['ID'], $arContactUpdateFields);
                if (!$updr) {
                    Application::log(
                        'Error update contact history field. Deal: ' . $arDeal['ID'] . PHP_EOL
                        . 'Contact: ' . print_r($arDeal['CONTACT'], true) . PHP_EOL
                        . 'fields: ' . print_r($arContactUpdateFields, true) . PHP_EOL
                        . 'error: ' . $obContact->LAST_ERROR,
                        __CLASS__ . '::' . __METHOD__ . '.log'
                    );
                }
            }
        }
    }

    /*protected function handleDealCreate($arDeal, $arEventDealFields)
    {
        $email = $arDeal['CONTACT']['EMAIL'];
        if(!empty($email)) {
            $arEmailData = $this->api->getEmailGlobalInfo($email);
            $arContactUpdateFields = [];

            switch ($arDeal['CATEGORY_ID']) {
                case Application::DEAL_CATEGORY_WEBINAR_ID:
                    $bNeedAdd = true;
                    $bNeedMove = false;
                    $bAlreadyMove = false;
                    if(is_array($arEmailData)) {
                        foreach ($arEmailData as $obBookEmail) {
                            if (in_array($obBookEmail->book_id, [
                                $this->arRemoteBooksRef['value2id'][self::BOOK_FP_NAME],
                                $this->arRemoteBooksRef['value2id'][self::BOOK_BE_NAME],
                                $this->arRemoteBooksRef['value2id'][self::BOOK_03_NAME],
                                $this->arRemoteBooksRef['value2id'][self::BOOK_911_NAME],
                            ])) {
                                $bAlreadyMove = true;
                                $bNeedAdd = false;
                                $this->updateRemoteWebinarValue($obBookEmail->book_id, $arDeal);
                            } elseif($obBookEmail->book_id == $this->arRemoteBooksRef['value2id'][self::BOOK_GREENBASE_NAME]) {
                                $bNeedAdd = false;
                                $this->updateRemoteWebinarValue($obBookEmail->book_id, $arDeal);
                                foreach ($obBookEmail->variables as $obVariable) {
                                    if ($obVariable->name === 'ss' 
                                        && $obVariable->value == self::BOOK_SEGMENT_COLD 
                                        && !empty($arDeal['CONTACT'][Constants::UF_CONTACT_WEBINAR_COMPLETE_CODE])
                                        && count($arDeal['CONTACT'][Constants::UF_CONTACT_WEBINAR_COMPLETE_CODE]) >= 2) {
                                        $this->api->updateEmailVariables($this->arRemoteBooksRef['value2id'][self::BOOK_GREENBASE_NAME], $email, ['ss' => self::BOOK_SEGMENT_WARM]);
                                        break;
                                    }
                                }
                            } elseif($obBookEmail->book_id == $this->arRemoteBooksRef['value2id'][self::BOOK_WEBINAR_NAME]
                                && !empty($arDeal['CONTACT'][Constants::UF_CONTACT_WEBINAR_COMPLETE_CODE])) {
                                $bNeedAdd = false;
                                if(count($arDeal['CONTACT'][Constants::UF_CONTACT_WEBINAR_COMPLETE_CODE]) >= 2) {
                                    $bNeedMove = true;
                                } else {
                                    $this->api->updateEmailVariables($this->arRemoteBooksRef['value2id'][self::BOOK_WEBINAR_NAME], $email, ['ss' => self::BOOK_SEGMENT_WARM]);
                                }
                                $this->updateRemoteWebinarValue($obBookEmail->book_id, $arDeal);
                            } elseif($obBookEmail->book_id == $this->arRemoteBooksRef['value2id'][self::BOOK_ONLINECOURSE_NAME]
                                && !empty($arDeal['CONTACT'][Constants::UF_CONTACT_WEBINAR_COMPLETE_CODE])
                                && count($arDeal['CONTACT'][Constants::UF_CONTACT_WEBINAR_COMPLETE_CODE]) >= 2) {
                                $this->api->removeEmails($this->arRemoteBooksRef['value2id'][self::BOOK_ONLINECOURSE_NAME], [$email]);
                            }
                        }
                    }
                    $bNeedMove = $bNeedMove && !$bAlreadyMove;
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
                case Application::DEAL_CATEGORY_ONLINE_COURSE_ID:
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
                            } elseif($obBookEmail->book_id == $this->arRemoteBooksRef['value2id'][self::BOOK_ONLINECOURSE_NAME]) {
                                $bNeedAdd = false;
                            } elseif($obBookEmail->book_id == $this->arRemoteBooksRef['value2id'][self::BOOK_WEBINAR_NAME]) {
                                $bNeedAdd = false;
                                $this->api->removeEmails($obBookEmail->book_id, [$email]);
                            }
                        }
                    }

                    if($bNeedAdd) {
                        $arFields = $this->makeEmailFields($arDeal['CONTACT']);
                        if(in_array($arDeal['STAGE_ID'], ['C54:PREPARATION','C54:4','C54:WON'])) {
                            $arFields['variables']['ss'] = self::BOOK_SEGMENT_WARM;
                        } else {
                            $arFields['variables']['ss'] = self::BOOK_SEGMENT_COLD;
                        }

                        $result = $this->api->addEmails($this->arRemoteBooksRef['value2id'][self::BOOK_ONLINECOURSE_NAME], [$arFields]);

                        $this->checkContactUpdate($arDeal['CONTACT'], $arContactUpdateFields, self::CONTACT_UF_BOOKS_ONLINECOURSE_NAME);
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
                                } elseif ($obBookEmail->book_id == $this->arRemoteBooksRef['value2id'][self::BOOK_ONLINECOURSE_NAME]) {
                                    $this->api->removeEmails($obBookEmail->book_id, [$email]);
                                } elseif ($obBookEmail->book_id == $this->arRemoteBooksRef['value2id'][self::BOOK_GREENBASE_NAME]){
                                    $bNeedAdd = false;
                                    if(in_array($arDeal['STAGE_ID'], [Constants::DEAL_CAT_SEMINAR_PRACTICE_STAGE_SEND, Constants::DEAL_CAT_ONLINE_PRACTICE_STAGE_SEND])) {
                                        $bToWarm = true;
                                        foreach ($obBookEmail->variables as $obVariable) {
                                            if ($obVariable->name === 'ss' && ($obVariable->value == self::BOOK_SEGMENT_WARM || $obVariable->value == self::BOOK_SEGMENT_REJECT)) {
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
                                $this->arRemoteBooksRef['value2id'][self::BOOK_GREENBASE_NAME],
                                $this->arRemoteBooksRef['value2id'][self::BOOK_ONLINECOURSE_NAME]
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
    }*/

    /*protected function handleDealUpdate($arDeal, $arEventDealFields)
    {
        
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
                                        $this->arRemoteBooksRef['value2id'][self::BOOK_ONLINECOURSE_NAME],
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
                            $bAlreadyMove = false;
                            $bToWarm = false;
                            if(is_array($arEmailData)) {
                                foreach ($arEmailData as $obBookEmail) {
                                    if (in_array($obBookEmail->book_id, [
                                        $this->arRemoteBooksRef['value2id'][self::BOOK_FP_NAME],
                                        $this->arRemoteBooksRef['value2id'][self::BOOK_BE_NAME],
                                        $this->arRemoteBooksRef['value2id'][self::BOOK_03_NAME],
                                        $this->arRemoteBooksRef['value2id'][self::BOOK_911_NAME],
                                    ])) {
                                        $bAlreadyMove = true;
                                        $bNeedMove = false;
                                    } elseif($obBookEmail->book_id == $this->arRemoteBooksRef['value2id'][self::BOOK_GREENBASE_NAME]) {
                                        $bNeedMove = false;
                                        foreach ($obBookEmail->variables as $obVariable) {
                                            if($obVariable->name === 'ss' && $obVariable->value == self::BOOK_SEGMENT_WARM) {
                                                $bAlreadyMove = true;
                                            }
                                        }
                                    } elseif($obBookEmail->book_id == $this->arRemoteBooksRef['value2id'][self::BOOK_WEBINAR_NAME]
                                        && !empty($arDeal['CONTACT'][Constants::UF_CONTACT_WEBINAR_COMPLETE_CODE])) {
                                        $bNeedMove = false;
                                        $bToWarm = true;
                                        foreach ($obBookEmail->variables as $obVariable) {
                                            if($obVariable->name === 'ss' && ($obVariable->value == self::BOOK_SEGMENT_WARM || $obVariable->value == self::BOOK_SEGMENT_REJECT)) {
                                                if($obVariable->value ==  self::BOOK_SEGMENT_WARM && count($arDeal['CONTACT'][Constants::UF_CONTACT_WEBINAR_COMPLETE_CODE]) >= 2) {
                                                    $bNeedMove = true;
                                                }
                                                $bToWarm = false;
                                            }
                                        }
                                    } elseif($obBookEmail->book_id == $this->arRemoteBooksRef['value2id'][self::BOOK_ONLINECOURSE_NAME]) {
                                        if(empty($arDeal['CONTACT'][Constants::UF_CONTACT_WEBINAR_COMPLETE_CODE])
                                            || (!empty($arDeal['CONTACT'][Constants::UF_CONTACT_WEBINAR_COMPLETE_CODE]) && count($arDeal['CONTACT'][Constants::UF_CONTACT_WEBINAR_COMPLETE_CODE]) < 2)) {
                                            $bNeedMove = false;
                                        } else {
                                            $this->api->removeEmails($this->arRemoteBooksRef['value2id'][self::BOOK_ONLINECOURSE_NAME], [$email]);
                                        }
                                    }
                                }
                            }
                            $bNeedMove = $bNeedMove && !$bAlreadyMove;
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
                case Application::DEAL_CATEGORY_ONLINE_COURSE_ID:
                    if(in_array($arDeal['STAGE_ID'], ['C54:PREPARATION','C54:4','C54:WON'])) {
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
                                } elseif ($obBookEmail->book_id == $this->arRemoteBooksRef['value2id'][self::BOOK_ONLINECOURSE_NAME]){
                                    foreach ($obBookEmail->variables as $obVariable) {
                                        if ($obVariable->name == 'ss' && $obVariable->value == self::BOOK_SEGMENT_WARM) {
                                            $bNeedMove = false;
                                            break;
                                        }
                                    }
                                }
                            }
                        }
                        if($bNeedMove) {
                            $this->api->updateEmailVariables($this->arRemoteBooksRef['value2id'][self::BOOK_ONLINECOURSE_NAME], $email, ['ss' => self::BOOK_SEGMENT_WARM]);
                        }
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
                                    } elseif ($obBookEmail->book_id == $this->arRemoteBooksRef['value2id'][self::BOOK_ONLINECOURSE_NAME]) {
                                        $this->api->removeEmails($obBookEmail->book_id, [$email]);
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
                                    } elseif ($obBookEmail->book_id == $this->arRemoteBooksRef['value2id'][self::BOOK_ONLINECOURSE_NAME]) {
                                        $this->api->removeEmails($obBookEmail->book_id, [$email]);
                                    }
                                }

                                if($bNeedMove) {
                                    $arFields = $this->makeEmailFields($arDeal['CONTACT']);
                                    $arFields['variables']['ss'] = $arDeal['STAGE_ID'] == Constants::DEAL_CAT_SEMINAR_PRACTICE_STAGE_PAY
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
                                } elseif ($obBookEmail->book_id == $this->arRemoteBooksRef['value2id'][self::BOOK_ONLINECOURSE_NAME]) {
                                    $this->api->removeEmails($obBookEmail->book_id, [$email]);
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
                                } elseif ($obBookEmail->book_id == $this->arRemoteBooksRef['value2id'][self::BOOK_ONLINECOURSE_NAME]) {
                                    $this->api->removeEmails($obBookEmail->book_id, [$email]);
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
                                } elseif ($obBookEmail->book_id == $this->arRemoteBooksRef['value2id'][self::BOOK_ONLINECOURSE_NAME]) {
                                    $this->api->removeEmails($obBookEmail->book_id, [$email]);
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
    }*/

    protected function getCrmInfoByEmail($email)
    {
        $dbContacts = \Bitrix\Crm\ContactTable::query()
            ->setFilter(['=EMAIL' => $email])
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
        $arContacts = [];
        while($arContact = $dbContacts->fetch()) {
            $arContacts[$arContact['ID']] = $arContact;
        }
        $dbDeals = \Bitrix\Crm\DealTable::query()
            ->setFilter(['CONTACT_ID' => array_keys($arContacts), 'CATEGORY_ID' => $this->arCategories])
            ->setSelect([
                'ID','STAGE_ID','CATEGORY_ID','CONTACT_ID',
                Constants::UF_DEAL_POST_CODE,
                Constants::UF_DEAL_STAFF_CNT_CODE,
                Constants::UF_DEAL_WEBINAR_COMPLETE_CNT_CODE,
                Constants::UF_DEAL_WEBINAR_NAME_CODE,
                Constants::UF_DEAL_WEBINAR_DATE_CODE
            ])
            ->exec();
        $arDeals = [];
        while($arDeal = $dbDeals->fetch()) {
            $arDeals[] = $arDeal;
        }

        return [$arContacts, $arDeals];
    }

    protected function sync($email, $correctBookId, $correctSegment, $arDeal)
    {
        $arEmailData = $this->api->getEmailGlobalInfo($email);
        $bookFound = false;
        if (is_array($arEmailData)) {
            foreach ($arEmailData as $obBookEmail) {
                if (in_array($obBookEmail->book_id, [
                    $this->arRemoteBooksRef['value2id'][self::BOOK_WEBINAR_NAME],
                    $this->arRemoteBooksRef['value2id'][self::BOOK_ONLINECOURSE_NAME],
                    $this->arRemoteBooksRef['value2id'][self::BOOK_GREENBASE_NAME],
                    $this->arRemoteBooksRef['value2id'][self::BOOK_FP_NAME],
                    $this->arRemoteBooksRef['value2id'][self::BOOK_BE_NAME],
                    //$this->arRemoteBooksRef['value2id'][self::BOOK_03_NAME],
                    //$this->arRemoteBooksRef['value2id'][self::BOOK_911_NAME],
                ])) {
                    if ($correctBookId !== $obBookEmail->book_id) {
                        $this->api->removeEmails($obBookEmail->book_id, [$email]);
                    } else {
                        $bookFound = true;
                        $segmentFound = false;
                        foreach ($obBookEmail->variables as $obVariable) {
                            if ($obVariable->name === 'ss') {
                                $segmentFound = true;
                                if ($obVariable->value !== $correctSegment) {
                                    $this->api->updateEmailVariables($obBookEmail->book_id, $email, ['ss' => $correctSegment]);
                                    break;
                                }
                            }
                        }
                        if (!$segmentFound) {
                            $this->api->updateEmailVariables($obBookEmail->book_id, $email, ['ss' => $correctSegment]);
                        }

                        if($arDeal['CATEGORY_ID'] == Application::DEAL_CATEGORY_WEBINAR_ID) {
                            $this->updateRemoteWebinarValue($obBookEmail->book_id, $arDeal);
                        }
                    }
                }
            }
        }
        if (!$bookFound) {
            $arFields = $this->makeEmailFields($arDeal['CONTACT']);
            $arFields['variables']['ss'] = $correctSegment;
            if($arDeal['CATEGORY_ID'] == Application::DEAL_CATEGORY_WEBINAR_ID && !empty($arDeal[Constants::UF_DEAL_WEBINAR_NAME_CODE])) {
                $arFields['variables']['webinar'] = $arDeal[Constants::UF_DEAL_WEBINAR_NAME_CODE].' '.$arDeal[Constants::UF_DEAL_WEBINAR_DATE_CODE];
            }
            $result = $this->api->addEmails($correctBookId, [$arFields]);
            $this->processAddResult($result, $arDeal, $arFields);
        }
    }

    protected function processAddResult($result, $arDeal, $arFields)
    {
        if (!empty($result)) {
            if ($result instanceof \stdClass) {
                if (!isset($result->result) || !$result->result) {
                    Application::log(
                        'Error add email to sendpulse. Deal: ' . $arDeal['ID'] . PHP_EOL
                        . 'fields: ' . print_r($arFields, true) . PHP_EOL
                        . 'result: ' . print_r($result, true),
                        __CLASS__ . '.log'
                    );
                }
            } else {
                Application::log(
                    'Error add email to sendpulse. Deal: ' . $arDeal['ID'] . PHP_EOL
                    . 'fields: ' . print_r($arFields, true) . PHP_EOL
                    . 'result: ' . print_r($result, true),
                    __CLASS__ . '.log'
                );
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
            $start = 0;
            while(true) {
                $arBooks = $this->api->listAddressBooks(100, $start);
                if (!empty($arBooks)) {
                    foreach ($arBooks as $obBook) {
                        if ($obBook instanceof \stdClass) {
                            $this->arRemoteBooksRef['id2value'][$obBook->id] = $obBook->name;
                            $this->arRemoteBooksRef['value2id'][$obBook->name] = $obBook->id;
                        }
                    }
                    if(count($arBooks) < 100) {
                        break;
                    } else {
                        $start += 100;
                    }
                }
            }
        } catch(\Exception $e) {
            Application::log('Error get sendpulse books, '.$e, __CLASS__.'::'.__METHOD__.'.log');
            $this->enabled = false;
        }
    }
}