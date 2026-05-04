<?php

namespace App\Enums;

use App\Traits\EnumHelper;
use JsonSerializable;

enum AmoEventType implements JsonSerializable
{
    use EnumHelper;
    case LEAD;
    case CONTACT;

    public function name(): string
    {
        return match ($this) {
            self::LEAD => 'Сделка',
            self::CONTACT => 'Контакт'
        };
    }
}
