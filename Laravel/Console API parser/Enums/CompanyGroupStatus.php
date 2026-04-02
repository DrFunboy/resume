<?php
namespace App\Enums;

enum CompanyGroupStatus: string
{
    case ACTIVE = 'ACTIVE';

    case CLOSED = 'CLOSED';

    public function name(): string
    {
        return match ($this) {
            self::ACTIVE => 'Действующие',
            self::CLOSED => 'Ликвидированные',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
