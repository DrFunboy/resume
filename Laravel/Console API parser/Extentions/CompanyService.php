<?php

namespace App\Extentions;

use App\Enums\CompanyGroupStatus;
use App\Models\Aggregate;
use App\Models\Company;
use App\Models\CompanyStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CompanyService
{
    const OKVED_LIST = [
        '01.1',
        '01.2',
        '01.3',
    ];

    const REGION_LIST = ['52'];

    const DATE_START = '2022-01-01';

    public static function parseFNS($page = 1, $okvedIndex = 0, $regionIndex = 0): void
    {
        $statuses = CompanyStatus::STATUSES;
        $okvedValue = self::OKVED_LIST[$okvedIndex ?: 0];
        $regionValue = self::REGION_LIST[$regionIndex ?: 0];
        $url = 'https://api-fns.ru/api/search?';

        $filter = ['datereg>01.01.2002<' . date('31.12.Y')];
        $filter[] = 'okved' . $okvedValue;
        $filter[] = 'region' . $regionValue;

        $url .= http_build_query([
            'q' => 'any',
            'key' => config('services.fns.key'),
            'page' => $page,
            'filter' => implode('+', $filter)
        ]);
        $response = Http::get($url);
        $responseBody = $response->json();

        if ($response->status() != 200 || empty($responseBody)){
            Log::channel('fns')->error($response->body());
            return;
        }

        if (!empty($responseBody['items'])){
            foreach ($responseBody['items'] as $company){
                $companyData = $company['ЮЛ'] ?? $company['ИП'];
                $parsedStatus = [];
                foreach ($statuses as $status){
                    if (mb_strtolower($status['raw_status']) == mb_strtolower($companyData['Статус'])){
                        $parsedStatus = $status;
                        break;
                    }
                }

                $model = Company::query()->where(
                    ['inn' => $companyData['ИНН']]
                )->first();

                if (empty($model)) {
                    $model = new Company;
                    $model->inn = $companyData['ИНН'];
                    $model->region_code = $regionValue;
                    $model->main_okved = $okvedValue;
                    $model->registration_date = $companyData['ДатаРег'] ?? $companyData['ДатаОГРН'];
                }

                $model->raw_status = $companyData['Статус'];

                if (!empty($companyData['ДатаПрекр'])){
                    $model->liquidation_date = $companyData['ДатаПрекр'];
                }

                if (!empty($parsedStatus)){
                    $model->status_code = $parsedStatus['status'];
                    $model->status_group = $parsedStatus['group_name'];
                }
                else {
                    Log::channel('fns')->alert("Unknown status {$companyData['Статус']}. ".json_encode($company));
                }

                try {
                    $model->save();
                }
                catch (\Throwable $e) {
                    Log::channel('fns')->error('Company not saved. '.$e->getMessage());
                }
            }
        }

        # Если не последняя страница
        if (!empty($responseBody['nextpage'])){
           self::parseFNS($page+1, $okvedIndex, $regionIndex);
        }
        # Если страница последняя, но запрошены не все ОКВЭДы
        elseif ($okvedIndex+1 < count(self::OKVED_LIST)) {
            self::parseFNS(1, $okvedIndex+1, $regionIndex);
        }
        # Если страница последняя, запрошены все ОКВЭДы, но не все регионы
        elseif ($regionIndex+1 < count(self::REGION_LIST)) {
            self::parseFNS(1, 0, $regionIndex+1);
        }
        # Агрегирование данных
        else {
            self::aggregateData();
        }
    }

    public static function aggregateData(): void
    {
        $dateStart = self::DATE_START;
        $closed = DB::table('companies')
            ->selectRaw("
                COUNT(*) as companies_count,
	            status_code,
	            status_group,
	            date_part('year', liquidation_date) AS liquidation_year
            ")
            ->where('status_group', CompanyGroupStatus::CLOSED)
            ->where('liquidation_date', '>=', self::DATE_START)
            ->groupBy('status_code', 'status_group', 'liquidation_year')
            ->orderBy('liquidation_year')
            ->get()
            ->toArray();

        foreach ($closed as $row){
            $model = Aggregate::query()->where(
                [
                    'year' => $row->liquidation_year,
                    'status_code' => $row->status_code
                ]
            )->first();

            if (empty($model)) {
                $model = new Aggregate();
                $model->year = $row->liquidation_year;
                $model->status_code = $row->status_code;
                $model->status_group = $row->status_group;
                $model->data_version = 0;
            }
            $model->companies_count = $row->companies_count;
            $model->data_version = $model->data_version+1;

            try {
                $model->save();
            }
            catch (\Throwable $e) {
                Log::channel('fns')->error('Aggregate not saved. '.$e->getMessage());
            }
        }


        $active = DB::table('companies')
            ->selectRaw("
                COUNT(*) as companies_count,
                status_code,
                date_part('year',
                    CASE WHEN registration_date < '{$dateStart}'
                    THEN '{$dateStart}'
                    ELSE registration_date
                    END
                ) AS registration_year
            ")
            ->where('status_group', CompanyGroupStatus::ACTIVE)
            ->groupBy('status_code', 'registration_year')
            ->orderBy('registration_year')
            ->get()
            ->toArray();

        $activeByYear = [];

        # Суммирует показатели по статусам за все предыдущие года
        foreach ($active as $row){
            $prevYear = $row->registration_year-1;
            $currentStatus = $row->status_code;
            $prevCnt = $activeByYear[$prevYear][$currentStatus] ?? 0;
            $currentCnt = $row->companies_count + $prevCnt;

            # Если в предыдущем году есть статус, которого нет в этом
            if (isset($activeByYear[$prevYear])) {
                foreach ($activeByYear[$prevYear] as $status => $statusCnt){
                    if ($status != $currentStatus && empty($activeByYear[$row->registration_year][$status])){
                        $activeByYear[$row->registration_year][$status] = $statusCnt;
                    }
                }
            }

            $activeByYear[$row->registration_year][$currentStatus] = $currentCnt;
        }

        // Агрегирует по годам
        $companyStatuses = CompanyStatus::getStatuses();
        foreach ($activeByYear as $year => $yearStatuses){
            foreach ($yearStatuses as $status => $companies_count) {
                $model = Aggregate::query()->where(
                    [
                        'year' => $year,
                        'status_code' => $status
                    ]
                )->first();

                if (empty($model)) {
                    $model = new Aggregate();
                    $model->year = $year;
                    $model->status_code = $status;
                    $model->status_group = $companyStatuses[$status]['group_name'];
                    $model->data_version = 0;
                }
                $model->companies_count = $companies_count;
                $model->data_version = $model->data_version+1;

                try {
                    $model->save();
                }
                catch (\Throwable $e) {
                    Log::channel('fns')->error('Aggregate not saved. '.$e->getMessage());
                }
            }
        }

    }
}
