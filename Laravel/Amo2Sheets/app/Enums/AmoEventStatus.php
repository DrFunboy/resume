<?php

namespace App\Enums;

use App\Traits\EnumHelper;
use JsonSerializable;

enum AmoEventStatus implements JsonSerializable
{
    use EnumHelper;
    case WAITING;
    case PROCESSING;
    case COMPLETED;
    case REJECTED;

    public function name(): string
    {
        return match ($this) {
            self::WAITING => 'Ожидает',
            self::PROCESSING => 'Обрабатывается',
            self::COMPLETED => 'Обработано',
            self::REJECTED => 'Отклонено'
        };
    }
}
