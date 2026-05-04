<?php

namespace App\Traits;

trait EnumHelper
{
    public function value(): string|int
    {
        return $this->name;
    }

    public function jsonSerialize(): string|int
    {
        return $this->value();
    }
}
