<?php

namespace App\Models;

use App\Enums\CompanyGroupStatus;

class CompanyStatus
{
    public const STATUSES = [
        [
            'status' => 'ACTIVE',
            'raw_status' => 'Действующее',
            'group_name' => CompanyGroupStatus::ACTIVE
        ],
        [
            'status' => 'BANKRUPTCY',
            'raw_status' => 'Юридическое лицо признано несостоятельным (банкротом) и в отношении него открыто конкурсное производство',
            'group_name' => CompanyGroupStatus::ACTIVE
        ],
        [
            'status' => 'CHANGE_ADDRESS',
            'raw_status' => 'Юридическим лицом принято решение об изменении места нахождения',
            'group_name' => CompanyGroupStatus::ACTIVE
        ],
        [
            'status' => 'LIQUIDATING',
            'raw_status' => 'Находится в стадии ликвидации',
            'group_name' => CompanyGroupStatus::ACTIVE
        ],
        [
            'status' => 'EXCLUDING_INACTIVE',
            'raw_status' => 'Принято решение о предстоящем исключении недействующего юридического лица из ЕГРЮЛ',
            'group_name' => CompanyGroupStatus::ACTIVE
        ],
        [
            'status' => 'EXCLUDING_INACCURATE',
            'raw_status' => 'Регистрирующим органом принято решение о предстоящем исключении юридического лица из ЕГРЮЛ (наличие в ЕГРЮЛ сведений о недостоверности)',
            'group_name' => CompanyGroupStatus::ACTIVE
        ],
        [
            'status' => 'EXCLUDING_INACCURATE',
            'raw_status' => 'Регистрирующим органом принято решение о предстоящем исключении юридического лица из ЕГРЮЛ (наличие в ЕГРЮЛ сведений о юридическом лице, в отношении которых внесена запись о недостоверности)',
            'group_name' => CompanyGroupStatus::ACTIVE
        ],
        [
            'status' => 'EXCLUDING_129FZ',
            'raw_status' => 'Регистрирующим органом принято решение о предстоящем исключении юридического лица из ЕГРЮЛ (наличие оснований, предусмотренных статьей 21.3 федерального закона от 08.08.2001 № 129-фз)',
            'group_name' => CompanyGroupStatus::ACTIVE
        ],
        [
            'status' => 'MONITORING',
            'raw_status' => 'В отношении юридического лица в деле о несостоятельности (банкротстве) введено наблюдение',
            'group_name' => CompanyGroupStatus::ACTIVE
        ],
        [
            'status' => 'LIQUIDATED',
            'raw_status' => 'Ликвидировано',
            'group_name' => CompanyGroupStatus::CLOSED
        ],
        [
            'status' => 'LIQUIDATED_BY_129FZ',
            'raw_status' => 'Ликвидировано по 129-ФЗ',
            'group_name' => CompanyGroupStatus::CLOSED
        ],
        [
            'status' => 'LIQUIDATED_BY_COURT',
            'raw_status' => 'Ликвидировано по суду',
            'group_name' => CompanyGroupStatus::CLOSED
        ],
        [
            'status' => 'REORG_JOIN',
            'raw_status' => 'Прекратило деятельность (реорганизовано в форме присоединения)',
            'group_name' => CompanyGroupStatus::CLOSED
        ],
        [
            'status' => 'REORG_CONVERT',
            'raw_status' => 'Прекратило деятельность (реорганизовано в форме преобразования)',
            'group_name' => CompanyGroupStatus::CLOSED
        ],
        [
            'status' => 'CEASED',
            'raw_status' => 'Прекратило деятельность',
            'group_name' => CompanyGroupStatus::CLOSED
        ],
    ];

    public static function getStatuses():array
    {
        return array_combine(array_column(self::STATUSES, 'status'), self::STATUSES);
    }
}
