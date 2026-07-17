<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TimeEntryStatus: string implements HasColor, HasLabel
{
    case Running = 'running';
    case Completed = 'completed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Running => 'In corso',
            self::Completed => 'Completato',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Running => 'warning',
            self::Completed => 'success',
        };
    }
}
