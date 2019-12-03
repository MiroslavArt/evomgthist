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
        'TASK_SESSION' => ''
    ];

    public function configureActions()
    {
        return [
            'getCourses' => [],
            'getCoursesList' => []
        ];
    }

    public function onPrepareComponentParams($arParams)
    {
        if(!empty($arParams['CATEGORY_ID'])) {
            $arParams['CATEGORY_ID'] = (int)$arParams['CATEGORY_ID'];
        }
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

            /*$result = [
                [
                    "name" => "Дарья Б.",
                    "dealId" => 15736,
                    "sessions" => [
                        [
                            "name" => "Сессия 1",
                            "sections" => [
                                [
                                    "name" => "Урок первый",
                                    "tasks" => [
                                        [
                                            "id" => 167654,
                                            "completeTill" => "2019-12-21",
                                            "completed" => false,
                                            'url' => '/company/personal/user/53/tasks/task/view/5855/'
                                        ],
                                        [
                                            "id" => 167484,
                                            "completeTill" => "2019-10-30",
                                            "completed" => true,
                                            'url' => '/company/personal/user/53/tasks/task/view/5855/'
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        [
                            "name" => "Сессия 33",
                            "sections" => [
                                [
                                    "name" => "Урок первый",
                                    "tasks" => [
                                        [
                                            "id" => 167654,
                                            "completeTill" => "2019-12-21",
                                            "completed" => false,
                                            'url' => '/company/personal/user/53/tasks/task/view/5855/'
                                        ],
                                        [
                                            "id" => 167484,
                                            "completeTill" => "2019-10-30",
                                            "completed" => true,
                                            'url' => '/company/personal/user/53/tasks/task/view/5855/'
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    "name" => "Роман К.",
                    "dealId" => 15736,
                    "sessions" => [
                        [
                            "name" => "Сессия 1",
                            "sections" => [
                                [
                                    "name" => "Урок первый",
                                    "tasks" => [
                                        [
                                            "id" => 167654,
                                            "completeTill" => "2019-12-21",
                                            "completed" => false,
                                            'url' => '/company/personal/user/53/tasks/task/view/5855/'
                                        ],
                                        [
                                            "id" => 167484,
                                            "completeTill" => "2019-10-30",
                                            "completed" => false,
                                            'url' => '/company/personal/user/53/tasks/task/view/5855/'
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        [
                            "name" => "Сессия 2",
                            "sections" => [
                                [
                                    "name" => "Урок первый",
                                    "tasks" => [
                                        [
                                            "id" => 167654,
                                            "completeTill" => "2019-12-21",
                                            "completed" => false,
                                            'url' => '/company/personal/user/53/tasks/task/view/5855/'
                                        ],
                                        [
                                            "id" => 167484,
                                            "completeTill" => "2019-10-30",
                                            "completed" => false,
                                            'url' => '/company/personal/user/53/tasks/task/view/5855/'
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ];*/
        }
        return $result;
    }

    protected function initUserfields()
    {
        $this->ufFields = [
            'DEAL_COURSE' => 'UF_CRM_1573215888', // TODO: get automaticaly
            'TASK_SESSION' => 'UF_SESSION'
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
            $arSessions = [];
            foreach($this->stages as $arStage) {
                $arSections = [];

                foreach($this->tasks as $arTask) {
                    if($arTask['UF_CRM_TASK'] == $arDeal['ID'] && $arTask[$this->ufFields['TASK_SESSION']] == $arStage['STATUS_ID']) {
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
                    }
                }

                $arSessions[] = [
                    'id' => $arStage['STATUS_ID'],
                    'name' => $this->arStagesRef[$arStage['ID']],
                    'sections' => $arSections
                ];
            }
            $result[] = [
                'name' => $this->contacts[$arDeal['CONTACT_ID']],
                'dealId' => $arDeal['ID'],
                'contactId' => $arDeal['CONTACT_ID'],
                'sessions' => $arSessions
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
    }

    protected function fetchDeals($filter)
    {
        $dbDeals = \CCrmDeal::GetListEx(
            [],
            $filter,
            false,
            false,
            ['ID','CONTACT_ID','STAGE_ID']
        );
        while($arDeal = $dbDeals->Fetch()) {
            $this->deals[$arDeal['ID']] = $arDeal;
        }
    }

    protected function fetchContacts()
    {
        $cID = [];
        foreach ($this->deals as $arDeal) {
            $cID[] = $arDeal['CONTACT_ID'];
        }
        $dbContacts = \Bitrix\Crm\ContactTable::query()
            ->setFilter(['=ID' => $cID])
            ->setSelect(['ID','SHORT_NAME'])
            ->exec();
        while($arContact = $dbContacts->fetch()) {
            $this->contacts[$arContact['ID']] = $arContact;
        }
    }

    protected function fetchTasks()
    {
        $dbTasks = \Bitrix\Tasks\TaskTable::query()
            ->setFilter(['UF_CRM_TASK' => array_keys($this->deals)])
            ->setSelect(['ID', 'TITLE', 'UF_CRM_TASK', $this->ufFields['TASK_SESSION'], 'DEADLINE_COUNTED','DEADLINE','CLOSED_DATE'])
            ->exec();
        while($arTask = $dbTasks->fetch()) {
            $this->tasks[$arTask['ID']] = $arTask;
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
        /*$result = [
            [
                "id" => 1,
                "name" => "Курс 1_"
            ],
            [
                "id" => 2,
                "name" => "Курс 2_"
            ],
            [
                "id" => 3,
                "name" => "Курс 3_"
            ]
        ];*/
        return $result;
    }
}