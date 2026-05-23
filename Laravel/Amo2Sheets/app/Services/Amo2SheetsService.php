<?php

namespace App\Services;

use App\Enums\AmoEventStatus;
use App\Enums\AmoEventType;
use App\Models\AmoAccount;
use App\Models\AmoConnection;
use App\Models\AmoEvent;
use App\Models\AmoFilter;
use App\Models\AmoFilterPipeline;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class Amo2SheetsService
{
    const string SHEETS_ENDPOINT = 'https://sheets.googleapis.com/v4/spreadsheets/';
    const string SHEET_NAME = 'Сделки';
    const int MAX_TRY_COUNT = 5;


    /**
     * Упрощенный curl
     * @param string $endpoint
     * @param string $token
     * @param array $body
     * @param string $method
     * @return bool|string
     */
    static function easyCurl(string $endpoint, string $token, array $body, string $method): bool|string
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

    /**
     * Упрощенный curl к ендпоинтам Google sheets
     * @param string $endpoint
     * @param string $token
     * @param array $body
     * @param string $method
     * @return array
     */
    static function googleCurl(string $endpoint, string $token, array $body = [], string $method = 'POST'): mixed
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

    /**
     * Упрощенный curl к ендпоинтам AmoCRM
     * @param string $endpoint
     * @param string $token
     * @param array $body
     * @param string $method
     * @return array
     */
    static function amoCurl(string $endpoint, string $token, array $body = [], string $method = 'POST'): array
    {
        $rawResponse = self::easyCurl($endpoint, $token, $body, $method);
        $response = json_decode($rawResponse, true);

        if (empty($response['_embedded'])){
            if (!empty($rawResponse)) {
                // Если не находит сделок, то endpoint ничего не возвращает, логировать это не нужно
                $logBody = json_encode([
                    'endpoint' => $endpoint,
                    'token' => $token,
                    'rawResponse' => $rawResponse,
                    'body' => $body,
                ]);
                Log::channel('amo')->error('AmoAPI ' . $logBody);
            }

            return ['error' => $rawResponse];
        }
        return $response;
    }

    /**
     * Преобразовывает строку с фильтрами со станицы воронки в массив фильтров для запроса к API
     * @param string $filter
     * @return array
     */
    static function parseAmoFilter(string $filter): array
    {
        $parsedArr = [];
        $filterArr = [];
        $filter = str_replace('?', '', $filter);
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

        // TODO брать настройку "оплачены ли фильтры" из БД
        if (!empty($filterArr['filter']['cf'])) {
            foreach ($filterArr['filter']['cf'] as $fieldName => $fieldParams){
                if (gettype($fieldParams) == 'array' ){
                    if (!empty($fieldParams['date_preset'])) {
                        $from = 0;
                        $to = time();
                        # Есть еще пресесеты, но их не используют
                        if (str_contains($fieldParams['date_preset'], 'previous_days_')){
                            $days = explode('_', $fieldParams['date_preset'])[2] ?? 0;
                            $from = strtotime("-{$days} days");
                        }
                        else {
                            Log::channel('amo')->alert('Unknown filter '.json_encode([
                                'name' => $fieldName,
                                'filter' => $filter
                            ]));
                            continue;
                        }
                        $parsedArr['filter']['custom_fields_values'][$fieldName]['from'] = $from;
                        $parsedArr['filter']['custom_fields_values'][$fieldName]['to'] = $to;

                    }
                    else if(!empty($fieldParams['from'])){
                        $parsedArr['filter']['custom_fields_values'][$fieldName]['from'] = $fieldParams['from'];
                    }
                    else if(!empty($fieldParams['to'])){
                        $parsedArr['filter']['custom_fields_values'][$fieldName]['to'] = $fieldParams['to'];
                    }
                    else if(!empty($fieldParams[0])){
                        $parsedArr['filter']['custom_fields_values'][$fieldName] = $fieldParams;
                    }
                    else {
                        Log::channel('amo')->alert('Unknown filter '.json_encode([
                            'name' => $fieldName,
                            'filter' => $filter
                        ]));
                        continue;
                    }
                }
                else {
                    $parsedArr['filter']['custom_fields_values'][$fieldName] = $fieldParams;
                }
            }
        }

        // TODO могут быть фильтры и по другим полям
        if (!empty($filterArr['filter_date_from'])) {
            $parsedArr['filter']['created_at']['from'] = strtotime($filterArr['filter_date_from']);
            $parsedArr['filter']['created_at']['to'] = strtotime($filterArr['filter_date_to'] ?? date('c'));
        }

        return $parsedArr;
    }

    /**
     * Получает и при необходимости обновляет токен для API Google
     * @param $domain
     * @param $code
     * @param $redirectUrl
     * @return bool|string
     */
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
            // Если у пользователя нет refresh токена, то генерирует ссылку на авторизацию
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

    /**
     * Удаляет токен для API Google
     * @param $domain
     * @return bool
     */
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

    /**
     * Получает и при необходимости обновляет токен для API AmoCRM
     * @param $domain
     * @param $code
     * @return bool|string
     */
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
     * Сохраняет $deals в Google таблицу
     * @param string $domain
     * @param string $spreadsheetId
     * @param array $sheetFields
     * @param array $pipelineIDs
     * @param array $deals
     * @param bool $force Не искать совпадения
     * @return array
     */
    static function saveDataToSheet(
        string $domain,
        string $spreadsheetId,
        array $sheetFields,
        array $pipelineIDs,
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
        static $oldPipelineIDs = $pipelineIDs;
        static $amoStatuses = [];
        static $amoPipeline = [];

        # Что бы не вызывать одни и те же запросы на больших выгрузках
        if ($oldPipelineIDs !== $pipelineIDs){
            unset($oldPipelineIDs, $amoStatuses, $amoPipeline);
            $oldPipelineIDs = $pipelineIDs;
            $amoStatuses = [];
            $amoPipelines = [];
        }

        if (empty($amoStatuses)) {
            foreach ($pipelineIDs as $pipelineID ){
                $amoPipeline = self::amoCurl(
                    endpoint: "https://{$domain}/api/v4/leads/pipelines/{$pipelineID}",
                    token: $amoToken
                );
                $amoPipelines[$amoPipeline['id']] = $amoPipeline['name'];
                foreach ($amoPipeline['_embedded']['statuses'] as $amoStatus) {
                    $amoStatuses[$amoStatus['id']] = $amoStatus['name'];
                }
            }
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
                if (empty($rawUsers['_links']['next'])){
                    break;
                }
                $nextPage++;
            }
            unset($rawUsers);
        }

        if (!$force){
            # Создание листа для поиска по id
            $newSheetName = 'MatchTab';
            $newSheet = self::googleCurl("$spreadsheetId:batchUpdate", $googleToken, [
                'requests' => ['addSheet' => ['properties' => ['title' => $newSheetName]]]
            ]);

            if (!empty($newSheet['error'])) {
                # Получение id листа если он существует
                $sheetData = self::googleCurl(
                    endpoint: $spreadsheetId,
                    token: $googleToken,
                    method: 'GET'
                );

                $newSheetID = null;
                foreach ($sheetData['sheets'] as $sheet){
                    if ($sheet['properties']['title'] === $newSheetName){
                        $newSheetID = $sheet['properties']['sheetId'];
                        break;
                    }
                }
                if (is_null($newSheetID)){
                    return $newSheet;
                }
            }
            else{
                $newSheetID = $newSheet['replies'][0]['addSheet']['properties']['sheetId'];
            }
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
                    case 'pipeline_name':
                        $dealFields[] = $amoPipelines[$deal['pipeline_id']] ?? '';
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
                                    if ($customField['field_type'] == 'numeric'){
                                        $customFieldValue = floatval($customFieldValue);
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

        # Добавление новых в начало
        self::googleCurl(
            endpoint: "{$spreadsheetId}/values/{$sheetName}!A3:append?valueInputOption=USER_ENTERED&insertDataOption=INSERT_ROWS",
            token: $googleToken,
            body: ['values' => $appendDeals]
        );

        return ['success' => true];
    }

    /**
     * Ищет данные по идентификаторам и удаляет их из Google таблицы
     * @param string $domain
     * @param string $spreadsheetId
     * @param array $ids
     * @return array
     */
    static function removeDataFromSheet(string $domain, string $spreadsheetId, array $ids): array
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

    /**
     * Постранично выгружает данные из воронки в Google таблицу
     * @param string $domain
     * @param string $connectionUUID
     * @param int $page
     * @return array
     */
    public static function syncConnection(string $domain, string $connectionUUID, int $page = 1): array
    {
        /** @var AmoConnection $connection */
        $connection = AmoConnection::query()->where('uuid', $connectionUUID)->first();
        if (empty($connection)) {
            return ['error' => 'Выгрузка не найдена'];
        }

        $pipelineIDs = $connection->filter->pipelines()->pluck('pipeline_id')->all();
        if (empty($pipelineIDs)){
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
        $filterStr = http_build_query($filter);

        $dealsExist = self::amoCurl(
            endpoint: "https://{$domain}/api/v4/leads?{$filterStr}&order[created_at]=desc&limit=1",
            token: $amoToken
        );
        if (!empty($dealsExist['error'])) {
            return ['error' => 'Сделок для экспорта не найдено'];
        }

        # Если первая страница, то очищаем все строки, кроме заголовка
        if ($page === 1){
            $clearSheet = self::googleCurl(
                endpoint: $connection->sheet_id . '/values:batchClear',
                token: $googleToken,
                body: ['ranges' => [self::SHEET_NAME . '!A2:Z']]
            );

            if (!empty($clearSheet['error'])) {
                return ['error' => 'Лист ' . self::SHEET_NAME . ' не удалось очистить'];
            }
        }

        $deals = self::amoCurl(
            endpoint: "https://{$domain}/api/v4/leads?{$filterStr}&with=contacts&order[created_at]=asc&page={$page}",
            token: $amoToken
        );

        $saveResult = self::saveDataToSheet(
            domain: $domain,
            spreadsheetId: $connection->sheet_id,
            sheetFields: $connection->sheet_fields,
            pipelineIDs: $pipelineIDs,
            deals: $deals['_embedded']['leads'],
            force: true
        );

        if (!empty($saveResult['error'])){
            return [
                'next_page' => false,
                'error' => $saveResult['error']
            ];
        }

        $connection->date_sync = date('Y-m-d H:i:s');
        $connection->save();

        return [
            'next_page' => !(empty($deals['_links']['next'])),
            'count_done' => count($deals['_embedded']['leads'])
        ];
    }

    /**
     * Ищет выгрузки к которым относится вебхук и сохраняет его
     * @param string $domain
     * @param array $events
     * @return array
     */
    public static function saveEvents(string $domain, array $events): array
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

                    $connections = $account->connections()->whereHas('filter', function ($filter) use ($lead) {
                        $filter->whereHas('pipelines', function ($filterPipeline) use ($lead) {
                            $filterPipeline->where('pipeline_id', $lead['pipeline_id']);
                        });
                    })->count();
                    if ($connections == 0) {
                        continue;
                    }

                    $newEvent = new AmoEvent();
                    $newEvent->external_id = $lead['id'];
                    $newEvent->pipeline_id = $lead['pipeline_id'];
                    $newEvent->type = AmoEventType::LEAD->value();
                    $newEvent->status = AmoEventStatus::WAITING->value();
                    $newEvent->event_body = $lead;
                    $newEvent->save();
                }
            }
        }

        return [];
    }

    /**
     * Обрабатывает вебхуки и сохраняет данные в Google таблицы
     * @return void
     */
    public static function processEvents(): void
    {
        # TODO: Не выполнять если идёт полная синхронизация
        # TODO: Добавить лимит кол-ва выбираемых строк за запуск, 5к-15к мб
        # TODO: Подумать, что делать если лимит у Amo изменится

        # Выбирает вебхуки постранично, т.к. у Amo есть лимит в 250 записей за запрос
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
                                ['date_start', '<=', date('Y-m-d H:i:s', strtotime('-15 min'))]
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

            # Выбирает все выгрузки к которым относится вебхук
            $byFilter = [];
            /** @var AmoEvent $event */
            foreach ($events as $event){
                $hasConnection = false;
                /** @var AmoFilter $filter */
                foreach ($event->filters as $filter) {
                    /** @var AmoConnection $connection */
                    foreach ($filter->connections as $connection){
                        if ($connection->active) {
                            $hasConnection = true;
                            $byFilter[$filter->id]['filter'] = $filter;
                            $byFilter[$filter->id]['events'][$event->id] = $event;
                            $byFilter[$filter->id]['connections'][$connection->id] = $connection;
                            $event->status = AmoEventStatus::PROCESSING->value();
                        }
                    }
                }

                if (!$hasConnection) {
                    $event->status = AmoEventStatus::REJECTED->value();
                }

                $event->date_start = date('Y-m-d H:i:s');
                $event->try_count = $event->try_count + 1;
                $event->save();
            }

            # Ищет сделки в Amo, обновляет, добавляет или удаляет их в Google Таблице
            foreach ($byFilter as $eventData){
                /** @var AmoFilter $filter */
                $filter = $eventData['filter'];
                $filterArr = self::parseAmoFilter($filter->filter_url);

                foreach ($eventData['events'] as $event){
                    $filterArr['filter']['id'][$event->external_id] = $event->external_id;
                }
                $filterArr['filter']['id'] = array_values($filterArr['filter']['id']);
                $filterStr = http_build_query($filterArr);
                $domain = $filter->account->domain;
                $amoToken = self::getAmoToken($domain);

                $deals = self::amoCurl(
                    endpoint: "https://{$domain}/api/v4/leads?{$filterStr}&with=contacts&order[created_at]=asc",
                    token: $amoToken
                );

                if (!empty($deals['error'])){
                    foreach ($eventData['events'] as $event){
                        $event->status = AmoEventStatus::REJECTED->value();
                        $event->date_end = date('Y-m-d H:i:s');
                        $event->save();
                    }
                    continue;
                }
                $deletedLeads = [];
                $existLeads = [];
                $leads = $deals['_embedded']['leads'] ?? [];

                foreach ($leads as $lead){
                    $existLeads[$lead['id']] = true;
                }

                foreach ($filterArr['filter']['id'] as $leadID){
                    if (empty($existLeads[$leadID])) {
                        $deletedLeads[] = $leadID;
                    }
                }


                foreach ($eventData['connections'] as $connection){
                    if (!empty($deletedLeads)){
                        self::removeDataFromSheet($domain, $connection->sheet_id, $deletedLeads);
                        AmoEvent::query()->whereIn('external_id', $deletedLeads)
                            ->update([
                                'status' => AmoEventStatus::COMPLETED->value(),
                                'date_end' => date('Y-m-d H:i:s')
                            ]);
                    }

                    if (!empty($leads)) {
                        $hasError = false;
                        $result = self::saveDataToSheet(
                            domain: $domain,
                            spreadsheetId: $connection->sheet_id,
                            sheetFields: $connection->sheet_fields,
                            pipelineIDs: $filter->pipelines()->pluck('pipeline_id')->all(),
                            deals: $leads
                        );
                        if (!empty($result['error'])) {
                            $hasError = true;
                        }
                        if (!$hasError){
                            foreach ($eventData['events'] as $event){
                                $event->status = AmoEventStatus::COMPLETED->value();
                                $event->date_end = date('Y-m-d H:i:s');
                                $event->save();
                            }
                        }
                    }

                    $connection->date_sync = date('Y-m-d H:i:s');
                    $connection->save();
                }
            }

            $offset += $limit;
        }
    }
}
