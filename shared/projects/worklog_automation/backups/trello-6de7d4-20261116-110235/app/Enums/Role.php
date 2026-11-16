<?php

namespace App\Enums;

enum Role: string
{
    case Admin    = 'admin';
    case Employee = 'employee';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}