<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum WorkPackageStatus: string implements HasColor, HasLabel
{
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case OnHold = 'on_hold';

    public function getLabel(): string
    {
        return match ($this) {
            self::Planned => 'Pianificato',
            self::InProgress => 'In corso',
            self::Completed => 'Completato',
            self::OnHold => 'In pausa',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Planned => 'gray',
            self::InProgress => 'info',
            self::Completed => 'success',
            self::OnHold => 'warning',
        };
    }
}
