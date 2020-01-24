<?php

use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

class CITrackCoursesAnalytics extends \CBitrixComponent implements Controllerable
{
    protected $modules = ['crm','tasks'];
    protected $stages = [];
    protected $arStagesRef = [];
    protected $deals = [];
    protected $contacts = [];
    protected $tasks = [];
    protected $ufFields = [
        'DEAL_COURSE' => '',
        'TASK_SESSION' => '',
        'DEAL_PAY_SUM_1' => '',
        'DEAL_PAY_SUM_2' => '',
        'DEAL_PAY_SUM_3' => '',
        'DEAL_PAY_SUM_4' => '',
        'DEAL_PAY_SUM_5' => '',
        'DEAL_PAY_SUM_6' => '',
        'DEAL_PAY_DATE_1' => '',
        'DEAL_PAY_DATE_2' => '',
        'DEAL_PAY_DATE_3' => '',
        'DEAL_PAY_DATE_4' => '',
        'DEAL_PAY_DATE_5' => '',
        'DEAL_PAY_DATE_6' => '',
        'DEAL_PAY_PAID_1' => '',
        'DEAL_PAY_PAID_2' => '',
        'DEAL_PAY_PAID_3' => '',
        'DEAL_PAY_PAID_4' => '',
        'DEAL_PAY_PAID_5' => '',
        'DEAL_PAY_PAID_6' => '',
        'DEAL_COURSE_GROUP' => ''
    ];

    const FILTER_ID_PREFIX = 'itrack_courses_analytics_';

    public function configureActions()
    {
        return [
            'getCourses' => [],
            'getCoursesList' => [],
            'getGroupList' => [],
            'getPayments' => []
        ];
    }

    public function onPrepareComponentParams($arParams)
    {
        if(!empty($arParams['CATEGORY_ID'])) {
            $arParams['CATEGORY_ID'] = (int)$arParams['CATEGORY_ID'];
        }
        $arParams['IS_PAYMENTS'] = !empty($arParams['TYPE']) && $arParams['TYPE'] == 'payments';
        $arParams['FILTER_ID'] = self::FILTER_ID_PREFIX.$arParams['CATEGORY_ID'].$arParams['TYPE'];
        return parent::onPrepareComponentParams($arParams);
    }

    protected function includeModules()
    {
        $bInclude = true;
        foreach($this->modules as $moduleId) {
            if (!\Bitrix\Main\Loader::includeModule($moduleId)) {
                $bInclude = false;
            }
        }
        return $bInclude;
    }

    protected function checkRights()
    {
        $userPermissions = \CCrmPerms::GetCurrentUserPermissions();
        return \CCrmDeal::CheckReadPermission(0, $userPermissions);
    }

    public function executeComponent()
    {
        if(!$this->includeModules()) {
            ShowError('Required modules not included');
            return;
        }

        if(!$this->checkRights()) {
            ShowError('Permission denied');
            return;
        }

        if(empty($this->arParams['CATEGORY_ID']) || $this->arParams['CATEGORY_ID'] <= 0) {
            ShowError('Empty category ID');
            return;
        } else {
            global $APPLICATION;
            $this->arResult['PAGE_URL'] = $APPLICATION->GetCurDir();
            $this->initFilter();
            $this->includeComponentTemplate();
        }
    }

    protected function initFilter()
    {
        $arCourses = $this->getCoursesListAction();
        $arFilterCourses = [];
        foreach($arCourses as $arCourse) {
            $arFilterCourses[$arCourse['id']] = $arCourse['name'];
        }
        $arGroups = $this->getGroupListAction();
        $arFilterGroups = [];
        foreach($arGroups as $arGroup) {
            $arFilterGroups[$arGroup['id']] = $arGroup['name'];
        }
        $this->arResult['FILTER_FIELDS'] = [
            ['id' => 'COURSE', 'name' => Loc::getMessage('ITRACK_CAC_COLUMN_COURSE_NAME'), 'type' => 'list', 'params' => ['multiple' => true], 'items' => $arFilterCourses, 'default' => true],
            ['id' => 'INTERVAL', 'name' => Loc::getMessage('ITRACK_CAC_COLUMN_INTERVAL_NAME'), 'type' => 'date', 'default' => true]
        ];
        if(!$this->arParams['IS_PAYMENTS']) {
            $this->arResult['FILTER_FIELDS'][] = ['id' => 'COURSE_GROUP', 'name' => Loc::getMessage('ITRACK_CAC_COLUMN_COURSE_GROUP_NAME'), 'type' => 'list', 'params' => ['multiple' => true], 'items' => $arFilterGroups, 'default' => true];
            $this->arResult['FILTER_FIELDS'][] = ['id' => 'ASSIGNED_BY_ID', 'name' => Loc::getMessage('ITRACK_CAC_COLUMN_ASSIGNED_BY_ID_NAME'), 'params' => ['multiple' => true], 'type' => 'custom_entity', 'default' => true];
        } else {
            $this->arResult['FILTER_FIELDS'][] = ['id' => 'PAYDATE', 'name' => Loc::getMessage('ITRACK_CAC_COLUMN_PAYDATE_NAME'), 'type' => 'date', 'default' => true];
        }
    }

    public function getCoursesAction($categoryId)
    {
        $result = [];
        if($this->includeModules() && $this->checkRights()) {
            $this->initStages($categoryId);
            $this->initUserfields();
            $filter = $this->makeDealFilter($categoryId);
            $result = $this->getCoursesData($filter);
        }
        return $result;
    }

    protected function initUserfields()
    {
        $this->ufFields = [
            'DEAL_COURSE' => 'UF_CRM_1573215888', // TODO: get automaticaly
            'TASK_SESSION' => 'UF_SESSION',
            'DEAL_PAY_SUM_1' => 'UF_CRM_1572207520',
            'DEAL_PAY_DATE_1' => 'UF_CRM_1572207664',
            'DEAL_PAY_SUM_2' => 'UF_CRM_1572874606',
            'DEAL_PAY_DATE_2' => 'UF_CRM_1572874559',
            'DEAL_PAY_SUM_3' => 'UF_CRM_1572874797',
            'DEAL_PAY_DATE_3' => 'UF_CRM_1572874748',
            'DEAL_PAY_SUM_4' => 'UF_CRM_1572874967',
            'DEAL_PAY_DATE_4' => 'UF_CRM_1572874904',
            'DEAL_PAY_SUM_5' => 'UF_CRM_1572875149',
            'DEAL_PAY_DATE_5' => 'UF_CRM_1572875105',
            'DEAL_PAY_SUM_6' => 'UF_CRM_1572875331',
            'DEAL_PAY_DATE_6' => 'UF_CRM_1572875271',
            'DEAL_PAY_PAID_1' => 'UF_CRM_1575378171',
            'DEAL_PAY_PAID_2' => 'UF_CRM_1575545399964',
            'DEAL_PAY_PAID_3' => 'UF_CRM_1575545686478',
            'DEAL_PAY_PAID_4' => 'UF_CRM_1575545894693',
            'DEAL_PAY_PAID_5' => 'UF_CRM_1575378439',
            'DEAL_PAY_PAID_6' => 'UF_CRM_1575378497',
            'DEAL_COURSE_GROUP' => 'UF_CRM_1573216197'
        ];
    }

    protected function initStages($categoryId)
    {
        $dbStages = \Bitrix\Crm\StatusTable::query()
            ->setFilter(['=ENTITY_ID' => 'DEAL_STAGE_'.$categoryId])
            ->setSelect(['STATUS_ID','NAME', 'SORT', 'ENTITY_ID'])
            ->setOrder(['SORT' => 'asc'])
            ->exec();
        while($arStage = $dbStages->fetch()) {
            if(\CCrmDeal::GetSemanticID($arStage['STATUS_ID']) == \Bitrix\Crm\PhaseSemantics::FAILURE
                || \CCrmDeal::GetSemanticID($arStage['STATUS_ID']) == \Bitrix\Crm\PhaseSemantics::SUCCESS) {
                continue;
            }
            $this->arStagesRef[$arStage['STATUS_ID']] = $arStage;
            $this->stages[] = $arStage['STATUS_ID'];
        }
    }

    protected function getCoursesData($filter)
    {
        $result = [];

        $this->fetchDeals($filter);
        $this->fetchContacts();
        $this->fetchTasks();

        global $USER;
        $pathToTask = Option::get("tasks", "paths_task_user_action", null, SITE_ID);
        $pathToTask = str_replace("#user_id#", $USER->GetID(), $pathToTask);

        $pathToDeal = Option::get('crm', 'path_to_deal_details', '', SITE_ID);

        foreach($this->deals as $arDeal) {
            if(empty($arDeal['CONTACT_ID'])) {
                continue;
            }
            $arSessions = [];
            $countCompleted = 0;
            foreach($this->stages as $stageId) {
                $arSections = [];

                foreach($this->tasks as $arTask) {
                    $bFound = false;
                    foreach($arTask['UF_CRM_TASK'] as $linkedValue) {
                        if($linkedValue == 'D_'.$arDeal['ID']) {
                            $bFound = true;
                        }
                    }
                    if($bFound && $arTask[$this->ufFields['TASK_SESSION']] == $stageId) {
                        $arSections[] = [
                            'id' => $arTask['ID'],
                            'name' => $arTask['TITLE'],
                            'tasks' => [[
                                'id' => $arTask['ID'],
                                'completeTill' => $arTask['DEADLINE']->format('Y-m-d'),
                                'completed' => !empty($arTask['CLOSED_DATE']),
                                'url' => \CComponentEngine::MakePathFromTemplate($pathToTask, array("task_id" => $arTask["ID"], "action" => "view"))
                            ]]
                        ];
                        if(!empty($arTask['CLOSED_DATE'])) {
                            $countCompleted++;
                        }
                    }
                }

                $arSessions[] = [
                    'id' => $stageId,
                    'name' => $this->arStagesRef[$stageId]['NAME'],
                    'sections' => $arSections
                ];
            }
            $name = $this->contacts[$arDeal['CONTACT_ID']]['LAST_NAME'].' '.$this->contacts[$arDeal['CONTACT_ID']]['NAME'];
            $result[] = [
                'name' => $name,
                'dealId' => $arDeal['ID'],
                'href' => \CComponentEngine::MakePathFromTemplate($pathToDeal, ["deal_id" => $arDeal["ID"]]),
                'contactId' => $arDeal['CONTACT_ID'],
                'sessions' => $arSessions,
                'countCompleted' => $countCompleted
            ];
        }

        return $result;
    }

    protected function makeDealFilter($categoryId, $isPayment = false)
    {
        $obFilterOption = new \Bitrix\Main\UI\Filter\Options(self::FILTER_ID_PREFIX.$categoryId.($isPayment ? 'payments' : ''));
        $arFilterData = $obFilterOption->getFilter();
        $arFilter = [
            'CATEGORY_ID' => $categoryId
        ];
        if(!empty($arFilterData['COURSE'])) {
            $arFilter[$this->ufFields['DEAL_COURSE']] = $arFilterData['COURSE'];
        }
        if(isset($arFilterData['INTERVAL_from']) && $arFilterData['INTERVAL_from']) {
            $arFilter['>=BEGINDATE'] = $arFilterData['INTERVAL_from'];
        }
        if(isset($arFilterData['INTERVAL_to']) && $arFilterData['INTERVAL_to']) {
            $arFilter['<=BEGINDATE'] = $arFilterData['INTERVAL_to'];
        }
        if(!empty($arFilterData['COURSE_GROUP'])) {
            $arFilter[$this->ufFields['DEAL_COURSE_GROUP']] = $arFilterData['COURSE_GROUP'];
        }
        if(!empty($arFilterData['ASSIGNED_BY_ID'])) {
            $arFilter['ASSIGNED_BY_ID'] = $arFilterData['ASSIGNED_BY_ID'];
        }
        if(!empty($arFilterData['FIND'])) {
            $arFilter[] = [
                'LOGIC' => 'OR',
                ['%TITLE' => $arFilterData['FIND']],
                ['%CONTACT_NAME' => $arFilterData['FIND']],
                ['%CONTACT_FULL_NAME' => $arFilterData['FIND']]
            ];
        }
        if(isset($arFilterData['PAYDATE_from']) && $arFilterData['PAYDATE_from']) {
            $arPDFilter = ['LOGIC' => 'OR'];
            for($i = 1; $i <= 6; $i++) {
                $arPDFilter[] = ['>='.$this->ufFields['DEAL_PAY_DATE_'.$i] => $arFilterData['PAYDATE_from']];
            }
            $arFilter[] = $arPDFilter;
        }
        if(isset($arFilterData['PAYDATE_to']) && $arFilterData['PAYDATE_to']) {
            $arPDFilter = ['LOGIC' => 'OR'];
            for($i = 1; $i <= 6; $i++) {
                $arPDFilter[] = ['<='.$this->ufFields['DEAL_PAY_DATE_'.$i] => $arFilterData['PAYDATE_to']];
            }
            $arFilter[] = $arPDFilter;
        }

        return $arFilter;
    }

    protected function fetchDeals($filter)
    {
        $arSelect = ['ID','CONTACT_ID','STAGE_ID'];
        for($i = 1; $i <= 6; $i++) {
            $arSelect[] = $this->ufFields['DEAL_PAY_SUM_'.$i];
            $arSelect[] = $this->ufFields['DEAL_PAY_DATE_'.$i];
            $arSelect[] = $this->ufFields['DEAL_PAY_PAID_'.$i];
        }
        $dbDeals = \CCrmDeal::GetListEx(
            [],
            $filter,
            false,
            false,
            $arSelect
        );
        while($arDeal = $dbDeals->Fetch()) {
            $this->deals[$arDeal['ID']] = $arDeal;
        }
    }

    protected function fetchContacts()
    {
        $cID = [];
        foreach ($this->deals as $arDeal) {
            if(!empty($arDeal['CONTACT_ID'])) {
                $cID[] = $arDeal['CONTACT_ID'];
            }
        }
        $dbContacts = \Bitrix\Crm\ContactTable::query()
            ->setFilter(['=ID' => $cID])
            ->setSelect(['ID','NAME','LAST_NAME','SECOND_NAME','SHORT_NAME','FULL_NAME'])
            ->exec();
        while($arContact = $dbContacts->fetch()) {
            $this->contacts[$arContact['ID']] = $arContact;
        }
    }

    protected function fetchTasks()
    {
        if(!empty($this->deals)) {
            $arIDs = [];
            $dealIDs = array_keys($this->deals);
            foreach ($dealIDs as $id) {
                $arIDs[] = 'D_'.$id;
            }
            $dbTasks = \Bitrix\Tasks\TaskTable::query()
                ->setFilter(['UF_CRM_TASK' => $arIDs, 'ZOMBIE' => 'N'])
                ->setSelect(['ID', 'TITLE', 'UF_CRM_TASK', $this->ufFields['TASK_SESSION'], 'DEADLINE_COUNTED', 'DEADLINE', 'CLOSED_DATE'])
                ->exec();
            while ($arTask = $dbTasks->fetch()) {
                $this->tasks[$arTask['ID']] = $arTask;
            }
        }
    }

    public function getCoursesListAction()
    {
        $result = [];
        $this->initUserfields();
        $dbUserField = \CUserTypeEntity::GetList([],['FIELD_NAME' => $this->ufFields['DEAL_COURSE'], 'ENTITY_ID' => 'CRM_DEAL']);
        if($arUF = $dbUserField->Fetch()) {
            $dbList = \CUserFieldEnum::GetList(array(), array("USER_FIELD_ID" => $arUF['ID']));
            while ($arValue = $dbList->Fetch()) {
                $result[] = [
                    'id' => $arValue['ID'],
                    'name' => $arValue['VALUE']
                ];
            }
        }
        return $result;
    }

    public function getGroupListAction()
    {
        $result = [];
        $this->initUserfields();
        $dbUserField = \CUserTypeEntity::GetList([],['FIELD_NAME' => $this->ufFields['DEAL_COURSE_GROUP'], 'ENTITY_ID' => 'CRM_DEAL']);
        if($arUF = $dbUserField->Fetch()) {
            $dbList = \CUserFieldEnum::GetList(array(), array("USER_FIELD_ID" => $arUF['ID']));
            while ($arValue = $dbList->Fetch()) {
                $result[] = [
                    'id' => $arValue['ID'],
                    'name' => $arValue['VALUE']
                ];
            }
        }
        return $result;
    }

    public function getPaymentsAction($categoryId)
    {
        $result = [];
        if($this->includeModules() && $this->checkRights()) {
            $this->initStages($categoryId);
            $this->initUserfields();
            $filter = $this->makeDealFilter($categoryId, true);
            $result = $this->getPaymentsData($filter);
        }
        return $result;
    }

    public function getPaymentsData($filter)
    {
        $result = [];

        $this->fetchDeals($filter);
        $this->fetchContacts();
        $this->fetchTasks();

        $pathToDeal = Option::get('crm', 'path_to_deal_details', '', SITE_ID);

        foreach($this->deals as $arDeal) {
            if (empty($arDeal['CONTACT_ID'])) {
                continue;
            }

            $arPayments = [];
            $fullPrice = 0;
            $currentPaid = 0;

            $arTasks = [];

            foreach($this->tasks as $arTask) {
                foreach($arTask['UF_CRM_TASK'] as $linkedValue) {
                    if($linkedValue == 'D_'.$arDeal['ID']) {
                        $arTasks[$arTask[$this->ufFields['TASK_SESSION']]][] = [
                            'id' => $arTask['ID'],
                            'completed' => !empty($arTask['CLOSED_DATE'])
                        ];
                        break;
                    }
                }
            }

            for($i = 1; $i <= 6; $i++) {
                if(!empty($arDeal[$this->ufFields['DEAL_PAY_SUM_'.$i]])) {
                    $sum = (int)explode('|', $arDeal[$this->ufFields['DEAL_PAY_SUM_'.$i]])[0];
                    $date = '';
                    if(!empty($arDeal[$this->ufFields['DEAL_PAY_DATE_'.$i]])) {
                        try {
                            $obDate = new \Bitrix\Main\Type\Date($arDeal[$this->ufFields['DEAL_PAY_DATE_' . $i]], 'd.m.Y');
                            $date = $obDate->format('Y-m-d');
                        } catch(\Exception $e) {

                        }
                    }

                    $theoryCompleted = true;
                    $practiceCompleted = true;

                    foreach($this->arStagesRef as $arStage) {
                        if($arStage['NAME'] == $i.' сессия') {
                            foreach($arTasks[$arStage['STATUS_ID']] as $task) {
                                if(!$task['completed']) {
                                    $practiceCompleted = false;
                                    break;
                                }
                            }
                        }
                        if($arStage['NAME'] == 'ПВ-'.$i) {
                            foreach($arTasks[$arStage['STATUS_ID']] as $task) {
                                if(!$task['completed']) {
                                    $theoryCompleted = false;
                                    break;
                                }
                            }
                        }
                    }

                    $arPayments[] = [
                        "name" => "Оплата ".$i,
                        "sum" => $sum,
                        "paid" => $arDeal[$this->ufFields['DEAL_PAY_PAID_'.$i]],
                        "payTill" => $date,
                        "theoryCompleted" => $theoryCompleted,
                        "practiceCompleted" => $practiceCompleted
                    ];
                    $fullPrice += $sum;
                    if($arDeal[$this->ufFields['DEAL_PAY_PAID_'.$i]]) {
                        $currentPaid += $sum;
                    }
                }
            }

            $name = $this->contacts[$arDeal['CONTACT_ID']]['LAST_NAME'].' '.$this->contacts[$arDeal['CONTACT_ID']]['NAME'];

            $result[] = [
                'name' => $name,
                'dealId' => $arDeal['ID'],
                'href' => \CComponentEngine::MakePathFromTemplate($pathToDeal, ["deal_id" => $arDeal["ID"]]),
                'contactId' => $arDeal['CONTACT_ID'],
                'payments' => $arPayments,
                'fullPrice' => $fullPrice,
                'currentPaid' => $currentPaid
            ];
        }

        return $result;
    }
}