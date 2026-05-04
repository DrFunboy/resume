<?php

namespace App\Services;

use App\Enums\AmoEventStatus;
use App\Enums\AmoEventType;
use App\Models\AmoAccount;
use App\Models\AmoConnection;
use App\Models\AmoEvent;
use App\Models\AmoFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class Amo2SheetsService
{
    const string SHEETS_ENDPOINT = 'https://sheets.googleapis.com/v4/spreadsheets/';
    const string SHEET_NAME = 'Сделки';
    const int MAX_TRY_COUNT = 5;


    static function easyCurl($endpoint, $token, $body, $method): bool|string
    {
        if (empty($body) && $method == 'POST'){
            $method = 'GET';
        }

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token
            ),
        ));
        if (!empty($body)){
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $rawResponse = curl_exec($curl);
        curl_close($curl);
        return $rawResponse;
    }

    static function googleCurl($endpoint, $token, $body = [], $method = 'POST')
    {
        $endpoint = self::SHEETS_ENDPOINT . $endpoint;
        $rawResponse = self::easyCurl($endpoint, $token, $body, $method);
        $response = json_decode($rawResponse, true);

        if (empty($response['spreadsheetId'])){
            $logBody = json_encode([
                'endpoint' => $endpoint,
                'token' => $token,
                'rawResponse' => $rawResponse,
                'body' => $body,
            ]);
            Log::channel('amo')->error('SheetID ' . $logBody);
            return ['error' => $rawResponse];
        }
        return $response;
    }

    static function amoCurl($endpoint, $token, $body = [], $method = 'POST')
    {
        $rawResponse = self::easyCurl($endpoint, $token, $body, $method);
        $response = json_decode($rawResponse, true);

        if (empty($response['_embedded'])){
            $logBody = json_encode([
                'endpoint' => $endpoint,
                'token' => $token,
                'rawResponse' => $rawResponse,
                'body' => $body,
            ]);
            Log::channel('amo')->error('AmoAPI ' . $logBody);
            return ['error' => $rawResponse];
        }
        return $response;
    }

    static function parseAmoFilter(string $filter): array
    {
        $parsedArr = [];
        $filterArr = [];
        parse_str($filter, $filterArr);


        if (!empty($filterArr['filter']['pipe'])) {
            foreach ($filterArr['filter']['pipe'] as $pipelineID => $statuses){
                foreach ($statuses as $statusID){
                    $parsedArr['filter']['statuses'][] = [
                        'pipeline_id' => $pipelineID,
                        'status_id' => $statusID
                    ];
                }
            }
        }

        // TODO брать AMO_CUSTOM_FILTERS из БД
        if (!empty($filterArr['filter']['cf']) && env('AMO_CUSTOM_FILTERS', false)) {
            foreach ($filterArr['filter']['cf'] as $fieldName => $fieldParams){
                $parsedArr['filter']['custom_fields_values'][$fieldName] = $fieldParams;
            }
        }


        if (!empty($filterArr['filter_date_from']) && !empty($filterArr['filter_date_to'])) {
            $parsedArr['filter']['created_at']['from'] = strtotime($filterArr['filter_date_from']);
            $parsedArr['filter']['created_at']['to'] = strtotime($filterArr['filter_date_to']);
        }

        return $parsedArr;
    }

    public static function getGoogleToken($domain, $code = null, $redirectUrl = null): bool|string
    {
        $token = Cache::get('google_access_' . $domain);
        if ($token && empty($code)) {
            return $token;
        }

        /** @var AmoAccount $account */
        $account = AmoAccount::query()->where(['domain' => $domain])->first();
        if (empty($account)) {
            return false;
        }

        $params = [
            'client_id' => env('GOOGLE_CLIENT_ID'),
            'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        ];

        if ($account->google_refresh) {
            $params['grant_type'] = 'refresh_token';
            $params['refresh_token'] = $account->google_refresh;
        }
        else {
            if (empty($code) || empty($redirectUrl)) {
                return false;
            }
            $params['code'] = $code;
            $params['grant_type'] = 'authorization_code';
            $params['redirect_uri'] = $redirectUrl;
        }

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://oauth2.googleapis.com/token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($params),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));
        $response = json_decode(curl_exec($curl), true);
        curl_close($curl);

        if (empty($response['access_token'])){
            Log::channel('daily')->alert('Invalid google_token. '.json_encode([
                'params' => $params,
                'response' => $response
            ]));
            return false;
        }

        if (!empty($response['refresh_token'])){
            $account->google_refresh = $response['refresh_token'];
            $account->save();
        }

        Cache::put('google_access_'.$domain, $response['access_token'], $response['expires_in']);

        return $response['access_token'];
    }

    public static function revokeGoogleToken($domain): bool
    {
        /** @var AmoAccount $account */
        $account = AmoAccount::query()->where(['domain' => $domain])->first();
        $token = Cache::get('google_access_' . $domain);

        if (empty($token)){
            if (empty($account) || empty($account->google_refresh)) {
                return false;
            }
            $token = $account->google_refresh;
        }

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://oauth2.googleapis.com/revoke?token='.$token,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));
        curl_exec($curl);
        curl_close($curl);

        $account->google_refresh = null;
        $account->save();

        Cache::delete('google_access_' . $domain);

        return true;
    }

    public static function getAmoToken($domain, $code = null): bool|string
    {
        $redirectUrl = route('amoInstall');
        $token = Cache::get('amo_access_' . $domain);
        if ($token && empty($code)) {
            return $token;
        }

        /** @var AmoAccount $account */
        $account = AmoAccount::query()->where(['domain' => $domain])->first();
        if (empty($account)) {
            return false;
        }

        $params = [
            'client_id' => $account->client_id,
            'client_secret' => $account->amo_secret,
            'redirect_uri' => $redirectUrl
        ];

        if ($account->amo_refresh) {
            $params['grant_type'] = 'refresh_token';
            $params['refresh_token'] = $account->amo_refresh;
        }
        else {
            if (empty($code)) {
                return false;
            }
            $params['code'] = $code;
            $params['grant_type'] = 'authorization_code';
        }

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://' . $domain . '/oauth2/access_token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($params),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));
        $response = json_decode(curl_exec($curl), true);
        curl_close($curl);

        if (empty($response['access_token'])){
            Log::channel('daily')->alert('Invalid amo_token. '.json_encode([
                'params' => $params,
                'response' => $response
            ]));
            return false;
        }

        if (!empty($response['refresh_token'])){
            $account->amo_refresh = $response['refresh_token'];
            $account->save();
        }

        Cache::put('amo_access_'.$domain, $response['access_token'], $response['expires_in']);

        return $response['access_token'];
    }

    /**
     * @param string $domain
     * @param string $spreadsheetId
     * @param string $pipelineID
     * @param array $deals
     * @param bool $force Не искать совпадения
     * @return array
     */
    static function saveDataToSheet(
        string $domain,
        string $spreadsheetId,
        array $sheetFields,
        string $pipelineID,
        array $deals,
        bool $force = false
    ): array
    {
        $amoToken = self::getAmoToken($domain);
        $googleToken = self::getGoogleToken($domain);
        $sheetName = self::SHEET_NAME;

        usort($sheetFields, function($a, $b) {
            return $a['order'] <=> $b['order'];
        });

        # Получение статусов воронки
        static $amoStatuses = [];
        if (empty($amoStatuses)) {
            $rawStatuses = self::amoCurl(
                endpoint: "https://{$domain}/api/v4/leads/pipelines/{$pipelineID}/statuses",
                token: $amoToken
            );
            foreach ($rawStatuses['_embedded']['statuses'] as $amoStatus) {
                $amoStatuses[$amoStatus['id']] = $amoStatus['name'];
            }
            unset($rawStatuses);
        }

        # Получение пользователей в amo
        static $amoUsers = [];
        if (empty($amoUsers)) {
            $nextPage = 1;
            while ($nextPage){
                $rawUsers = self::amoCurl(
                    endpoint: "https://{$domain}/api/v4/users?page={$nextPage}",
                    token: $amoToken
                );
                foreach ($rawUsers['_embedded']['users'] as $amoUser) {
                    $amoUsers[$amoUser['id']] = $amoUser['name'];
                }
                unset($rawUsers);

                if (empty($deals['_links']['next'])){
                    break;
                }
                $nextPage++;
            }
        }

        if (!$force){
            # Создание листа для поиска по id
            $newSheetName = 'MatchTab';
            $newSheet = self::googleCurl("$spreadsheetId:batchUpdate", $googleToken, [
                'requests' => ['addSheet' => ['properties' => ['title' => $newSheetName]]]
            ]);

            if (!empty($newSheet['error'])) {
                return $newSheet;
            }
            $newSheetID = $newSheet['replies'][0]['addSheet']['properties']['sheetId'];
        }

        # Поиск строк которые можно обновить и генерация фильтра для запроса контактов
        $updateValues = [];
        $dealPositions = [];
        $contactFilter = [];

        foreach ($deals as $deal) {
            $dealPositions[] = [
                'id' => $deal['id'],
                'position' => 0
            ];
            $updateValues[] = ["=MATCH({$deal['id']}; {$sheetName}!B:B; 0)"];
            if (empty($deal['_embedded']['contacts'])) {
                continue;
            }
            $contactFilter['filter']['id'][] = $deal['_embedded']['contacts'][0]['id'];
        }

        if (!$force) {
            $matches = self::googleCurl(
                endpoint: "{$spreadsheetId}/values/{$newSheetName}:append?valueInputOption=USER_ENTERED&includeValuesInResponse=true",
                token: $googleToken,
                body: ['values' => $updateValues]
            );
            if (!empty($matches['error'])) {
                return $matches;
            }

            # Сопоставление позиции в листе и id сделки
            foreach ($matches['updates']['updatedData']['values'] as $i => $rowPosition) {
                $position = intval($rowPosition[0]);
                if ($position != 0) {
                    $dealPositions[$i]['position'] = $position;
                }
            }

            $dealPositionKeys = [];
            foreach ($dealPositions as $dealPosition){
                if ($dealPosition['position'] != 0){
                    $dealPositionKeys[$dealPosition['id']] = $dealPosition['position'];
                }
            }
        }

        # Запрос контактов
        $contactFilter = http_build_query($contactFilter);

        $amoContacts = self::amoCurl(
            endpoint: "https://{$domain}/api/v4/contacts?$contactFilter",
            token: $amoToken
        );
        $dealsContacts = [];
        foreach ($amoContacts['_embedded']['contacts'] as $amoContact){
            $dealsContacts[$amoContact['id']] = $amoContact;
        }

        unset($amoContacts);

        $appendDeals = [];
        $updateDeals = [];

        foreach ($deals as $deal) {
            $dealFields = [];
            if (!empty($deal['_embedded']['contacts'])){
                $dealContact = $dealsContacts[$deal['_embedded']['contacts'][0]['id']];
            }
            else {
                $dealContact = [];
            }

            foreach ($sheetFields as $sheetField){
                switch ($sheetField['name']) {
                    case 'empty':
                        $dealFields[] = '';
                        break;
                    case 'status_name':
                        $dealFields[] = $amoStatuses[$deal['status_id']];
                        break;
                    case 'responsible_user_name':
                        if ($sheetField['type'] == 'deal' && !empty($deal['responsible_user_id'])) {
                            $dealFields[] = $amoUsers[$deal['responsible_user_id']];
                        }
                        elseif (!empty($dealContact['responsible_user_id'])){
                            $dealFields[] = $amoUsers[$dealContact['responsible_user_id']];
                        }
                        else {
                            $dealFields[] = '';
                        }
                        break;
                    case 'created_by_name':
                        if ($sheetField['type'] == 'deal' && !empty($deal['created_by'])) {
                            $dealFields[] = $amoUsers[$deal['created_by']];
                        }
                        elseif (!empty($dealContact['created_by'])){
                            $dealFields[] = $amoUsers[$dealContact['created_by']];
                        }
                        else {
                            $dealFields[] = '';
                        }
                        break;
                    case 'updated_by_name':
                        if ($sheetField['type'] == 'deal' && !empty($deal['updated_by'])) {
                            $dealFields[] = $amoUsers[$deal['updated_by']];
                        }
                        elseif (!empty($dealContact['updated_by'])){
                            $dealFields[] = $amoUsers[$dealContact['updated_by']];
                        }
                        else {
                            $dealFields[] = '';
                        }
                        break;
                    default:
                        if (($sheetField['custom'] ?? false)){
                            $customFields = $sheetField['type'] == 'deal'? ($deal['custom_fields_values'] ?? []) : ($dealContact['custom_fields_values'] ?? []);
                            $customFieldValue = '';

                            foreach ($customFields as $customField){
                                if ($customField['field_name'] == $sheetField['name']){
                                    $customFieldValue = $customField['values'][0]['value'] ?? '';
                                    if (
                                        in_array($customField['field_type'], ['date', 'birthday'])
                                        && !empty($customFieldValue)
                                    ) {
                                        $customFieldValue = date('d.m.Y', $customFieldValue);
                                    }
                                    break;
                                }
                            }

                            $dealFields[] = $customFieldValue;
                        }
                        else {
                            $fieldSource = Amo2SheetsFields::$fields[$sheetField['type']][$sheetField['name']] ?? null;
                            if (empty($fieldSource)){
                                $dealFields[] = '';
                            }
                            else {
                                $fieldValue = $sheetField['type'] == 'deal'? ($deal[$sheetField['name']] ?? '') : ($dealContact[$sheetField['name']] ?? '');
                                if (!empty($fieldValue) && ($fieldSource['format'] ?? null) == 'date'){
                                    $fieldValue = date('d.m.Y', $fieldValue);
                                }

                                $dealFields[] = $fieldValue;
                            }
                        }
                }
            }

            if (!$force && !empty($dealPositionKeys[$deal['id']])) {
                $position = $dealPositionKeys[$deal['id']];
                $updateDeals[] = [
                    'range' => "{$sheetName}!A$position:Z",
                    'values' => [$dealFields]
                ];
            }
            else {
                $appendDeals[] = $dealFields;
            }
        }

        if (!$force) {
            # Удаление листа для поиска
            self::googleCurl("$spreadsheetId:batchUpdate", $googleToken, [
                'requests' => ['deleteSheet' => ['sheetId' => $newSheetID]]
            ]);
        }

        if (!empty($updateDeals)){
            # Обновление найденных сделок
            self::googleCurl(
                endpoint: "$spreadsheetId/values:batchUpdate",
                token: $googleToken,
                body: [
                    'valueInputOption' => 'USER_ENTERED',
                    'data' => $updateDeals
                ]
            );
        }

        # Добавление новых
        self::googleCurl(
            endpoint: "{$spreadsheetId}/values/{$sheetName}:append?valueInputOption=USER_ENTERED&insertDataOption=OVERWRITE",
            token: $googleToken,
            body: ['values' => $appendDeals]
        );

        return ['success' => true];
    }

    static function removeDataFromSheet($domain, $spreadsheetId, $ids): array
    {
        # Создание листа для поиска по id
        $googleToken = self::getGoogleToken($domain);
        $newSheetName = 'MatchTab';
        $newSheet = self::googleCurl("$spreadsheetId:batchUpdate", $googleToken, [
            'requests' => ['addSheet' => ['properties' => ['title' => $newSheetName]]]
        ]);

        if (!empty($newSheet['error'])) {
            return $newSheet;
        }
        $newSheetID = $newSheet['replies'][0]['addSheet']['properties']['sheetId'];

        # Получение id листа со сделками
        $sheetName = self::SHEET_NAME;
        $sheetData = self::googleCurl(
            endpoint: $spreadsheetId,
            token: $googleToken,
            method: 'GET'
        );

        $sheetID = null;
        foreach ($sheetData['sheets'] as $sheet){
            if ($sheet['properties']['title'] === $sheetName){
                $sheetID = $sheet['properties']['sheetId'];
                break;
            }
        }

        if (empty($sheetID)){
            return ['error' => "Sheet {$sheetName} not found"];
        }

        # Поиск строк которые нужно удалить
        $values = [];
        foreach ($ids as $id){
            $values[] = ["=MATCH({$id}; {$sheetName}!B:B; 0)"];
        }

        $matches = self::googleCurl(
            endpoint: "{$spreadsheetId}/values/{$newSheetName}:append?valueInputOption=USER_ENTERED&includeValuesInResponse=true",
            token: $googleToken,
            body: ['values' => $values]
        );
        if (!empty($matches['error'])) {
            return $matches;
        }

        # Удаление найденных строк
        $rowPositions = [];
        foreach ($matches['updates']['updatedData']['values'] as $rowPosition){
            $position = intval($rowPosition[0]);
            if ($position != 0) {
                $rowPositions[] = $position;
            }
        }

        if (!empty($rowPositions)){
            arsort($rowPositions);
            $ranges = [];
            foreach ($rowPositions as $position){
                $ranges[] = [
                    'deleteDimension' => [
                        'range' => [
                            'sheetId' => $sheetID,
                            'dimension' => 'ROWS',
                            'startIndex' => $position-1,
                            'endIndex' => $position,
                        ]
                    ]
                ];
            }

            self::googleCurl("$spreadsheetId:batchUpdate", $googleToken, [
                'requests' => $ranges
            ]);
        }

        # Удаление листа для поиска
        self::googleCurl("$spreadsheetId:batchUpdate", $googleToken, [
            'requests' => ['deleteSheet' => ['sheetId' => $newSheetID]]
        ]);

        return ['success' => true];
    }

    public static function syncConnection($domain, $connectionUUID, $page = 1): array
    {
        /** @var AmoConnection $connection */
        $connection = AmoConnection::query()->where('uuid', $connectionUUID)->first();
        if (empty($connection)) {
            return ['error' => 'Выгрузка не найдена'];
        }
        $pipelineID = $connection->filter->pipeline_id;

        if (empty($pipelineID)){
            return ['error' => 'Фильтр настроен неправильно'];
        }

        $googleToken = Amo2SheetsService::getGoogleToken($domain);
        if (!$googleToken){
            return ['error' => 'Авторизация Google не настроена'];
        }

        $sheetExist = self::googleCurl($connection->sheet_id, $googleToken);

        if (!empty($sheetExist['error'])) {
            return ['error' => 'Таблица ' . $connection->sheet_id . ' не существует или к ней нет доступа'];
        }

        $amoToken = Amo2SheetsService::getAmoToken($domain);
        if (!$amoToken){
            return ['error' => 'Авторизация AmoCRM не настроена'];
        }

        $filter = self::parseAmoFilter($connection->filter->filter_url);
        $filter['filter']['pipeline_id'] = $pipelineID;
        $filterStr = http_build_query($filter);

        $pipelineExist = self::amoCurl(
            endpoint: "https://{$domain}/api/v4/leads?{$filterStr}&order[created_at]=desc&limit=1",
            token: $amoToken
        );
        if (!empty($pipelineExist['error'])) {
            return ['error' => 'Воронка ' . $pipelineID . ' не существует или к ней нет доступа'];
        }

        if ($page === 1){
            $clearSheet = self::googleCurl(
                endpoint: $connection->sheet_id . '/values:batchClear',
                token: $googleToken,
                body: ['ranges' => [self::SHEET_NAME . '!A2:Z']]
            );

            if (!empty($clearSheet['error'])) {
                return ['error' => 'Таблицу ' . self::SHEET_NAME . ' не удалось очистить'];
            }
        }

        $deals = self::amoCurl(
            endpoint: "https://{$domain}/api/v4/leads?{$filterStr}&with=contacts&order[created_at]=desc&page={$page}",
            token: $amoToken
        );

        $saveResult = self::saveDataToSheet(
            domain: $domain,
            spreadsheetId: $connection->sheet_id,
            sheetFields: $connection->sheet_fields,
            pipelineID: $pipelineID,
            deals: $deals['_embedded']['leads'],
            force: true
        );

        if (!empty($saveResult['error'])){
            return [
                'next_page' => false,
                'error' => $saveResult['error']
            ];
        }

        return [
            'next_page' => !(empty($deals['_links']['next'])),
            'count_done' => count($deals['_embedded']['leads'])
        ];
    }

    public static function saveEvents($domain, $events): array
    {
        /** @var AmoAccount $account */
        $account = AmoAccount::query()->where(['domain' => $domain])->first();
        if (empty($account)){
            return ['error' => 'Выгрузка не найдена'];
        }

        if (!empty($events['leads'])){
            $externalIDs = [];
            foreach ($events['leads'] as $leadsAction){
                foreach ($leadsAction as $lead){
                    $externalIDs[] = $lead['id'];
                }
            }

            $existLeads = AmoEvent::query()
                ->where('type', AmoEventType::LEAD->value())
                ->whereNotIn('status', [
                    AmoEventStatus::COMPLETED->value(),
                    AmoEventStatus::REJECTED->value()
                ])
                ->whereIn('external_id', $externalIDs)
                ->where('try_count', '<', self::MAX_TRY_COUNT)
                ->get()
                ->keyBy('external_id')
                ->toArray();

            foreach ($events['leads'] as $leadsAction){
                foreach ($leadsAction as $lead){
                    if (!empty($existLeads[$lead['id']])){
                        continue;
                    }

                    $connection = $account->connections()->whereHas('filter', function ($query) use($lead) {
                        $query->where('pipeline_id', $lead['pipeline_id']);
                    })->first();
                    if (empty($connection)) {
                        continue;
                    }

                    $newEvent = new AmoEvent();
                    $newEvent->external_id = $lead['id'];
                    $newEvent->connection_id = $connection->id;
                    $newEvent->type = AmoEventType::LEAD->value();
                    $newEvent->status = AmoEventStatus::WAITING->value();
                    $newEvent->event_body = $lead;
                    $newEvent->save();
                }
            }
        }

        return [];
    }

    public static function processEvents(): void
    {
        // TODO: Не выполнять если идёт полная синхронизация
        $limit = 250;
        $offset = 0;
        while (true){
            $events = AmoEvent::query()
                ->where([
                    'type' => AmoEventType::LEAD->value(),
                ])
                ->where(function (Builder $query) {
                    $query
                        ->where('status', AmoEventStatus::WAITING->value())
                        ->orWhere(function (Builder $query) {
                            $query->where([
                                ['status', AmoEventStatus::PROCESSING->value()],
                                ['try_count', '<', self::MAX_TRY_COUNT],
                                ['date_start', '<=', strtotime('-30 min')]
                            ]);
                        });
                })
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->offset($offset)
                ->get();

            if ($events->isEmpty()){
                break;
            }

            $byPipeline = [];
            /** @var AmoEvent $event */
            foreach ($events as $event){
                $byPipeline[$event->connection->filter->pipeline_id]['connection'] = $event->connection;
                $byPipeline[$event->connection->filter->pipeline_id]['filter'] = $event->connection->filter;
                $byPipeline[$event->connection->filter->pipeline_id]['events'][] = $event;

                $event->status = AmoEventStatus::PROCESSING->value();
                $event->date_start = date('Y-m-d H:i:s');
                $event->try_count = $event->try_count + 1;
                $event->save();
            }

            foreach ($byPipeline as $eventData){
                /** @var AmoFilter $filter */
                $filter = $eventData['filter'];
                /** @var AmoConnection $connection */
                $connection = $eventData['connection'];
                $filterArr = self::parseAmoFilter($filter->filter_url);

                foreach ($eventData['events'] as $event){
                    $filterArr['filter']['id'][$event->external_id] = $event->external_id;
                }
                $filterArr['filter']['id'] = array_values($filterArr['filter']['id']);
                $filterStr = http_build_query($filterArr);
                $domain = $filter->account->domain;
                $amoToken = self::getAmoToken($domain);

                $deals = self::amoCurl(
                    endpoint: "https://{$domain}/api/v4/leads?{$filterStr}&with=contacts&order[created_at]=desc",
                    token: $amoToken
                );


                if (empty($deals)){
                    foreach ($eventData['events'] as $event){
                        $event->status = AmoEventStatus::REJECTED->value();
                        $event->date_end = date('Y-m-d H:i:s');
                        $event->save();
                    }
                    return;
                }
                $deletedLeads = [];
                $existLeads = [];

                foreach ($deals['_embedded']['leads'] as $lead){
                    $existLeads[$lead['id']] = true;
                }

                foreach ($filterArr['filter']['id'] as $leadID){
                    if (empty($existLeads[$leadID])) {
                        $deletedLeads[] = $leadID;
                    }
                }

                if (!empty($deletedLeads)){
                    self::removeDataFromSheet($domain, $connection->sheet_id, $deletedLeads);
                }

                $result = self::saveDataToSheet(
                    domain: $domain,
                    spreadsheetId: $connection->sheet_id,
                    sheetFields: $connection->sheet_fields,
                    pipelineID: $filter->pipeline_id,
                    deals: $deals['_embedded']['leads']
                );

                if (empty($result['error'])){
                    foreach ($eventData['events'] as $event){
                        $event->status = AmoEventStatus::COMPLETED->value();
                        $event->date_end = date('Y-m-d H:i:s');
                        $event->save();
                    }

                    $connection->date_sync = date('Y-m-d H:i:s');
                    $connection->save();
                }
            }

            $offset += $limit;
        }
    }
}
