<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ProjectVisibility: string implements HasLabel
{
    case Public = 'public';
    case Private = 'private';

    public function getLabel(): string
    {
        return match ($this) {
            self::Public => 'Pubblico',
            self::Private => 'Privato',
        };
    }
}
