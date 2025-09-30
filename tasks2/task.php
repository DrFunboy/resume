<?php
namespace employees;

use DB;
class task
{
    /**
     * Перечень переменных для подстановки в действия
     */
    const TASK_REPLACE_VARS = [
        'CommissionID' => 'ID события повышения комиссии',
        'ShowID' => 'ID показа',
    ];

    /** Условие постановки задачи 1 */
    const TASK_CONDITION_1 = 1;
    /** Условие постановки задачи 2 */
    const TASK_CONDITION_2 = 2;
    /** Условие постановки задачи 3 */
    const TASK_CONDITION_3 = 3;
    /** Условие постановки задачи 4 */
    const TASK_CONDITION_4 = 4;
    /** Условие постановки задачи 5 */
    const TASK_CONDITION_5 = 5;
    /** Условие постановки задачи 6 */
    const TASK_CONDITION_6 = 6;
    /** Условие постановки задачи 7 */
    const TASK_CONDITION_7 = 7;
    /** Условие постановки задачи 8 */
    const TASK_CONDITION_8 = 8;
    /** Условие постановки задачи 9 */
    const TASK_CONDITION_9 = 9;
    /** Условие постановки задачи 10 */
    const TASK_CONDITION_10 = 10;
    
    const TASK_CHECKLIST_NOT_COMPLETED_STATUS = 0;
    const TASK_CHECKLIST_COMPLETED_STATUS = 1;
    const TASK_CHECKLIST_BAD_RESULT = 1;
    const TASK_CHECKLIST_GOOD_RESULT = 10;
    const TASK_OPEN_RESULT_ID = 1;
    const TASK_COMPLETED_RESULT_ID = 2;

    /** Задача по работе с клиентом */
    const TASK_LINK_CLIENT = 1;
    /** Задача без связи с клиентом */
    const TASK_LINK_EMPLOYEE = 2;

    /** Создание задачи для сотрудника
     * @param array $params
     * - `TaskTypeID`
     * - `UserID`
     * - `Force` - Игнорировать, что у сотрудника уже есть эта задача
     * */
    public static function createTask($params = []){
        $taskTypeCond = DB::inCond('ett.TaskTypeID', $params['TaskTypeID']);
        $taskTypes = DB::getAssocs("
            SELECT
                ett.TaskTypeID,
                ett.ConditionID,
                ett.DeadlineMinute,
                ett.ConditionDate,
                ett.ClientHistoryDate,
                ett.NotifyCreate,
                ett.TaskTypeName,
                ett.ClientType,
                ett.ClientSource,
                ett.BudgetFrom,
                ett.BudgetTo,
                GROUP_CONCAT(ettp.PostID) as PostID,
                ett.LinkType
            FROM employee_task_type ett
            LEFT JOIN employee_task_type_post ettp ON ettp.TaskTypeID = ett.TaskTypeID
            WHERE 
                ett.Active = 1
                {$taskTypeCond}
            GROUP BY ett.TaskTypeID
        ");

        $taskTypesID = [];
        foreach ($taskTypes as $taskType) {
            $taskType['Items'] = [];
            $taskTypesID[$taskType['TaskTypeID']] = $taskType;
        }
        $taskTypesKeys = implode(',', array_keys($taskTypesID));
        if (empty($taskTypesID)) {
            return;
        }

        $taskItems = DB::getAssocs("
            SELECT ecli.ItemID, ecli.DeadlineMinute, ettcl.TaskTypeID, ecli.ActionName
            FROM employee_task_type_checklist ettcl
            INNER JOIN employee_checklist_item ecli ON ecli.ListID = ettcl.ListID
            WHERE ettcl.TaskTypeID IN ({$taskTypesKeys})
        ");

        foreach ($taskItems as $taskItem) {
            $taskTypesID[$taskItem['TaskTypeID']]['Items'][] = $taskItem;
        }


        $tgBOT = new \helpers\bots\TelegramBot();
        foreach ($taskTypesID as $taskType) {
            if (empty($taskType['Items'])){
                continue;
            }
            $clients = [];
            $userIDCond = DB::inCond('su.UserID', $params['UserID'], '');
            $forceCond = '';

            if ($taskType['LinkType'] == self::TASK_LINK_CLIENT) {
                $clhDate = $taskType['ClientHistoryDate'];
                $conditionDate = $taskType['ConditionDate']?: $clhDate;
                if (!$params['Force']) {
                    $forceCond = 'et.TaskID IS NULL';
                }

                $sql = [
                    'SELECT' => [
                        'ID' => 'clh.ID',
                        'ClientID' => 'clh.ClientID',
                        'UserID' => 'clh.UserID',
                        'DateStart' => 'NOW()',
                        'TimeFrom' => 'em.TimeFrom',
                        'TimeTill' => 'em.TimeTill',
                        'UserActive' => 'su.Active'
                    ],
                    'FROM' => 'clients_history clh',
                    'JOIN' => [
                        'cl' => ['INNER', 'clients cl', 'clh.ClientID = cl.ClientID'],
                        'et' => ['LEFT', 'employee_task et', "et.ClientHistoryID = clh.ID AND et.TaskTypeID = {$taskType['TaskTypeID']}"],
                        'su' => ['LEFT', 'sys_users su', 'su.UserID = clh.UserID'],
                        'em' => ['LEFT', 'employee em', 'em.UserID = su.UserID'],
                    ],
                    'WHERE' => [
                        'clh.Status = 1',
                        'clh.ArchiveReasonID = 0',
                        $forceCond,
                        "clh.DateStart >= '{$clhDate}'",
                        $userIDCond
                    ],
                    'GROUP BY' => ['clh.ID']
                ];


                if ($taskType['BudgetFrom'] || $taskType['BudgetTo']) {
                    $sql['WHERE'][] = DB::betweenCond('clh.AvgBudget', $taskType['BudgetFrom']*1000, $taskType['BudgetTo']*1000, '');
                }
                if ($taskType['ClientType']) {
                    $sql['WHERE'][] = "clh.PropertyTypeID = {$taskType['ClientType']}";
                }
                if ($taskType['ClientSource']) {
                    $sql['JOIN']['clsrc'] = ['INNER', 'calls_sources casrc', 'casrc.SourceID = clh.SourceID'];
                    $sql['WHERE'][] = "casrc.BaseGroupID = {$taskType['ClientSource']}";
                }
                if ($taskType['PostID']) {
                    $sql['JOIN']['em'] = ['INNER', 'employee em', 'em.UserID = clh.UserID'];
                    $sql['WHERE'][] = "em.BrokerPostID IN ({$taskType['PostID']})";
                }


                if ($taskType['ConditionID'] == self::TASK_CONDITION_1) {
                    $rent = \blocks::PROPERTY_RENT;
                    $sql['WHERE'][] = "clh.PropertyTypeID = {$rent}";
                    $clients = DB::getAssocs($sql);
                }

                else if ($taskType['ConditionID'] == self::TASK_CONDITION_2) {
                    $interviewActive = \settings::get('interviewActive');
                    if ($interviewActive) {
                        $sql['JOIN']['et'] = [
                            'LEFT',
                            'employee_task et',
                            "et.ClientHistoryID = clh.ID 
                            AND et.TaskTypeID = {$taskType['TaskTypeID']}
                            AND et.CreateDay >= CURDATE() - INTERVAL 3 MONTH"
                        ];
                        $sql['WHERE'][] = "clh.NeededInterview IS NOT NULL";
                        $sql['WHERE'][] = "clh.NeededInterview >= '{$conditionDate}'";
                        $sql['WHERE'][] = "cl.InterviewDate IS NULL";
                        $sql['SELECT']['DateStart'] = "CONCAT(clh.NeededInterview, ' ', DATE_FORMAT(NOW(), '%H:%i:%s'))";

                        $clients = DB::getAssocs($sql);
                    }
                }

                else if ($taskType['ConditionID'] == self::TASK_CONDITION_10) {
                    $sql['JOIN']['cli'] = [
                        'LEFT',
                        'clients_interview cli',
                        "cli.ClientID = cl.ClientID"
                    ];
                    $sql['WHERE'][] = "cli.InviteStatus = 2";
                    $sql['WHERE'][] = "cli.InviteSurveyLink = ''";
                    $sql['SELECT']['UserID'] = "cli.Broker";

                    $clients = DB::getAssocs($sql);

                }

                else if ($taskType['ConditionID'] == self::TASK_CONDITION_3){
                    // Выбирается клиент с типом аренда, у которого нет первого показа и работа с ним началась позже даты начала работы, но раньше чем 7 дней от текущей даты
                    $rent = \blocks::PROPERTY_RENT;
                    $sql['WHERE'][] = "clh.DateStart <= CURDATE() - INTERVAL 7 DAY";
                    $sql['WHERE'][] = "clh.PropertyTypeID = {$rent}";
                    $sql['WHERE'][] = "clh.FirstShowDate IS NULL";

                    $clients = DB::getAssocs($sql);
                }

                else if ($taskType['ConditionID'] == self::TASK_CONDITION_4){
                    // Выбирается клиент, у которого дата повторного показа позже даты условия или даты начала работы
                    $sql['WHERE'][] = "clh.FirstDoubleShowDate IS NOT NULL";
                    $sql['WHERE'][] = "clh.FirstDoubleShowDate >= '{$conditionDate}'";

                    $clients = DB::getAssocs($sql);
                }

                else if ($taskType['ConditionID'] == self::TASK_CONDITION_5){
                    // Выбирается клиент, у которого было событие Переговоры
                    $action = \engine::ACTION_TALKS;
                    $sql['JOIN']['la'] = [
                        'INNER',
                        'log_actions la',
                        "la.ClientHistoryID = clh.ID AND la.ActionID = {$action}"
                    ];

                    $clients = DB::getAssocs($sql);
                }

                else if ($taskType['ConditionID'] == self::TASK_CONDITION_6){
                    //Выбирается клиент, у которого дата показа позже даты условия или даты начала работы и в показе стоит галочка "Нужен ремонт"
                    $action = \engine::ACTION_SHOW;
                    $sql['JOIN']['la'] = [
                        'INNER',
                        'log_actions la',
                        "la.ClientHistoryID = clh.ID AND la.ActionID = {$action} AND la.DayDate >= '{$conditionDate}'"
                    ];

                    $sql['JOIN']['las'] = [
                        'INNER',
                        'log_actions_shows las',
                        "las.RecordID = la.RecordID AND las.NeedRepair = 1"
                    ];

                    unset($sql['JOIN']['et']); //Нужно, что бы таблица log_actions джоинилась до employee_task
                    $sql['JOIN']['et'] = [
                        'LEFT',
                        'employee_task et',
                        "et.ClientHistoryID = clh.ID 
                    AND et.BlockID = la.BlockID
                    AND et.TaskTypeID = {$taskType['TaskTypeID']}"
                    ];

                    $sql['SELECT']['BlockID'] = 'la.BlockID';
                    $sql['GROUP BY'] = ['clh.ID', 'la.BlockID'];

                    $clients = DB::getAssocs($sql);
                }

                else if ($taskType['ConditionID'] == self::TASK_CONDITION_7){
                    //Есть событие "повышение комиссии", но после него нет показа либо он еще не состоялся
                    $actionShow = \engine::ACTION_SHOW;
                    $actionComm = \engine::ACTION_COMMISSION_INCREASE;

                    $sql['JOIN']['la'] = [
                        'INNER',
                        'log_actions la',
                        "la.ClientHistoryID = clh.ID AND la.ActionID = {$actionComm} AND la.DayDate >= '{$conditionDate}'"
                    ];

                    $sql['JOIN']['las'] = [
                        'LEFT',
                        'log_actions las',
                        "
                        las.ClientHistoryID = clh.ID 
                        AND las.ActionID = {$actionShow} 
                        AND las.Date >= unix_timestamp(la.CreateDate)
                    "
                    ];

                    $sql['WHERE'][] = 'COALESCE(las.Date, NOW()) >= NOW()';

                    $clients = DB::getAssocs($sql);
                }

                else if ( in_array($taskType['ConditionID'], [self::TASK_CONDITION_8, self::TASK_CONDITION_9])){
                    //Есть событие "повышение комиссии" и новый процент в log_action_commission_increase равен или не равен нулю
                    $actionShow = \engine::ACTION_SHOW;
                    $actionComm = \engine::ACTION_COMMISSION_INCREASE;

                    $sql['JOIN']['la'] = [
                        'INNER',
                        'log_actions la',
                        "la.ClientHistoryID = clh.ID AND la.ActionID = {$actionComm} AND la.DayDate >= '{$conditionDate}'"
                    ];

                    $sql['JOIN']['laci'] = [
                        'INNER',
                        'log_action_commission_increase laci',
                        'laci.RecordID = la.RecordID'
                    ];

                    $sql['JOIN']['las'] = [
                        'INNER',
                        'log_actions las',
                        "
                        las.ClientHistoryID = clh.ID 
                        AND las.ActionID = {$actionShow} 
                        AND las.Date >= unix_timestamp(la.CreateDate)
                    "
                    ];

                    $sql['SELECT']['BlockID'] = 'la.BlockID';
                    $sql['SELECT']['CommissionID'] = 'laci.RecordID';
                    $sql['SELECT']['ShowID'] = "SUBSTRING_INDEX(GROUP_CONCAT(las.RecordID ORDER BY las.Date DESC), ',', 1)";

                    $sql['GROUP BY'] = ['clh.ID', 'la.BlockID'];

                    if ($taskType['ConditionID'] == self::TASK_CONDITION_8) {
                        $sql['WHERE'][] = 'laci.NewPercent = 0';
                    }
                    else {
                        $sql['JOIN']['emd'] = [
                            'INNER',
                            'employee_departments emd',
                            'emd.ID = em.DepartmentID'
                        ];

                        $sql['WHERE'][] = 'laci.NewPercent != 0';
                        $sql['SELECT']['UserID'] = 'COALESCE(emd.BossID, emd.BossID2)';
                    }


                    $clients = DB::getAssocs($sql);
                }
            }
            elseif ($taskType['LinkType'] == self::TASK_LINK_EMPLOYEE) {
                if (empty($taskType['PostID']) || $taskType['ConditionDate'] > \e::nowDate() ) {
                    continue;
                }


                $sql = [
                    'SELECT' => [
                        'su.UserID',
                        'NOW() as DateStart',
                        'TimeFrom' => 'em.TimeFrom',
                        'TimeTill' => 'em.TimeTill',
                    ],
                    'FROM' => 'employee_task_type ett',
                    'JOIN' => [
                        'em' => ['INNER', 'employee em', "em.BrokerPostID IN ({$taskType['PostID']}) AND COALESCE(em.LastWorkDay, CURDATE()) >= CURDATE()"],
                        'su' => ['INNER', 'sys_users su', 'em.UserID = su.UserID AND su.Active = 1'],
                        'et' => ['LEFT', 'employee_task et', "et.TaskTypeID = ett.TaskTypeID"],
                        'etu' => ['LEFT', 'employee_task_user etu', "etu.TaskID = et.TaskID"],
                    ],
                    'WHERE' => [
                        'ett.TaskTypeID' => $taskType['TaskTypeID']
                    ],
                    'GROUP BY' => ['su.UserID'],
                ];

                if (!$params['Force']) {
                    $sql['WHERE'][] = 'etu.ID IS NULL';
                }
                elseif($params['UserID']) {
                    $userIDCond = DB::inCond('em.UserID', $params['UserID'], '');
                    $sql['JOIN']['em'] = ['INNER', 'employee em', $userIDCond];
                }

                $clients = DB::getAssocs($sql);
            }

            if (!empty($clients)){
                foreach ($clients as $client) {
                    $deadlineTime = strtotime("{$client['DateStart']} + {$taskType['DeadlineMinute']} minute");
                    $insertedTask = DB::insertByTableName('employee_task', [
                        'TaskTypeID' => $taskType['TaskTypeID'],
                        'ConditionID' => $taskType['ConditionID'],
                        'DateStart' => $client['DateStart'],
                        'DateTimeStart' => $client['DateStart'],
                        'DateEnd' => date('c', $deadlineTime),
                        'DateTimeEnd' => date('c', $deadlineTime),
                        'ClientID' => $client['ClientID']?: 0,
                        'ClientHistoryID' => $client['ID']?: 0,
                        'CreateDay' => date('c'),
                        'CreateTime' => date('c'),
                        'BlockID' => $client['BlockID']?: 0,
                    ]);

                    DB::insertByTableName('employee_task_user', [
                        'TaskID' => $insertedTask,
                        'UserID' => $client['UserID'],
                        'RoleID' => 1
                    ]);

                    if ($insertedTask) {
                        foreach ($taskType['Items'] as $item) {
                            $itemDateEnd = date('c', strtotime("{$client['DateStart']} + {$item['DeadlineMinute']} minute"));

                            foreach (self::TASK_REPLACE_VARS as $var => $value) {
                                $item['ActionName'] = str_replace("@$var;", $client[$var], $item['ActionName']);
                            }

                            DB::insertByTableName([
                                'employee_task_checklist_status',
                                ['fieldsParams' => [
                                    'Description' => ['type' => 'html']
                                ]]
                            ], [
                                'TaskID' => $insertedTask,
                                'ItemID' => $item['ItemID'],
                                'Description' => $item['ActionName'],
                                'DateStart' => $client['DateStart'],
                                'DateTimeStart' => $client['DateStart'],
                                'DateEnd' => $itemDateEnd,
                                'DateTimeEnd' => $itemDateEnd
                            ]);
                        }

                        // TODO Рассчитывать необходимость отправки уведомлений на основе `employee_task_user_role.NotifyCreate`
                        // Отправка уведомления о созданной задаче
                        if ($taskType['NotifyCreate'] == 1 && !empty($client['UserID'])) {
                            // Проверка, что сотрудник сейчас работает
                            $isJobDay = \common::isJobDay(time(), $client['UserID']);
                            $isJobTime = time() >= strtotime($client['TimeFrom']) && time() <= strtotime($client['TimeTill']);
                            if (!$client['UserActive']) $isJobDay = false;

                            if ($isJobDay && $isJobTime) {
                                $arButtons = [['url' => \Config::get('fortexErpDomain').'/task_employee/select', 'text' => 'Подробнее']];

                                $deadline = date('d.m.Y H:i', $deadlineTime);
                                $text = "Добавлена задача - {$taskType['TaskTypeName']} \nСрок выполнения - {$deadline}";
                                $tgBOT->sendMessage(1, $client['UserID'], $text, [], $arButtons);
                            }
                        }
                    }
                }
            }
        }
    }

    /** Напоминания о задачах с истекающим сроком выполнения */
    public static function sendNotification(){
        $users = DB::getAssocs("
            SELECT etu.UserID, COUNT(et.TaskID) AS TaskCnt
            FROM employee_task et
            INNER JOIN employee_task_user etu 
                ON etu.TaskID = et.TaskID
            INNER JOIN employee_task_user_role etur 
                ON etur.RoleID = etu.RoleID
            INNER JOIN employee_task_type ett 
                ON ett.TaskTypeID = et.TaskTypeID
                AND ett.NotifyDeadline = 1
            INNER JOIN sys_users su 
                ON su.UserID = etu.UserID
                AND su.Active = 1 
            WHERE 
                et.ResultID = 1
                AND et.DateStart != et.DateEnd
                AND et.DateEnd = CURDATE()
                AND etur.NotifyDeadline = 1
            GROUP BY etu.UserID
        ");

        $tgBOT = new \helpers\bots\TelegramBot();
        foreach ($users as $user) {
            $userID = $user['UserID'];
            // Если не рабочий день, то ничего не отправлять
            $isJobDay = \common::isJobDay(time(), $userID);
            if (!$isJobDay) continue;

            $text = "Задач истекающих сегодня - {$user['TaskCnt']}";
            $today = strtotime('today');
            $url = \Config::get('fortexErpDomain')."/task_employee/select?minDateEnd={$today}&maxDateEnd={$today}";
            $arButtons = [['url' => $url, 'text' => 'Подробнее']];
            $tgBOT->sendMessage(1, $userID, $text, [], $arButtons);
        }
    }
}