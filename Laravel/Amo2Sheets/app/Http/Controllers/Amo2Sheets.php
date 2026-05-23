<?php

namespace App\Http\Controllers;

use App\Http\Resources\AmoConnectionResource;
use App\Http\Resources\AmoFilterResource;
use App\Models\AmoAccount;
use App\Models\AmoConnection;
use App\Models\AmoFilter;
use App\Services\Amo2SheetsFields;
use App\Services\Amo2SheetsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Http\Response;

class Amo2Sheets extends Controller
{
    const string REQUIRED_SCOPE = 'https://www.googleapis.com/auth/spreadsheets';

    public function install(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'referer' => ['required', 'string'],
            'client_id' => ['required', 'string'],
            'code' => ['required', 'string'],
        ]);
        $domain = $validated['referer'];

        /** @var AmoAccount $account */
        $account = AmoAccount::query()->where(['domain' => $domain])->first();
        if (empty($account)){
            $account = new AmoAccount();
            $account->domain = $domain;
        }
        $account->client_id = $validated['client_id'];
        $account->save();

        Amo2SheetsService::getAmoToken($domain, $validated['code']);

        return self::success();
    }

    public function uninstall(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_uuid' => ['required', 'string'],
            'signature' => ['required', 'string'],
            'hook_reason' => ['required', 'string'],
            'account_id' => ['required', 'string'],
        ]);

        if ($validated['hook_reason'] !== 'integration_uninstalled'){
            return self::error('Invalid hook reason');
        }

        /** @var AmoAccount $account */
        $account = AmoAccount::query()->where(['client_id' => $validated['client_uuid']])->first();

        if (empty($account)){
            return self::error('Account not found');
        }

        if (!hash_equals(
            hash_hmac(
                'sha256', sprintf('%s|%s', $validated['client_uuid'], $validated['account_id']),
                $account->amo_secret
            ),
            $validated['signature']
        )) {
            return self::error('Invalid signature');
        }

        $account->amo_refresh = null;
        $account->save();

        Cache::delete('amo_access_' . $account->domain);
        Amo2SheetsService::revokeGoogleToken($account->domain);

        return self::success();
    }


    public function filters(Request $request): JsonResponse
    {
        $collection = AmoFilterResource::collection(
            AmoFilter::query()->whereHas('account', function (Builder $q) use ($request) {
                $q->where('domain', $request->domain);
            })->get()
        );

        return self::success(['filters' => $collection]);
    }

    public function storeFilter(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'uuid' => ['nullable', 'string'],
            'name' => ['required', 'string'],
            'comment' => ['nullable', 'string'],
            'amouser_id' => ['required', 'integer'],
            'filter_url' => ['required', 'string'],
        ]);

        if (!empty($validated['uuid'])) {
            $model = AmoFilter::query()->where('uuid', $validated['uuid'])->first();
        }

        if (empty($model)){
            $model = new AmoFilter();
        }
        $pipelineIDs = [];
        $filterArr = [];
        $filter = str_replace('?', '', $validated['filter_url']);
        parse_str($filter, $filterArr);



        if (!empty($filterArr['filter']['pipe'])) {
            foreach ($filterArr['filter']['pipe'] as $pipelineID => $statuses){
                $pipelineIDs[$pipelineID] = ['pipeline_id' => $pipelineID];
            }
        }
        else {
            return self::error('Empty pipelines');
        }

        $model->account_id = $request->amoAccount->id;
        $model->name = $validated['name'];
        $model->comment = $validated['comment'] ?? null;
        $model->filter_url = $validated['filter_url'];
        $model->amo_author = $validated['amouser_id'];
        $model->save();

        $model->pipelines()->createMany($pipelineIDs);

        return self::success();
    }

    public function deleteFilter(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'uuid' => ['required', 'string'],
        ]);

        $model = AmoFilter::query()->where('uuid', $validated['uuid'])->first();
        if (empty($model)){
            return self::success();
        }
        $model->delete();

        return self::success();
    }


    public function connections(Request $request): JsonResponse
    {
        $collection = AmoConnectionResource::collection(
            $request->amoAccount->connections
        );

        return self::success(['connections' => $collection]);
    }

    public function storeConnection(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'uuid' => ['nullable', 'string'],
            'amouser_id' => ['required', 'integer'],
            'filter_id' => ['required', 'integer'],
            'sheet_fields' => ['required', 'array'],
            'sheet_fields.*.name' => ['required', 'string'],
            'sheet_fields.*.type' => ['required', 'string'],
            'sheet_fields.*.order' => ['required', 'integer'],
            'sheet_fields.*.custom' => ['required', 'integer'],
            'active' => ['required', 'boolean'],
        ]);

        foreach ($validated['sheet_fields'] as &$v){
            $v['order'] = intval($v['order']);
            $v['custom'] = intval($v['custom']);
        }

        if (!empty($validated['uuid'])) {
            $model = AmoConnection::query()->where('uuid', $validated['uuid'])->first();
        }

        if (empty($model)){
            $model = new AmoConnection();
            $validated += $request->validate([
                'sheet_id' => ['required', 'string', 'unique:amo_connection,sheet_id'],
            ]);
        }
        else {
            $validated += $request->validate([
                'sheet_id' => [
                    'required',
                    'string',
                    Rule::unique('amo_connection', 'sheet_id')->ignore($model->id)
                ],
            ]);
        }

        $model->account_id = $request->amoAccount->id;
        $model->filter_id = $validated['filter_id'];
        $model->sheet_id = $validated['sheet_id'];
        $model->sheet_fields = $validated['sheet_fields'];
        $model->amo_author = $validated['amouser_id'];
        $model->active = $validated['active'];
        $model->save();

        return self::success();
    }

    public function deleteConnection(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'uuid' => ['required', 'string'],
        ]);

        $model = AmoConnection::query()->where('uuid', $validated['uuid'])->first();
        if (empty($model)){
            return self::success();
        }
        $model->delete();

        return self::success();
    }

    public function syncConnection(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'uuid' => ['required', 'string'],
            'page' => ['nullable', 'integer']
        ]);
        $domain = $request->amoAccount->domain;
        $syncStatus = Amo2SheetsService::syncConnection($domain, $validated['uuid'], intval($validated['page']));

        if (!empty($syncStatus['error'])){
            return self::error($syncStatus['error']);
        }

        return self::success($syncStatus);
    }

    public function fullData(Request $request): JsonResponse
    {
        $filters = AmoFilterResource::collection(
            $request->amoAccount->filters
        );

        $connections = AmoConnectionResource::collection(
            $request->amoAccount->connections
        );

        return self::success([
            'filters' => $filters,
            'connections' => $connections,
            'connection_fields' => Amo2SheetsFields::$fields
        ]);
    }

    public function oauthComplete(Request $request): Response
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
            'state' => ['required', 'string'],
            'scope' => ['required', 'string', Rule::in([self::REQUIRED_SCOPE])],
        ]);

        Amo2SheetsService::getGoogleToken(
            $validated['state'],
            $validated['code'],
            $request->getSchemeAndHttpHost().'/api/sheets/oauth'
        );

        $content = "<script>
            if (window.opener) {
                window.opener.postMessage('auth_success', 'https://" . $validated['state'] . "');
            }

            window.close();
        </script>";

        return response($content);

    }

    public function oauthCheck(Request $request): JsonResponse
    {
        $domain = $request->amoAccount->domain;
        $token = Amo2SheetsService::getGoogleToken($domain);

        if ($token) {
            return self::success([
                'auth' => true,
            ]);
        }
        else {
            $url = 'https://accounts.google.com/o/oauth2/v2/auth?';
            $params = [
                'client_id' => env('GOOGLE_CLIENT_ID'),
                'redirect_uri' => $request->getSchemeAndHttpHost().'/api/sheets/oauth',
                'response_type' => 'code',
                'scope' => self::REQUIRED_SCOPE,
                'access_type' => 'offline',
                'state' => $domain
            ];
            return self::success([
                'url' => $url.http_build_query($params),
            ]);
        }
    }

    public function oauthLogout(Request $request): JsonResponse
    {
        $domain = $request->amoAccount->domain;
        Amo2SheetsService::revokeGoogleToken($domain);

        return self::success();
    }

    public function addEvent(Request $request): JsonResponse
    {
        $account = $request->account ?? [];
        $leads = $request->leads ?? [];
        if (empty($account) || empty($leads)) {
            Log::channel('amo')->alert('Event ' . json_encode($_REQUEST));
            return self::success();
        }

        $domain = $account['subdomain'].'.amocrm.ru';
        Amo2SheetsService::saveEvents($domain, [
            'leads' => $leads
        ]);
        return self::success();
    }

}
