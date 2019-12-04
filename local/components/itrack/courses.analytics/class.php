<?php

use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Config\Option;

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
    ];

    public function configureActions()
    {
        return [
            'getCourses' => [],
            'getCoursesList' => [],
            'getPayments' => []
        ];
    }

    public function onPrepareComponentParams($arParams)
    {
        if(!empty($arParams['CATEGORY_ID'])) {
            $arParams['CATEGORY_ID'] = (int)$arParams['CATEGORY_ID'];
        }
        $arParams['IS_PAYMENTS'] = !empty($arParams['TYPE']) && $arParams['TYPE'] == 'payments';
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
            $this->includeComponentTemplate();
        }
    }

    public function getCoursesAction($categoryId, $course = 0, $date = '')
    {
        $result = [];
        if($this->includeModules() && $this->checkRights()) {
            $this->initStages($categoryId);
            $this->initUserfields();
            $filter = $this->makeDealFilter($categoryId, $course, $date);
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
            'DEAL_PAY_PAID_2' => 'UF_CRM_1575378273',
            'DEAL_PAY_PAID_3' => 'UF_CRM_1575378308',
            'DEAL_PAY_PAID_4' => 'UF_CRM_1575378419',
            'DEAL_PAY_PAID_5' => 'UF_CRM_1575378439',
            'DEAL_PAY_PAID_6' => 'UF_CRM_1575378497'
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
            $result[] = [
                'name' => !empty($this->contacts[$arDeal['CONTACT_ID']]['SHORT_NAME'])
                    ? $this->contacts[$arDeal['CONTACT_ID']]['SHORT_NAME']
                    : $this->contacts[$arDeal['CONTACT_ID']]['FULL_NAME'],
                'dealId' => $arDeal['ID'],
                'contactId' => $arDeal['CONTACT_ID'],
                'sessions' => $arSessions,
                'countCompleted' => $countCompleted
            ];
        }

        return $result;
    }

    protected function makeDealFilter($categoryId, $course = 0, $date = '')
    {
        $arFilter = [
            'CATEGORY_ID' => $categoryId
        ];
        if(!empty($course)) {
            $arFilter[$this->ufFields['DEAL_COURSE']] = $course;
        }
        if(!empty($date) && $date !== 'null') {
            $arFilter['>=BEGINDATE'] = new Bitrix\Main\Type\Date($date, 'Y-m-d');
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
            []
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
            ->setSelect(['ID','SHORT_NAME','FULL_NAME'])
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
                ->setFilter(['UF_CRM_TASK' => $arIDs])
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

    public function getPaymentsAction($categoryId, $course = 0, $date = '')
    {
        $result = [];
        if($this->includeModules() && $this->checkRights()) {
            $this->initStages($categoryId);
            $this->initUserfields();
            $filter = $this->makeDealFilter($categoryId, $course, $date);
            $result = $this->getPaymentsData($filter);
        }
        return $result;
    }

    public function getPaymentsData($filter)
    {
        $result = [];

        $this->fetchDeals($filter);
        $this->fetchContacts();

        foreach($this->deals as $arDeal) {
            if (empty($arDeal['CONTACT_ID'])) {
                continue;
            }

            $arPayments = [];
            $fullPrice = 0;
            $currentPaid = 0;

            for($i = 1; $i <= 6; $i++) {
                if(!empty($arDeal[$this->ufFields['DEAL_PAY_SUM_'.$i]])) {
                    $arPayments[] = [
                        "name" => "Оплата ".$i,
                        "sum" => $arDeal[$this->ufFields['DEAL_PAY_SUM_'.$i]],
                        "paid" => $arDeal[$this->ufFields['DEAL_PAY_PAID_'.$i]],
                        "payTill" => !empty($arDeal[$this->ufFields['DEAL_PAY_DATE_'.$i]]) ? $arDeal[$this->ufFields['DEAL_PAY_DATE_'.$i]]->format('Y-m-d') : ''
                    ];
                    $fullPrice += (int)$arDeal[$this->ufFields['DEAL_PAY_SUM_'.$i]];
                    if($arDeal[$this->ufFields['DEAL_PAY_PAID_'.$i]]) {
                        $currentPaid += (int)$arDeal[$this->ufFields['DEAL_PAY_SUM_'.$i]];
                    }
                }
            }

            $result[] = [
                'name' => !empty($this->contacts[$arDeal['CONTACT_ID']]['SHORT_NAME'])
                    ? $this->contacts[$arDeal['CONTACT_ID']]['SHORT_NAME']
                    : $this->contacts[$arDeal['CONTACT_ID']]['FULL_NAME'],
                'dealId' => $arDeal['ID'],
                'contactId' => $arDeal['CONTACT_ID'],
                'payments' => $arPayments,
                'fullPrice' => $fullPrice,
                'currentPaid' => $currentPaid
            ];
        }

        return $result;
    }
}