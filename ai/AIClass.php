<?php

class AI
{
    const GPT = 'gpt-4o';
    const GPT_mini = 'gpt-4o-mini';
    const Gemini = 'gemini-2.0-flash';
    const Gemini_exp = 'gemini-2.0-flash-exp';
    const Gemini_lite = 'gemini-2.0-flash-lite';
    const Gemini_pro_exp = 'gemini-2.0-pro-exp-02-05';
    const DeepSeek = 'deepseek-chat';
    const DeepSeek_R1 = 'deepseek-reasoner';
    const YandexGPT = 'yandexgpt';
    const YandexGPT_lite = 'yandexgpt-lite';
    const MaxTries = 15;
    const CONTEXT_REPLACE_VARS = [
        'VarName1' => 'Название перменной 1',
        'VarName2' => 'Название перменной 2',
        'VarName3' => 'Название перменной 3',
        'VarName4' => 'Название перменной 4',
        'VarName5' => 'Название перменной 5',

    ];

    const CallsToText_LMM = AI::DeepSeek;

    /**
     * Помещает запрос к ИИ в очередь
     * @param string    $prompt Вопрос, который задается ИИ
     * @param string    $llm Языковая модель
     * @param array     $params
     * - Callback       - Метод, который будет вызван при выполнении запроса.
     * - CallbackData   - Данные, которые будут переданы в метод.
     * - Context        - Контекст запроса в виде массива `[['role' => ..., 'content' => ...]]`.
     * - GroupUID       - Идентификатор групп для составных запросов.
     * - TaskID         - id задачи ai_tasks
     * - ParentTypeID   - тип родителя
     * - ParentID       - id родителя
     * - CallType       - Тип звонка из ip_calls_type
     * - UserID         - Сотрудник
     * - DateStart      - Дата на которую создавать запись в log_ai_request
     * @return array ID в очереди или ошибка
     * */
    public static function saveRequest($prompt, $llm, $params = []){
        $callback = $params['Callback']?: '';
        $callbackData = $params['CallbackData']?: [];
        $context = $params['Context']? json_encode($params['Context']): '';
        $groupUID = $params['GroupUID']?: '';
        $taskID = $params['TaskID']?: 0;
        $parentTypeID = $params['ParentTypeID']?: 0;
        $parentID = $params['ParentID']?: 0;
        $callType = $params['CallType']?: 0;
        $userID = $params['UserID']?: 0;
        $vars = $params['Vars']?: '';
        $dateStart = $params['DateStart']?: e::nowDate();


        $llmData = DB::getAssoc("
            SELECT ID, MaxInputToken
            FROM ai_models
            WHERE Alias = '{$llm}'
        ");
        $tokenUsage = self::calculatePromptTokens($prompt . $context);
        if (in_array($llm, [self::GPT, self::GPT_mini])){
            // ChatGPT использует вдвое больше токенов чем прочие ИИ
            $tokenUsage *= 2;
        }

        if ($tokenUsage > $llmData['MaxInputToken']) {
            return ['error' => "Запрос слишком большой. Токенов использовано - {$tokenUsage}, токенов доступно - {$llmData['MaxInputToken']}"];
        }
        $logHash = md5($llm . $prompt . $context . $groupUID . $taskID);

        $maxTries = self::MaxTries;
        $logExist = \DB::getItem("
            SELECT LogID
            FROM log_ai_request
            WHERE 
                LogHash = '{$logHash}'
                AND ReqCount <= '{$maxTries}'
        ");
        if ($logExist) {
            return ['error' => "Запрос № {$logExist} уже существует и ждет своей очереди"];
        }

        $logID = \DB::insertByTableName('log_ai_request', [
            'DateStart' => $dateStart,
            'LMM' => $llmData['ID'],
            'Question' => $prompt,
            'LogContext' => $context,
            'Callback' => $callback,
            'LogHash' => $logHash,
            'GroupUID' => $groupUID,
            'CallbackData' => $callbackData? json_encode($callbackData) : '',
            'TaskID' => $taskID,
            'ParentID' => $parentID,
            'ParentTypeID' => $parentTypeID,
            'CallType' => $callType,
            'UserID' => $userID,
            'Vars' => $vars
        ]);
        return ['ID' => $logID];
    }

    /**
     * Рассчитывает дату следующей отправки запроса к ИИ
     * @param int $count Кол-во совершенных попыток отправки запроса
     * @return string Следующая дата отправки в формате Y-m-d H:i:s
     */
    public static function calculateNewDateStart($count = 1){
        if ($count >= self::MaxTries - 1) {
            $nextDateStart = '+24 hours';
        }
        elseif ($count <= 5) {
            $nextDateStart = '+2 minutes';
        }
        elseif ($count <= 10) {
            $nextDateStart = '+10 minutes';
        }
        else {
            $nextDateStart = '+1 hour';
        }
        return date('Y-m-d H:i:s', strtotime($nextDateStart));
    }


    /**
     * Выполняет запрос к ИИ и возвращает ответ, при необходимости отправляет вебхук
     * @param int $logID Идентификатор запроса из log_ai_request
     * @return array Ответ на вопрос или ошибку
     * */
    public static function execRequest($logID, $force = false)
    {
        $logData = DB::getAssoc("
            SELECT 
                LogContext,
                Question,
                Callback,
                ai_models.Alias,
                ReqCount,
                ReqStatus,
                Answer,
                DateStart
            FROM log_ai_request 
            INNER JOIN ai_models ON ai_models.ID = log_ai_request.LMM
            WHERE LogID = '{$logID}'
        ");
        $reqCount = $logData['ReqCount']+1;

        if (empty($logData))
		{
            return ['error' => "Лог {$logID} не найден"];
        }
        elseif ($logData['ReqStatus'] == 1 && strtotime($logData['DateStart']) > strtotime('-5 minutes'))
        {
            // Запрос уже в обработке
            return ['error' => 'Запрос уже выполняется'];
        }
        elseif ($logData['ReqStatus'] == 2 )
        {
            return ['answer' => $logData['Answer']];
        }
        elseif ($logData['ReqCount'] > self::MaxTries && !$force)
        {
            //Превышен лимит попыток
            return ['error' => 'Превышен лимит попыток'];
        }
        elseif ($logData['ReqStatus'] == 4 && $logData['Answer'])
        {
            // Если не получен ответ от вебхука
            \DB::updateByTableName(
                'log_ai_request',
                [
                    'ReqStatus' => 1,
                    'ReqCount' => $reqCount,
                    'DateStart' => date('c'),
                ],
                ['LogID' => $logID]
            );

	        $cbSuccess = false;
			if ($logData['Callback'] && is_callable($logData['Callback']))
			{
				$cbResult = call_user_func_array($logData['Callback'], [ ['LogID' => $logID] ]);
				$cbSuccess = $cbResult['success'];
			}

            if ($cbSuccess){
                $updateArray = ['ReqStatus' => 2];
            } else {
                $updateArray = [
                    'ReqStatus' => 4,
                    'DateStart' => self::calculateNewDateStart($reqCount)
                ];
            }

	        \DB::updateByTableName(
		        'log_ai_request',
                $updateArray,
		        ['LogID' => $logID]
	        );
            return ['answer' => $logData['Answer']];
        }


        \DB::updateByTableName(
            'log_ai_request',
            [
                'ReqStatus' => 1,
                'ReqCount' => $reqCount,
                'DateStart' => date('c'),
            ],
            ['LogID' => $logID]
        );

        $prompt = $logData['Question'];
        $llm = $logData['Alias'];
        $logStatus = 0;
        $context = json_decode($logData['LogContext'], true);
        if (empty($context)) {
            $context = [[
                'role' => 'system',
                'content' => 'Ты помощник агентства, занимающегося арендой и продажей офисов в Москве.'
            ]];
        }

        $browser = new \core\browser();
        if (in_array($llm, [self::GPT, self::GPT_mini]))
		{
            $messages = $context;
            $messages[] = [
                'role' => 'user',
                'content' => $prompt
            ];

            $apiKey = AI_APIKEY_GPT;
            $apiUrl = 'https://api.openai.com/v1/chat/completions';
            $sourceAnswer = $browser->getCurlContent([
                'timeout' => 300,
                'headers' => [
                    "Authorization: Bearer {$apiKey}",
                    'Content-Type: application/json'
                ],
                'url' => $apiUrl,
                'post' => json_encode([
                    'model' => $llm,
                    'messages' => $messages
                ])
            ]);
            $result = json_decode($sourceAnswer, true);
            $tokens = $result['usage']['totalTokens']?: 0;
            $answerText = $result['choices'][0]['message']['content'];
        }
        elseif (in_array($llm, [self::Gemini, self::Gemini_exp, self::Gemini_lite, self::Gemini_pro_exp]))
        {
            $messages = [];
            $systemInstruction = [];
            foreach ($context as $msg){
                if ($msg['role'] == 'system'){
                    $systemInstruction[] = ['text' => $msg['content']];
                    continue;
                }
                $messages[] = [
                    'role' => $msg['role'],
                    'parts' => [
                        'text' => $msg['content']
                    ]
                ];
            }

            $messages[] = [
                'role' => 'user',
                'parts' => [
                    'text' => $prompt
                ]
            ];
            $postData = ['contents' => $messages];
            if (!empty($systemInstruction)){
                $postData['system_instruction'] = ['parts' => $systemInstruction];
            }

            $apiKey = AI_APIKEY_GEMINI;
            $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$llm}:generateContent?key={$apiKey}";
            $sourceAnswer = $browser->getCurlContent([
                'timeout' => 300,
                'headers' => [
                    "Content-Type: application/json"
                ],
                'url' => $apiUrl,
                'post' => json_encode($postData)
            ]);
            $result = json_decode($sourceAnswer, true);
            $tokens = $result['usageMetadata']['totalTokenCount']?: 0;
            $answerText = $result['candidates'][0]['content']['parts'][0]['text'];
        }
        elseif (in_array($llm, [self::DeepSeek, self::DeepSeek_R1]))
        {
            $messages = $context;
            $messages[] = [
                'role' => 'user',
                'content' => $prompt
            ];

            $apiKey = AI_APIKEY_DEEPSEEK;
            $apiUrl = 'https://api.deepseek.com/chat/completions';
            $sourceAnswer = $browser->getCurlContent([
                'timeout' => 300,
                'headers' => [
                    "Authorization: Bearer {$apiKey}",
                    'Content-Type: application/json'
                ],
                'url' => $apiUrl,
                'post' => json_encode([
                    'model' => $llm,
                    'messages' => $messages,
                    'stream' => false
                ])
            ]);
            $result = json_decode($sourceAnswer, true);
            $tokens = $result['usage']['total_tokens']?: 0;
            $answerText = $result['choices'][0]['message']['content'];
        }
        elseif (in_array($llm, [self::YandexGPT, self::YandexGPT_lite]))
        {
            $messages = [];
            foreach ($context as $msg){
                $messages[] = [
                    'role' => $msg['role'],
                    'text' => $msg['content']
                ];
            }
            $messages[] = [
                'role' => 'user',
                'text' => $prompt
            ];

            $apiKey = AI_APIKEY_YANDEXGPT;
            $folderID = AI_APIKEY_YANDEX_FOLDER_ID;
            $apiUrl = 'https://llm.api.cloud.yandex.net/foundationModels/v1/completion';
            $sourceAnswer = $browser->getCurlContent([
                'timeout' => 300,
                'headers' => [
                    "Authorization: Api-Key {$apiKey}",
                    'Content-Type: application/json'
                ],
                'url' => $apiUrl,
                'post' => json_encode([
                    'modelUri' => "gpt://{$folderID}/{$llm}",
                    'completionOptions' => [
                        'stream' => false,
                    ],
                    'messages' => $messages
                ])
            ]);
            $result = json_decode($sourceAnswer, true)['result'];
            $tokens = $result['usage']['totalTokens']?: 0;
            $answerText = $result['alternatives'][0]['message']['text'];
        }
        else
		{
            return ['error' => 'Языковая модель не найдена'];
        }

        if ($answerText)
		{
            $result =  ['answer' => $answerText];
            $logStatus = 2;
        }
        elseif ($sourceAnswer)
        {
            $result =  ['error' => $sourceAnswer];
        }

        $updateArray = [
            'SourceAnswer' => $sourceAnswer,
            'Answer' => $answerText,
            'DateEnd' => date('c'),
            'Tokens' => $tokens
        ];

        if (empty($result) || $result['error'])
		{
            $logStatus = 3;
            $updateArray['DateStart'] = self::calculateNewDateStart($reqCount);
        }

        $updateArray['ReqStatus'] = $logStatus;

        \DB::updateByTableName(
            'log_ai_request',
            $updateArray,
            ['LogID' => $logID]
        );

        if ($answerText && $logData['Callback'])
		{
			$cbSuccess = false;
			if(is_callable($logData['Callback']))
			{
				$cbResult = call_user_func_array($logData['Callback'], [ ['LogID' => $logID] ]);
				$cbSuccess = $cbResult['success'];
			}
            \DB::updateByTableName(
                'log_ai_request',
                ['ReqStatus' => $cbSuccess ? 2 : 4],
                ['LogID' => $logID]
            );
        }

        return $result;
    }


    /**
     * Проверяет не превышает ли запрос максимально допустимое количество токенов
     * @param $text
     * @return int
     */
    public static function calculatePromptTokens($text){
        $charCount = mb_strlen($text, 'UTF-8');
        $wordCount = count(explode(' ', $text));
        $tokenEstimateByChars = $charCount / 4;
        $tokenEstimateByWords = $wordCount * 0.75;
        return intval(($tokenEstimateByChars + $tokenEstimateByWords) / 2);
    }


    /**
     * Помещает запрос на оценку качества звонка в очередь
     * @param int $IPCallID Колонка в таблице `ip_calls_stat.CallID`
     * @return array
     * */
    public static function startQualityControl($IPCallID)
    {
        $callData = DB::getAssoc("
            SELECT
                calls_to_text.Text,
                icp.CallType,
                ics.DateDay
            FROM ip_calls_stat ics
            INNER JOIN calls_to_text ON ics.CallID = calls_to_text.IPCallID
            INNER JOIN ip_calls_params icp ON icp.IPCallID = ics.CallID
            WHERE 
                ics.CallID = '{$IPCallID}'
                AND calls_to_text.Text != ''
                AND calls_to_text.BadResult = 0");

        if (empty($callData))
		{
            return ['error' => "Звонок не имеет транскрибации или не существует"];
        }

        $tasks = DB::getAssocs("
            SELECT
                tqct.TaskID,
                ai_tasks.TaskName,
                ai_tasks.Prompt,
                aip.PromptText as ContextPrompt,
                GROUP_CONCAT(tqct.CallTypeID) as CallType
            FROM ai_tasks_qc_calls_types tqct
            INNER JOIN ip_calls_type ict ON ict.CallType = tqct.CallTypeID
            INNER JOIN ai_tasks ON tqct.taskID = ai_tasks.TaskID
                AND ai_tasks.Active = 1
                AND ai_tasks.TypeID = 1
                AND ai_tasks.DateStart <= NOW() 
                AND (ai_tasks.DateEnd >= NOW() OR ai_tasks.DateEnd IS NULL)
            LEFT JOIN ai_prompts aip ON aip.PromptID = ai_tasks.PromptID
            WHERE 
                ict.CallType = {$callData['CallType']}
                OR ict.ParentType = {$callData['CallType']}
        GROUP BY tqct.TaskID
        ");

        if (empty($tasks)){
            return ['error' => "Для этого типа звонка не найдено подходящей задачи"];
        }

        $callTypesIDs = [];
        $byTaskID = [];
        foreach ($tasks as $task) {
            $task['callTypes'] = explode(',', $task['CallType']);
            $callTypesIDs = array_merge($callTypesIDs, $task['callTypes']);
            $byTaskID[$task['TaskID']] = $task;
        }

        $callTypesIDs = implode(',', array_unique($callTypesIDs));
        $callTypesData = DB::getAssocs("
            SELECT
                CallType as ID,
                Comment,
                Posts
            FROM ip_calls_type
            WHERE callType IN ({$callTypesIDs})
        ");
        $callTypesKeys = [];
        foreach ($callTypesData as $callType){
            $callTypesKeys[$callType['ID']] = $callType;
        }

        $tasksIDs = implode(',', array_keys($byTaskID));

        $models = DB::getAssocs("
            SELECT 
                TaskID,
                ai_models.Alias
            FROM ai_tasks_ai_models taim
            INNER JOIN ai_models ON ai_models.ID = taim.AIModelID
            WHERE TaskID IN ($tasksIDs)
        ");
        if (empty($models)){
            return ['error' => 'Для этого типа звонка не указаны модели ИИ'];
        }
        foreach ($models as $model) {
            $byTaskID[$model['TaskID']]['models'][] = $model['Alias'];
        }

        $questions = DB::getAssocs("
            SELECT
                TaskID,
                ai_q.QuestionKey,
                ai_q.QuestionText
            FROM ai_tasks_questions aitq
            INNER JOIN ai_questions ai_q ON aitq.QuestionID = ai_q.ID
            WHERE TaskID IN ($tasksIDs)
        ");
        if (empty($questions)){
            return ['error' => 'Для этого типа звонка нет вопросов'];
        }
        foreach ($questions as $question) {
            $byTaskID[$question['TaskID']]['questions'][] = [
                'key' => $question['QuestionKey'],
                'text' => $question['QuestionText'],
            ];
        }

        $existTasks = DB::getAssocs("
            SELECT 
                lair.TaskID,
                lair.CallType,
                aim.Alias
            FROM log_ai_request lair
            LEFT JOIN ai_models aim ON aim.ID = lair.LMM
            WHERE 
                lair.ParentTypeID = 1
                AND lair.ParentID = $IPCallID
                AND lair.TaskID IN ($tasksIDs)
        ");

        $results = [];
        foreach ($byTaskID as $taskID => $taskData) {
            $contextPrompt = $taskData['ContextPrompt'];
            if (!empty($contextPrompt)){
                $contextPrompt = [[
                    'role' => 'system',
                    'content' => $contextPrompt
                ]];
            }

            $prompt = $taskData['Prompt']?: '';
            if (!empty($prompt)){
                $prompt .= "\n";
            }

            $prompt .= "Проанализируй следующий диалог между сотрудником и клиентом, в котором канал 1 - это сотрудник, а канал 2 - клиент. Оцени ответы сотрудника на основе следующих вопросов и представь результат в виде чистого JSON. Если вопрос касается сотрудника, то оцениваем только его фразы! Для каждого ответа на вопрос нужна такая структура:
{
    \"questionKey\": идентификатор вопроса,
    \"score\": оценка ответа на вопрос по 10-балльной шкале (где 1 — очень плохо, 10 — отлично),
    \"recommendation\": рекомендации по улучшению, с коротким примером (до 255 символов),
    \"fact\": укажи к какой фразе относится рекомендация (до 255 символов)
}
Не оценивай вопросы, которые не подходят для данного разговора, таким вопросам ставь оценку 0. Не заполняй \"recommendation\" и \"fact\", если оценка 0, 8, 9 или 10. ".PHP_EOL.PHP_EOL;
            foreach ($taskData['questions'] as $question) {
                $prompt .= "QuestionKey: {$question['key']} - {$question['text']}".PHP_EOL;
            }
            $prompt = $prompt.PHP_EOL.'Диалог:'.PHP_EOL;

            $taskConditions = DB::getAssocs("
                SELECT ac.Condition
                FROM ai_tasks_conditions atc
                JOIN ai_conditions ac ON ac.ID = atc.ConditionID
                WHERE atc.TaskID = $taskID
            ");
            $conditions = '';
            foreach ($taskConditions as $condition){
                $conditions .= " AND {$condition['Condition']}";
            }

            $tmplData = DB::getAssoc("
                SELECT 
                    cl.ClientID,
                    cl.Company AS ClientName,
                    cl.Budget AS ClientBudget,
                    cl.SquareFrom AS ClientSquareFrom,
                    cl.SquareTo AS ClientSquareTo,
                    cl.CostFrom AS ClientCostFrom,
                    cl.CostTo AS ClientCostTo,
                    cut.ResultName AS CallResult,
                    sec_cut.UserTypeName AS CallInResult,
                    calls.SquareFrom AS CallSquare,
                    calls.BrokerComment AS BrokerCallComment
                FROM ip_calls_params icp
                LEFT JOIN clients cl ON icp.ClientID = cl.ClientID
                LEFT JOIN calls ON icp.LeadID = calls.CallID
                LEFT JOIN call_user_types cut ON cut.UserTypeID = calls.ResultID
                LEFT JOIN call_user_types sec_cut ON sec_cut.UserTypeID = calls.UserTypeID
                WHERE icp.IPCallID = {$IPCallID} {$conditions}
            ")?: [];

            if (!empty($conditions) && empty($tmplData)){
                $results[] = ['error' => "Для этого звонка не выполнены условия задачи '{$taskData['TaskName']}'"];
                continue;
            }

            $vars = [];
            $varMatch = [];
            preg_match_all('/@(.*?);/simu', $prompt, $varMatch);
            foreach ($varMatch[1] as $varValue) {
                if (isset($tmplData[$varValue])) {
                    $vars[] = self::CONTEXT_REPLACE_VARS[$varValue].' - '.$tmplData[$varValue];
                }
            }
            $vars = implode("\n", $vars);

            foreach ($taskData['models'] as $model) {
                foreach ($taskData['callTypes'] as $callType) {
                    foreach ($existTasks as $existTask) {
                        // Проверка существует ли такой же запрос
                        if (
                            $existTask['TaskID'] == $taskID
                            && $existTask['CallType'] == $callType
                            && $existTask['Alias'] == $model
                        )
                        {
                            $results[] = ['error' => "Запрос с TaskID = {$taskID}, CallType = {$callType} и LMM = {$model} уже существует"];
                            continue 3;
                        }
                    }
                    $posts = $callTypesKeys[$callType]['Posts'];
                    $callText = $callData['Text'];

                    // Вычисление ответственного за звонок сотрудника
                    if (!empty($posts)) {
                        $dateDay = $callData['DateDay'];
                        $userData = DB::getAssoc("
                            SELECT
                                edh.UserID,
                                edh.PostID
                            FROM
                                ip_calls_params icp
                            INNER JOIN employee_departments_history edh 
                                ON (
                                    edh.UserID = icp.UserID
                                    OR edh.UserID = icp.InUserID
                                )
                                AND edh.DateStart <= '{$dateDay}' AND coalesce(edh.DateEnd, curdate()) >= '{$dateDay}'
                                AND edh.PostID IN ({$posts})
                            INNER JOIN employees_posts ep
                                ON edh.PostID = ep.PostID
                            INNER JOIN sys_users su
                                ON edh.UserID = su.UserID
                            WHERE icp.IPCallID = {$IPCallID}
                            ORDER BY ep.PostOrder DESC, ep.PostID
                        ");
                        $userID = $userData['UserID'];
                        $userPost = $userData['PostID'];
                        $explodedText = explode(\helpers\SpeechKit::DialogSeparator, $callText);
                        if ($explodedText[1]) {
                            $callText = $userPost == userPosts::secretary? $explodedText[0] : $explodedText[1];
                            $callText = trim($callText);
                        }
                    }
                    else {
                        $userID = DB::getItem("
                            SELECT UserID 
                            FROM ip_calls_params icp
                            WHERE IPCallID = {$IPCallID}
                        ");
                    }

                    $reqPrompt = forms::parseSQL($prompt.$callText, $tmplData);
                    $results[] =  self::saveRequest($reqPrompt, $model, [
                        'Callback' => 'AI::endQualityControl',
                        'CallbackData' => [
                            'IPCallID' => $IPCallID
                        ],
                        'Context' => $contextPrompt,
                        'CallType' => $callType,
                        'UserID' => $userID,
                        'TaskID' => $taskID,
                        'ParentID' => $IPCallID,
                        'Vars' => $vars,
                        'ParentTypeID' => 1
                    ]);
                }
            }
        }
        return $results;
    }


    /**
     * Получает результат запроса на оценку качества звонка и помещает его в qc_calls_results
     * @return array Массив с error или success
     * */
    public static function endQualityControl($params)
    {
        $logID = $params['LogID'];
        $logData = DB::getAssoc("SELECT Answer, CallbackData, LMM FROM log_ai_request WHERE LogID = '{$logID}'");
        $cbData = json_decode($logData['CallbackData'], true);
        $IPCallID = $cbData['IPCallID'];

        if (!$IPCallID)
		{
            return ['error' => 'IPCallID не найден'];
        }

        $answer = trim($logData['Answer'], "`");
        $answer = trim($answer, "json");
        $answer = json_decode($answer, true);

        if (!$answer || !count($answer))
		{
            return ['error' => 'В ответе пришел неправильный JSON'];
        }

        $questionTypes = DB::getAssocs("SELECT ID, QuestionKey FROM ai_questions");
        $questionTypesIDs = [];

        foreach ($questionTypes as $questionType)
		{
            $questionTypesIDs[$questionType['QuestionKey']] = $questionType['ID'];
        }

        $updatedSuccessfully = false;
        foreach ($answer as $question)
		{
            $questionID = $questionTypesIDs[$question['questionKey']];
            $updated = DB::updateOrInsertByTableName('qc_calls_results',
                [
                    'IPCallID' => $IPCallID,
                    'QuestionID' => $questionID,
                    'Score' => $question['score'],
                    'Recommendation' => $question['recommendation'],
                    'Fact' => $question['fact'],
                    'RequestID' => $logID,
                ],
                ['CallID', 'QuestionID', 'RequestID']
            );
            if ($updated) $updatedSuccessfully = true;
        }
        return ['success' => $updatedSuccessfully];
    }

    /**
     * @param string $dateStart - от какой даты выбирать звонки в формате `Y-m-d H:i:s`
     * */
    public static function initCallTasks($dateStart = '')
    {
        $dateStart = $dateStart ? : date('Y-m-d');

        $callTypeAndTasks = DB::getAssocs("
            SELECT
                IF(ict.ParentType != 0, ict.ParentType, ict.CallType) as CallType,
                ai_tasks.TaskID
            FROM ai_tasks
            INNER JOIN ai_tasks_qc_calls_types tqct ON ai_tasks.TaskID = tqct.TaskID
            INNER JOIN ip_calls_type ict ON ict.CallType = tqct.CallTypeID
            WHERE 
                ai_tasks.Active = 1
                AND ai_tasks.TypeID = 1
                AND ai_tasks.DateStart <= '{$dateStart}'
                AND (ai_tasks.DateEnd >= '{$dateStart}' OR ai_tasks.DateEnd IS NULL)
            GROUP BY tqct.ID
        ");


        $taskByCallType = [];
        foreach ($callTypeAndTasks as $callTypeAndTask){
            if (empty($taskByCallType[$callTypeAndTask['CallType']])) {
                $taskByCallType[$callTypeAndTask['CallType']] = [];
            }
            $taskByCallType[$callTypeAndTask['CallType']][] = $callTypeAndTask['TaskID'];
        }

        $callTypes = implode(',', array_keys($taskByCallType));

        if (!empty($callTypes)) {
            $calls = DB::getAssocs("
                SELECT
                    ics.CallID,
                    icp.callType,
                    GROUP_CONCAT(DISTINCT lair.TaskID) as Tasks
                FROM ip_calls_stat ics
                INNER JOIN ip_calls_params icp ON icp.IPCallID = ics.CallID AND icp.CallType IN ({$callTypes})
                INNER JOIN calls_to_text ON ics.CallID = calls_to_text.IPCallID                    
                LEFT JOIN log_ai_request lair 
                    ON lair.ParentTypeID = 1 
                    AND lair.ParentID = ics.CallID
                WHERE 
                    calls_to_text.BadResult = 0
                    AND ics.DateDay >= '{$dateStart}'
                GROUP BY ics.CallID
            ");

            foreach ($calls as $call) {
                $callType = $call['callType'];
                $doneTasks = explode(',', $call['Tasks']);
                $mustTasks = $taskByCallType[$callType];
                $diffTasks = array_diff($mustTasks, $doneTasks);
                if (!empty($diffTasks)) {
                    AI::startQualityControl($call['CallID']);
                }
            }
        }
    }

    /**
     * Создает задачи по анализу ленты действий для ИИ
     * @param string $dateStart - от какой даты строить ленту в формате `Y-m-d H:i:s`
     * */
    public static function initActionTasks($dateStart = '') {
        $dateStart = $dateStart ? : date('Y-m-d 00:00:00');
        $results = [];

        $actionTasks = DB::getAssocs("
            SELECT
                ait.TaskID,
                aic.`Condition`,
                ait.PeriodDays,
                ait.Prompt,
                ait.TypeID,
                aip.PromptText as ContextPrompt
            FROM ai_tasks ait
            INNER JOIN ai_tasks_conditions aitc ON aitc.TaskID = ait.TaskID
            INNER JOIN ai_conditions aic ON aic.ID = aitc.ConditionID
            LEFT JOIN ai_prompts aip ON aip.PromptID = ait.PromptID
            WHERE 
                ait.Active = 1
                AND ait.TypeID IN (2,3)
                AND ait.DateStart <= '{$dateStart}'
                AND (ait.DateEnd >= '{$dateStart}' OR ait.DateEnd IS NULL)
            GROUP BY ait.TaskID
        ");
        $byTaskID = [];
        foreach ($actionTasks as $task) {
            $byTaskID[$task['TaskID']] = $task;
        }

        $tasksIDs = implode(',', array_keys($byTaskID));

        $questions = DB::getAssocs("
            SELECT
                TaskID,
                ai_q.QuestionText
            FROM ai_tasks_questions aitq
            INNER JOIN ai_questions ai_q ON aitq.QuestionID = ai_q.ID
            WHERE TaskID IN ($tasksIDs)
        ");
        foreach ($questions as $question) {
            $byTaskID[$question['TaskID']]['questions'][] = $question['QuestionText'];
        }

        $models = DB::getAssocs("
            SELECT 
                TaskID,
                taim.AIModelID,
                ai_models.Alias
            FROM ai_tasks_ai_models taim
            INNER JOIN ai_models ON ai_models.ID = taim.AIModelID
            WHERE TaskID IN ($tasksIDs)
        ");
        foreach ($models as $model) {
            $byTaskID[$model['TaskID']]['models'][$model['AIModelID']] = $model['Alias'];
        }

        foreach ($byTaskID as $taskID => $taskData) {
            if (empty($taskData['questions']) || empty($taskData['models'])) {
                continue;
            }

            $prompt = $taskData['Prompt'];
            if (!empty($prompt)){
                $prompt .= PHP_EOL;
            }
            $prompt .= "В своем ответе не используй спецсимволы и разметку markdown, но используй знаки препинания. Проанализируй ленту событий, составь на ее основе резюме в котором ответь на следующие вопросы:".PHP_EOL;
            foreach ($taskData['questions'] as $index => $question) {
                $index = $index+1;
                $prompt .= "$index. $question".PHP_EOL;
            }

            $prompt .= PHP_EOL.'Лента событий:'.PHP_EOL;

            $periodDate = date('Y-m-d H:i:s', strtotime("$dateStart - {$taskData['PeriodDays']} days"));
            $condition = \common::replaceTemplate([
                'dateStart' => $dateStart,
                'periodDate' => $periodDate,
            ], $taskData['Condition']);

            $contextPrompt = $taskData['ContextPrompt'];
            if (!empty($contextPrompt)){
                $contextPrompt = [[
                    'role' => 'system',
                    'content' => $contextPrompt
                ]];
            }

            $requests = [];

            // Лента клиента
            if ($taskData['TypeID'] == 2) {
                $clients = DB::getAssocs("
                    SELECT
                        cl.Company AS ClientName,
                        cl.Budget AS ClientBudget,
                        cl.SquareFrom AS ClientSquareFrom,
                        cl.SquareTo AS ClientSquareTo,
                        cl.CostFrom AS ClientCostFrom,
                        cl.CostTo AS ClientCostTo,
                        clh.ClientID,
                        clh.ID as HistoryID,
                        GROUP_CONCAT(DISTINCT aim.Alias) as AIModels,
                        IF(clh.ArchiveReasonID !=0, clh.ArchivedDate, '$dateStart')  as ActionDateEnd
                    FROM clients_history clh
                    INNER JOIN clients cl ON cl.clientID = clh.ClientID
                    LEFT JOIN log_ai_request lair
                        ON lair.ParentTypeID = 2
                        AND lair.TaskID = '{$taskID}'
                        AND lair.ParentID = clh.ClientID
                        AND lair.DateStart > '{$periodDate}'
                    LEFT JOIN ai_models aim
                        ON aim.ID = lair.LMM
                    WHERE
                        $condition
                    GROUP BY clh.ClientID
                ");

                foreach ($clients as $client) {
                    $existAIModels = explode(',', $client['AIModels']);
                    $modelsDiff = array_diff($taskData['models'], $existAIModels);
                    if (empty($modelsDiff)) {
                        continue;
                    }

                    $actions = \actions::getClientAction($client['ClientID'], [
                        'AI' => true,
                        'startDate' => $periodDate,
                        'endDate' => $client['ActionDateEnd'],
                    ]);

                    if (empty($actions['data'])){
                        continue;
                    }

                    $reqPrompt = $prompt;
                    foreach ($actions['data'] as $action) {
                        $actionDate = date('Y-m-d H:i:s', $action['Date']);
                        $userName = strip_tags($action['Surname']);
                        $description = strip_tags($action['Comment']);
                        $reqPrompt .= "Дата - {$actionDate}".PHP_EOL;
                        $reqPrompt .= "Тип - {$action['Title']}".PHP_EOL;
                        $reqPrompt .= "Связанный сотрудник - {$userName}".PHP_EOL;
                        $reqPrompt .= "Подробности - \"{$description}\"".PHP_EOL.PHP_EOL;
                    }

                    $requests[] = [
                        'Question' => $reqPrompt,
                        'ParentID' => $client['HistoryID'],
                        'TmplData' => $client,
                        'Models' => $modelsDiff,
                        'CallbackData' => [
                            'DateStart' => $periodDate,
                            'DateEnd' => $client['ActionDateEnd'],
                            'ParentType' => 1
                        ],
                    ];
                }

                unset($clients, $actions);
            }

            // Лента сотрудника
            if ($taskData['TypeID'] == 3) {
                $employees = DB::getAssocs("
                    SELECT 
                        su.UserID,
                        su.Fullname AS EmployeeName,
                        GROUP_CONCAT(DISTINCT aim.Alias) as AIModels,
                        COALESCE(em.LastWorkDay, '$dateStart') as ActionDateEnd
                    FROM sys_users su
                    INNER JOIN employee em ON su.UserID = em.UserID
                    LEFT JOIN log_ai_request lair
                        ON lair.ParentTypeID = 3
                        AND lair.TaskID = '{$taskID}'
                        AND lair.ParentID = su.UserID
                        AND lair.DateStart > '{$periodDate}'
                    LEFT JOIN ai_models aim 
                        ON aim.ID = lair.LMM
                    WHERE 
                        $condition
                    GROUP BY su.UserID
                ");

                foreach ($employees as $employee) {
                    $existAIModels = explode(',', $employee['AIModels']);
                    $modelsDiff = array_diff($taskData['models'], $existAIModels);
                    if (empty($modelsDiff)) {
                        continue;
                    }

                    $actions = \employees\ajax::loadAllEmployeeInfo($employee['UserID'], [
                        'dateStart' => $periodDate,
                        'dateEnd' => $employee['ActionDateEnd'],
                        'asArray' => true,
                        'AI' => true,
                    ]);

                    if (empty($actions['content'])){
                        continue;
                    }

                    $reqPrompt = $prompt;
                    foreach ($actions['content'] as $action) {
                        $description = strip_tags($action['Description']);
                        $actionDate = date('Y-m-d H:i:s', $action['UnixTime']);
                        $reqPrompt .= "Дата - {$actionDate}".PHP_EOL;
                        $reqPrompt .= "Тип - {$action['Title']}".PHP_EOL;
                        $reqPrompt .= "Подробности - \"{$description}\"".PHP_EOL.PHP_EOL;
                    }

                    $requests[] = [
                       'Question' => $reqPrompt,
                       'ParentID' => $employee['UserID'],
                       'TmplData' => $employee,
                       'Models' => $modelsDiff,
                       'CallbackData' => [
                           'DateStart' => $periodDate,
                           'DateEnd' => $employee['ActionDateEnd'],
                           'ParentType' => 2
                       ],
                    ];
                }

                unset($employees, $actions);
            }

            foreach ($requests as $request) {
                $prompt = $request['Question'];
                $tmplData = $request['TmplData'];

                $vars = [];
                $varMatch = [];
                preg_match_all('/@(.*?);/simu', $prompt, $varMatch);
                foreach ($varMatch[1] as $varValue) {
                    if (isset($tmplData[$varValue])) {
                        $vars[] = self::CONTEXT_REPLACE_VARS[$varValue].' - '.$tmplData[$varValue];
                    }
                }
                $vars = implode("\n", $vars);
                $reqPrompt = forms::parseSQL(trim($prompt), $tmplData);


                foreach ($request['Models'] as $model) {
                    $results[] =  self::saveRequest($reqPrompt, $model, [
                        'Context' => $contextPrompt,
                        'TaskID' => $taskID,
                        'ParentID' => $request['ParentID'],
                        'ParentTypeID' => $taskData['TypeID'],
                        'Vars' => $vars,
                        'Callback' => 'AI::saveReport',
                        'CallbackData' => $request['CallbackData'],
                    ]);
                }
            }
        }

        return $results;
    }


    /**
     * Получает результат запроса на оценку качества звонка и помещает его в qc_calls_results
     * @return array Массив с error или success
     * */
    public static function saveReport($params)
    {
        $logID = $params['LogID'];
        $logData = DB::getAssoc("SELECT Answer, CallbackData, TaskID, ParentID FROM log_ai_request WHERE LogID = '{$logID}'");
        $cbData = json_decode($logData['CallbackData'], true);

        $insID = \DB::insertByTableName('ai_reports', [
            'ParentID' => $logData['ParentID'],
            'ParentType' => $cbData['ParentType'],
            'DateStart' => $cbData['DateStart'],
            'DateEnd' => $cbData['DateEnd'],
            'Created' => date('c'),
            'TaskID' => $logData['TaskID'],
            'ReportText' => $logData['Answer']
        ]);

        return ['success' => $insID];
    }

    /**
     * Получает резюме и очищенное содержание письма из broker_email
     * @param int $recordID - ID сообщения из broker_email
     * @param string $mode - Режим выборки писем (all - все письма)
     * @return array
     * */
    public static function improveEmail($recordID, $mode = ''){
        if (!$recordID && $mode !== 'all') {
            return ['error' => 'recordID не указан'];
        }

        if ($mode === 'all') {
            $emailsData = DB::getAssocs("
                SELECT * FROM (
                    SELECT 
                        be.RecordID,
                        su.FullName,
                        be.Subject,
                        be.From,
                        be.To,
                        be.Body,
                        if (be.`To` = co.Contact, 'From', 'To') AS Dist,
                        cl.Company as ClientName,
                        cp.Name as PersonName,
                        cp.PostID as PostID,
                        cpp.PostName,
                        be.UserID,
                        be.ImprovedAI
                    FROM clients AS cl
                    INNER JOIN contacts_persons AS cp ON cl.ClientID = cp.ParentID
                    LEFT JOIN contacts_persons_posts cpp ON cp.PostID = cpp.PostID
                    INNER JOIN contacts co 
                        ON co.TypeID = 2
                        AND co.PersonID = cp.PersonID
                        AND co.Contact NOT LIKE '%@fortexgroup.ru'
                        AND co.Contact NOT LIKE '%@2800526.ru'
                    LEFT JOIN broker_email_to bet 
                        ON bet.Email = co.Contact
                    INNER JOIN broker_email be
                        ON be.`From` = co.Contact
                        OR be.RecordID = bet.RecordID
                    INNER JOIN sys_users su ON su.UserID = be.UserID
                    WHERE
                        cl.Archived = 0 AND cl.Status = 1
                    GROUP BY be.RecordID
                ) AS subq
                WHERE subq.ImprovedAI = 0
            ");
        }
        else {
            $emailsData = DB::getAssocs("
                SELECT 
                    be.RecordID,
                    su.FullName,
                    be.Subject,
                    be.From,
                    be.To,
                    be.Body,
                    if (be.`To` = co.Contact, 'From', 'To') AS Dist,
                    cl.Company as ClientName,
                    cp.Name as PersonName,
                    cp.PostID as PostID,
                    cpp.PostName,
                    be.UserID
                FROM broker_email be
                INNER JOIN sys_users su ON su.UserID = be.UserID
                LEFT JOIN broker_email_to et ON be.RecordID	= et.RecordID and IF(be.FolderID in (1,43), 1, 0)
                INNER JOIN broker_email_folders AS f ON be.FolderID = f.FolderID
                LEFT JOIN contacts co ON co.TypeID = 2
                    AND IF(be.FolderID in (1,43),
                        co.Contact = et.Email
                        and co.Contact NOT LIKE '%@fortexgroup.ru'
                        
                        and co.Contact NOT LIKE '%@2800526.ru',
                        co.Contact = be.`From`
                    )
                LEFT JOIN contacts_persons AS cp ON co.PersonID = cp.PersonID
                INNER JOIN contacts_persons_posts cpp ON cp.PostID = cpp.PostID
                INNER JOIN clients cl ON cl.ClientID = cp.ParentID
                WHERE 
                    be.RecordID = '{$recordID}'
                    AND be.FolderID != 5
                    AND co.Contact != ''
                    and co.Contact IS NOT NULL
                GROUP BY be.RecordID
            ");
        }


        if (empty($emailsData)){
            return ['error' => 'Запись в broker_email не найдена'];
        }

        $result = [];
        $dateStart = new DateTime();
        $step = 500;
        $i = 0;

        foreach ($emailsData as $emailData) {
            //Для обработки большого кол-ва писем
            $i++;
            if ($i > $step) {
                $i = 0;
                $dateStart->modify('+1 day');
            }

            $prompt = "Проанализируй следующее email сообщение между клиентом и сотрудником. Предоставь результат в виде чистого JSON со следующей структурой:
{
    \"summary\": *Резюме сообщения длиной до 255 символов*,
    \"clearText\": *Очищенный текст сообщения*,
}".PHP_EOL.PHP_EOL;

            if ($emailData['Dist'] == 'From') {
                $prompt .= "Сотрудник {$emailData['FullName']} отправил письмо с почтового адреса {$emailData['From']}, ";
                if ($emailData['PersonName'] && $emailData['PostID'] != 17) {
                    $prompt .= "представителю {$emailData['PersonName']} ({$emailData['PostName']}) клиента {$emailData['ClientName']}";
                }
                else {
                    $prompt .= "клиенту {$emailData['ClientName']}";
                }
                $prompt .= " на почтовый адрес {$emailData['To']}";
            }
            else {
                if ($emailData['PersonName'] && $emailData['PostID'] != 17) {
                    $prompt .= "Представитель {$emailData['PersonName']} ({$emailData['PostName']}) клиента {$emailData['ClientName']}";
                }
                else {
                    $prompt .= "Клиент {$emailData['ClientName']}";
                }
                $prompt .= " отправил письмо с почтового адреса {$emailData['From']}, сотруднику {$emailData['FullName']} на почтовый адрес {$emailData['To']}";
            }


            if ($emailData['Subject']) {
                $prompt .= PHP_EOL."Тема: «".trim(strip_tags($emailData['Subject'])).'»';
            }
            $prompt .= PHP_EOL."Сообщение: «".trim(strip_tags($emailData['Body'])).'»';


            $result[] = \AI::saveRequest($prompt, \AI::DeepSeek, [
                'Context' => [[
                    'role' => 'system',
                    'content' => 'Ты программа обработки email сообщений. Ты помогаешь агентству, занимающемуся арендой и продажей офисов в Москве. В ответ ты должна выдавать только JSON, не используй в своем ответе символы для маркировки текста, не общайся с пользователем.'
                ]],
                'ParentTypeID' => 4,
                'ParentID' => $emailData['RecordID'],
                'UserID' => $emailData['UserID'],
                'Callback' => 'AI::saveEmailSummary',
                'DateStart' => $dateStart->format('Y-m-d'),
            ]);
        }
        return $result;
    }

    /**
     * Получает результат улучшение email и сохраняет в broker_email
     * @return array Массив с error или success
     * */
    public static function saveEmailSummary($params){
        $logID = $params['LogID'];
        $logData = DB::getAssoc("SELECT Answer, ParentID FROM log_ai_request WHERE LogID = '{$logID}'");
        $recordID = $logData['ParentID'];
        $emailRecord = DB::getItem("SELECT COUNT(*) FROM broker_email WHERE RecordID = {$recordID}");

        if (!$emailRecord)
        {
            return ['error' => 'Запись в broker_email не найден'];
        }

        $answer = trim($logData['Answer'], "`");
        $answer = trim($answer, "json");
        $answer = json_decode($answer, true);

        if (!$answer || !count($answer))
        {
            return ['error' => 'В ответе пришел неправильный JSON'];
        }

        if ($answer['clearText'] || $answer['summary']) {
            $updID = \DB::updateByTableName('broker_email', [
                'BodyClear' => $answer['clearText'],
                'BodyAI' => $answer['summary'],
                'ImprovedAI' => $answer['summary'] ? 1:0,
            ], ['RecordID' => $recordID]);

            return ['success' => $updID];
        }
        else {
            return ['error' => 'В JSON отсутствуют clearText и summary'];
        }
    }

}