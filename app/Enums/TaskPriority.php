<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TaskPriority: string implements HasColor, HasLabel
{
    case Alta = 'high'; // 
    case Media = 'medium';
    case Bassa = 'low'; // 20
    case NiceToHave = 'nice-to-have'; // 10

    public function getLabel(): string
    {
        return match ($this) {
            self::Alta => 'Alta',
            self::Media => 'Media',
            self::Bassa => 'Bassa',
            self::NiceToHave => 'Nice-to-have',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Alta => 'danger',
            self::Media => 'warning',
            self::Bassa => 'gray',
            self::NiceToHave => 'info',
        };
    }
}
