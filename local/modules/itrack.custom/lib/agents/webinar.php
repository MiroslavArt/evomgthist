<?php

namespace iTrack\Custom\Agents;

use Bitrix\Crm\ContactTable;
use Bitrix\Crm\DealTable;
use Bitrix\Crm\Order\Contact;
use Bitrix\Iblock\ElementTable;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\Web\Json;

class Webinar
{
    /**
     * Ид инфоблока вебинаров
     * @var int
     */
    static $iblockId;
    /**
     * Токен доступа к апи бизон365
     * @var string
     */
    static $token;
    /**
     * Ид аккаунта в системе бизон365
     * @var int
     */
    static $accountId;
    /**
     * Массив вебинаров из бд
     * @var array
     */
    static $arWebinars = [];
    /**
     * Массив вебинаров из апи
     * @var array
     */
    static $arList = [];

    /**
     * Агент заполняет список вебинаров из апи в инфоблок
     *
     * @return string
     * @throws \Bitrix\Main\ArgumentNullException
     * @throws \Bitrix\Main\ArgumentOutOfRangeException
     * @throws \Bitrix\Main\LoaderException
     */
    public static function getWebinars()
    {
        if(Loader::includeModule('iblock')) {
            self::$iblockId = Option::get('itrack.custom', 'webinars_iblock_id', 0);
            self::$token = Option::get('itrack.custom', 'bizon365_token', '');
            self::$accountId = Option::get('itrack.custom', 'bizon365_id', '');

            if(self::$iblockId > 0 && strlen(self::$token) > 0) {
                self::fetchWebinarsFromDb();
                self::fetchWebinarsFromBizon();
                self::fillDb();
            }
        }

        return '\iTrack\Custom\Agents\Webinar::getWebinars();';
    }

    /**
     * метод получает список вебинаров из бд
     *
     * @throws ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    protected static function fetchWebinarsFromDb()
    {
        $dbWebinar = ElementTable::query()
            ->setSelect(['ID','XML_ID','CODE'])
            ->exec();
        while($arWebinar = $dbWebinar->fetch()) {
            self::$arWebinars[$arWebinar['XML_ID']] = $arWebinar;
        }
    }

    /**
     * метод получает список завершенных вебинаров с сервера бизон365
     */
    protected static function fetchWebinarsFromBizon()
    {
        $httpClient = new HttpClient([
            "redirect" => true,
            "waitResponse" => true,
            "socketTimeout" => 30
        ]);

        $httpClient->setHeader('X-Token',self::$token);
        $response = $httpClient->get('https://online.bizon365.ru/api/v1/webinars/reports/getlist?limit=100');
        try {
            $result = Json::decode($response);
            if(!empty($result['list'])) {
                self::$arList = $result['list'];
            }
        } catch(\Exception $e) {
            self::log('error parse api response: '.$e->getMessage());
        }
    }

    /**
     * Метод запрашивает данные по посетителям вебинара
     *
     * @param string $webinarId
     * @return array
     */
    protected static function getWebinarViewers($webinarId)
    {
        $httpClient = new HttpClient([
            "redirect" => true,
            "waitResponse" => true,
            "socketTimeout" => 30
        ]);
        $httpClient->setHeader('X-Token',self::$token);

        $list = [];

        /**
         * Функция для рекурсивного получения списка посетителей из-за наличия "пагинации"
         * максимум 1000 за запрос
         * @param int $skip Сколько записей в выборке пропустить с начала
         */
        $request = function($skip = 0) use ($httpClient, &$list, $webinarId, &$request) {
            $url = 'https://online.bizon365.ru/api/v1/webinars/reports/getviewers?webinarId='.urlencode($webinarId);
            if($skip > 0) {
                $url .= '&skip='.$skip;
            }
            $response = $httpClient->get($url);
            try {
                $result = Json::decode($response);
                if(!empty($result['viewers'])) {
                    $list = array_merge($list, $result['viewers']);
                }
                if($result['count'] > 1000) {
                    if($skip > 0 && $result['count'] - ($skip + 1000) > 0) {
                        $request($skip + 1000);
                    } else {
                        $request(1000);
                    }
                }
            } catch(\Exception $e) {
                self::log('error parse api response: '.$e->getMessage());
            }
        };

        $request();

        return $list;
    }

    /**
     * Метод сравнивает список в базе и из апи и добавляет в базу вебинары которых еще нет
     */
    protected function fillDb()
    {
        if(!empty(self::$arList)) {
            $obElement = new \CIBlockElement();
            foreach(self::$arList as $arWebinar) {
                if(empty(self::$arWebinars[$arWebinar['_id']])) {
                    $arWebinarData = [];
                    try {
                        $arWebinarData = Json::decode($arWebinar['data']);
                    } catch(\Exception $e) {

                    }
                    // формируем имя как идентификатор комнаты без префикса с ид аккаунта + дата вебинара без времени
                    $name = '';
                    if(!empty($arWebinarData['group'])) {
                        $name = str_replace($arWebinarData['group'] . ':', '', $arWebinar['name']);
                    } else {
                        $name = str_replace(self::$accountId . ':', '', $arWebinar['name']);
                    }
                    $arTmp = explode('*', $arWebinar['webinarId']);
                    $arDate = explode('T', $arTmp[1]);
                    $name .= ' '.$arDate[0];
                    $arFields = [
                        'IBLOCK_ID' => self::$iblockId,
                        'ACTIVE' => 'Y',
                        'XML_ID' => $arWebinar['_id'],
                        'CODE' => $arWebinar['webinarId'],
                        'NAME' => $name,
                        'PREVIEW_TEXT' => $arWebinar['text'],
                        'PROPERTY_VALUES' => [
                            'ROOM_ID' => !empty($arWebinarData['roomid']) ? $arWebinarData['roomid'] : $arWebinar['name'],
                            'START' => !empty($arWebinarData['start']) ? (int)$arWebinarData['start'] : 0,
                            'DURATION' => !empty($arWebinarData['minutes']) ? (int)$arWebinarData['minutes'] : 0
                        ]
                    ];
                    if(!$obElement->Add($arFields)) {
                        self::log('error add webinar '.$arWebinar['text'].': '.$obElement->LAST_ERROR);
                    }
                }
            }
        }
    }

    /**
     * Агент проверки вебинаров которые начались 7 минут назад
     *
     * @deprecated Сейчас не используется, т.к. апи дает возможность получить вебинары только после окончания
     * @return string
     * @throws \Bitrix\Main\ArgumentNullException
     * @throws \Bitrix\Main\ArgumentOutOfRangeException
     * @throws \Bitrix\Main\LoaderException
     */
    public function checkStart()
    {
        if(Loader::includeModule('iblock')) {
            self::$iblockId = Option::get('itrack.custom', 'webinars_iblock_id', 0);
            if(self::$iblockId > 0) {
                $time = time()*100;
                $db = \CIBlockElement::GetList(
                    [],
                    [
                        'IBLOCK_ID' => self::$iblockId,
                        'PROPERTY_COMPLETE' => false,
                        '>=PROPERTY_START' => $time - 42000,
                        '<PROPERTY_START' => $time - 36000
                    ],
                    false,
                    false,
                    ['ID']
                );
                while($ar = $db->Fetch()) {
                    \CAgent::AddAgent(
                        '\iTrack\Custom\Agents\Webinar::processStart('.$ar['ID'].');',
                        'itrack.custom',
                        'N',
                        1
                    );
                    self::log(print_r($ar, true));
                }
            }
        }

        return '\iTrack\Custom\Agents\Webinar::checkStart();';
    }

    /**
     * Агент обработки начала вебинара
     *
     * @deprecated см. self::checkStart
     * @param int $id ИД вебинара в бд
     * @throws ArgumentException
     * @throws \Bitrix\Main\ArgumentNullException
     * @throws \Bitrix\Main\ArgumentOutOfRangeException
     * @throws \Bitrix\Main\LoaderException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    public static function processStart($id)
    {
        if(Loader::includeModule('iblock') && Loader::includeModule('crm')) {
            $db = \CIBlockElement::GetList([],['=ID' => $id], false, false, ['ID','IBLOCK_ID','CODE','PROPERTY_ROOM_ID']);
            if($arWebinar = $db->Fetch()) {
                self::$token = Option::get('itrack.custom', 'bizon365_token', '');
                self::$accountId = Option::get('itrack.custom', 'bizon365_id', '');

                $viewers = self::getWebinarViewers($arWebinar['CODE']);

                $obContact = new \CCrmContact();
                $obDeal = new \CCrmDeal();
                foreach($viewers as $viewer) {
                    $dbContact = ContactTable::query()
                        ->setFilter(['EMAIL' => $viewer['email']])
                        ->setSelect(['ID','UF_CRM_1582202212','EMAIL'])
                        ->exec();
                    if($arContact = $dbContact->fetch()) {
                        $dbDeal = \CCrmDeal::GetListEx(
                            [],
                            [
                                'CHECK_PERMISSIONS' => 'N',
                                'CONTACT_ID' => $arContact['ID'],
                                'CATEGORY_ID' => 50,
                                'STAGE_ID' => 'C50:NEW',
                                'UF_CRM_1582128672' => str_replace(self::$accountId.':','',$arWebinar['PROPERTY_ROOM_ID_VALUE'])
                            ],
                            false,
                            false,
                            ['ID']
                        );
                        if($arDeal = $dbDeal->Fetch()) {
                            $arFields = ['STAGE_ID' => 'C50:PREPARATION'];
                            if(!$obDeal->Update($arDeal['ID'], $arFields)) {
                                self::log('error update deal '.$arDeal['ID'].' stage: '.$obDeal->LAST_ERROR);
                            }
                        }
                        $newLinks = $arContact['UF_CRM_1582202212'];
                        if(!empty($newLinks)) {
                            $newLinks[] = $arWebinar['ID'];
                            $newLinks = array_values($newLinks);
                        } else {
                            $newLinks = [$arWebinar['ID']];
                        }
                        $arContactFields = ['UF_CRM_1582202212' => $newLinks];
                        if($obContact->Update($arContact['ID'], $arContactFields)) {
                            self::log('error update contact '.$arContact['ID'].' with webinar '.$arWebinar['ID'].': '.$obContact->LAST_ERROR);
                        }
                    }
                }

                $dbAgent = \CAgent::GetList([],['NAME' => '\iTrack\Custom\Agents\Webinar::processStart('.$arWebinar['ID'].');','MODULE_ID' => 'itrack.custom']);
                if($arAgent = $dbAgent->Fetch()) {
                    \CAgent::Delete($arAgent['ID']);
                }
            }
        }
    }

    /**
     * Агент получает список вебинаров которые завершились и создает агент обюработки по каждому вебинару
     *
     * @return string
     * @throws \Bitrix\Main\ArgumentNullException
     * @throws \Bitrix\Main\ArgumentOutOfRangeException
     * @throws \Bitrix\Main\LoaderException
     */
    public static function checkEnd()
    {
        if(Loader::includeModule('iblock')) {
            self::$iblockId = Option::get('itrack.custom', 'webinars_iblock_id', 0);
            if(self::$iblockId > 0) {
                $time = time()*100;
                $db = \CIBlockElement::GetList(
                    [],
                    [
                        'IBLOCK_ID' => self::$iblockId,
                        'PROPERTY_COMPLETE' => false
                    ],
                    false,
                    false,
                    ['ID']
                );
                while($ar = $db->Fetch()) {
                    \CAgent::AddAgent(
                        '\iTrack\Custom\Agents\Webinar::processEnd('.$ar['ID'].');',
                        'itrack.custom',
                        'N',
                        1,
                        '',
                        'Y',
                        '',
                        100,
                        53
                    );
                }
            }
        }

        return '\iTrack\Custom\Agents\Webinar::checkEnd();';
    }

    /**
     * Агент обрабатывает вебинар после окончания. Устанавливает поля в контактах, меняет стадии сделок
     * Удаляет сам себя после завершения из списка агентов
     *
     * @param int $id ID вебинара в бд
     * @throws ArgumentException
     * @throws \Bitrix\Main\ArgumentNullException
     * @throws \Bitrix\Main\ArgumentOutOfRangeException
     * @throws \Bitrix\Main\LoaderException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    public static function processEnd($id)
    {
        if(Loader::includeModule('iblock') && Loader::includeModule('crm')) {
            $db = \CIBlockElement::GetList([],['=ID' => $id], false, false, ['ID','IBLOCK_ID','NAME','CODE','PROPERTY_ROOM_ID','PROPERTY_DURATION']);
            if($arWebinar = $db->Fetch()) {
                self::$token = Option::get('itrack.custom', 'bizon365_token', '');
                self::$accountId = Option::get('itrack.custom', 'bizon365_id', '');

                /**
                 * список посетителей вебинара
                 * @var array $viewers
                 */
                $viewers = self::getWebinarViewers($arWebinar['CODE']);

                $obContact = new \CCrmContact(false);
                $obDeal = new \CCrmDeal(false);
                foreach($viewers as $viewer) {
                    /**
                     * Статус просмотра
                     * N - не заходил в вебинар
                     * F - посмотрел полностью
                     * E - посмотрел менее 50%
                     * @var string $viewStatus
                     */
                    $viewStatus = 'N';
                    if($viewer['finished']) {
                        $viewStatus = 'F';
                    } else {
                        $timeViewed = (int)(floor((float)$viewer['viewTill']/1000) - floor((float)$viewer['view']/1000));
                        if($timeViewed > 0) {
                            if (!empty($arWebinar['PROPERTY_DURATION_VALUE'])) {
                                if ((int)$timeViewed / ((int)$arWebinar['PROPERTY_DURATION_VALUE']) * 60 > 0.5) {
                                    $viewStatus = 'F';
                                } else {
                                    $viewStatus = 'E';
                                }
                            } else {
                                $viewStatus = 'E';
                            }
                        }
                    }

                    $arIdPart = explode('*', $arWebinar['CODE']);
                    $parsedDate = \DateTime::createFromFormat('Y-m-d\TH:i:s', $arIdPart[1]);
                    $arDealFilter = [
                        //'CHECK_PERMISSIONS' => 'N',
                        'CATEGORY_ID' => 50,
                        'STAGE_ID' => 'C50:NEW',
                        'UF_CRM_1582128672' => str_replace(self::$accountId.':','',$arWebinar['PROPERTY_ROOM_ID_VALUE']),
                        '>=UF_CRM_1582128645' => $parsedDate->format('d.m.Y').' 00:00:00',
                        '<UF_CRM_1582128645' => $parsedDate->format('d.m.Y').' 23:59:59'
                    ];
                    if(!empty($viewer['email']) || !empty($viewer['phone'])) {
                        if(!empty($viewer['email'])) {
                            $arDealFilter['UF_CRM_5D9A127B6B961'] = $viewer['email'];
                        } else {
                            $arDealFilter['UF_CRM_5D9A127A85C89'] = $viewer['phone'];
                        }

                        $dbDeal = DealTable::query()
                            ->setFilter($arDealFilter)
                            ->setSelect(['ID','CONTACT_ID'])
                            ->exec();
                    }
                    if(isset($dbDeal) && $arDeal = $dbDeal->fetch()) {
                        if($viewStatus !== 'N') {
                            // если посмотрел больше 50% - двигаем сделку в успешные, если менее - в стадию посетил вебинар
                            if($viewStatus === 'F') {
                                $arFields = ['STAGE_ID' => 'C50:WON'];
                            } else {
                                $arFields = ['STAGE_ID' => 'C50:LOSE'];
                            }
                            if (!$obDeal->Update($arDeal['ID'], $arFields, true, true, ['CURRENT_USER' => 53])) {
                                self::log('error update deal ' . $arDeal['ID'] . ' stage: ' . $obDeal->LAST_ERROR . print_r($arFields, true));
                            }
                        }

                        $dbContact = ContactTable::query()
                            ->setFilter(['ID' => $arDeal['CONTACT_ID']])
                            ->setSelect([
                                'ID',
                                'UF_CRM_1582202212', // посетил вебинар
                                'UF_CRM_1582222639', // посмотрел вебинар
                                'EMAIL'
                            ])
                            ->exec();
                        if($arContact = $dbContact->fetch()) {
                            // устанавливаем поля в контакте
                            // если посмотрел более 50% - добавляем вебинар к списку в поле "посморел вебинар"
                            // если менее - к списку "посетил вебинар"
                            if($viewStatus !== 'N') {
                                if($viewStatus === 'F') {
                                    $newLinks = $arContact['UF_CRM_1582222639'];
                                } else {
                                    $newLinks = $arContact['UF_CRM_1582202212'];
                                }

                                if (!empty($newLinks)) {
                                    $newLinks[] = $arWebinar['ID'];
                                    $newLinks = array_values(array_unique($newLinks));
                                } else {
                                    $newLinks = [$arWebinar['ID']];
                                }
                                if($viewStatus === 'F') {
                                    $arContactFields = ['UF_CRM_1582222639' => $newLinks];
                                } else {
                                    $arContactFields = ['UF_CRM_1582202212' => $newLinks];
                                }

                                if (!$obContact->Update($arContact['ID'], $arContactFields, true, true,['CURRENT_USER' => 53])) {
                                    self::log('error update contact ' . $arContact['ID'] . ' with webinar ' . $arWebinar['ID'] . ': ' . $obContact->LAST_ERROR . print_r($arContactFields, true));
                                }

                                if(Loader::includeModule('bizproc')) {
                                    // запустим бп на синхронизацию полей контакта со связанными сделками
                                    $arErrorsTmp = [];
                                    $wfId = \CBPDocument::StartWorkflow(
                                        299,
                                        array("crm", "CCrmDocumentContact", 'CONTACT_' . $arContact['ID']),
                                        [],
                                        $arErrorsTmp
                                    );
                                    if(!empty($arErrorsTmp)) {
                                        self::log('error start bizproc on contact '.$arContact['ID'].': '.print_r($arErrorsTmp, true));
                                    }
                                }
                            }
                        }
                    } else {
                        $dbContact = ContactTable::query()
                            ->setFilter([['LOGIC' => 'OR', 'EMAIL' => $viewer['email'], 'PHONE' => $viewer['phone']]])
                            ->setSelect(['ID'])
                            ->exec();
                        if($arContact = $dbContact->fetch()) {
                            $contactId = $arContact['ID'];
                        } else {
                            $arFields = [
                                'NAME' => $viewer['username'],
                                'ASSIGNED_BY_ID' => 56,
                                'PHONE' => ['VALUE' => $viewer['phone'], 'VALUE_TYPE' => 'WORK'],
                                'EMAIL' => ['VALUE' => $viewer['email'], 'VALUE_TYPE' => 'WORK'],
                                'COMMENTS' => 'Контакт с вебинара ' . $arWebinar['NAME']
                            ];
                            if ($viewStatus !== 'N') {
                                if ($viewStatus === 'F') {
                                    $arFields['UF_CRM_1582222639'] = [$arWebinar['ID']];
                                } else {
                                    $arFields['UF_CRM_1582202212'] = [$arWebinar['ID']];
                                }
                            }
                            $contactId = $obContact->Add($arFields, true, ['CURRENT_USER' => 53]);
                        }
                        if(!$contactId) {
                            self::log('error create contact '.print_r($arFields, true).': '.$obContact->LAST_ERROR);
                        } else {
                            $stageID = 'C50:NEW';
                            if($viewStatus === 'F') {
                                $stageID = 'C50:WON';
                            } elseif($viewStatus === 'E') {
                                $stageID = 'C50:LOSE';
                            }
                            $arIdPart = explode('*', $arWebinar['CODE']);
                            $parsedDate = \DateTime::createFromFormat('Y-m-d\TH:i:s', $arIdPart[1]);
                            $arDealFields = [
                                'TITLE' => $arWebinar['NAME'],
                                'ASSIGNED_BY_ID' => 56,
                                'CONTACT_ID' => $contactId,
                                'CATEGORY_ID' => 50,
                                'STAGE_ID' => $stageID,
                                'UF_CRM_1581578029' => 'Вебинар Бизон365 '.$arWebinar['CODE'],
                                'UF_CRM_1582128672' => str_replace(self::$accountId.':','',$arWebinar['PROPERTY_ROOM_ID_VALUE']), // ид комнаты
                                'UF_CRM_1582128619' => $arWebinar['NAME'], // название вебинара
                                'UF_CRM_1582128645' => $parsedDate->format('d.m.Y H:i:s')
                            ];
                            if(!$obDeal->Add($arDealFields, true, ['CURRENT_USER' => 53])) {
                                self::log('error add deal '.print_r($arDealFields, true).': '.$obDeal->LAST_ERROR);
                            }
                            
                            if(Loader::includeModule('bizproc')) {
                                // запустим бп на синхронизацию полей контакта со связанными сделками
                                $arErrorsTmp = [];
                                $wfId = \CBPDocument::StartWorkflow(
                                    299,
                                    array("crm", "CCrmDocumentContact", 'CONTACT_' . $contactId),
                                    [],
                                    $arErrorsTmp
                                );
                                if(!empty($arErrorsTmp)) {
                                    self::log('error start bizproc on contact '.$contactId.': '.print_r($arErrorsTmp, true));
                                }
                            }
                        }
                    }
                }

                // переместим в проваленые все сделки, которые остались в начальном статусе, т.е. посетитель не заходил в вебинар
                $dbDeals = \CCrmDeal::GetListEx(
                    [],
                    [
                        'CHECK_PERMISSIONS' => 'N',
                        'CATEGORY_ID' => 50,
                        'STAGE_ID' => 'C50:NEW',
                        'UF_CRM_1582128672' => str_replace(self::$accountId.':','',$arWebinar['PROPERTY_ROOM_ID_VALUE'])
                    ],
                    false,
                    false,
                    ['ID','UF_CRM_1582128645']
                );
                while($arDeal = $dbDeals->Fetch()) {
                    $date = \DateTime::createFromFormat('d.m.Y H:i:s', $arDeal['UF_CRM_1582128645']);
                    if($date->getTimestamp() <= time()) {
                        $arFields = ['STAGE_ID' => 'C50:LOSE'];
                        if (!$obDeal->Update($arDeal['ID'], $arFields)) {
                            self::log('error update deal ' . $arDeal['ID'] . ' stage: ' . $obDeal->LAST_ERROR);
                        }
                    }
                }

                // пометим вебинар в бд как обработанный
                \CIBlockElement::SetPropertyValuesEx(
                    $arWebinar['ID'],
                    $arWebinar['IBLOCK_ID'],
                    ['COMPLETE' => 'Y']
                );

                // удалим агента
                $dbAgent = \CAgent::GetList([],['NAME' => '\iTrack\Custom\Agents\Webinar::processEnd('.$arWebinar['ID'].');','MODULE_ID' => 'itrack.custom']);
                if($arAgent = $dbAgent->Fetch()) {
                    @\CAgent::Delete($arAgent['ID']);
                }
            }
        }
    }

    /**
     * логи
     * @param $msg
     */
    protected function log($msg)
    {
        // todo: better logging
        file_put_contents(__DIR__.'/log.log', date(DATE_COOKIE).': '.$msg.PHP_EOL, FILE_APPEND);
    }
}