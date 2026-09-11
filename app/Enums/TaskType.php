<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TaskType: string implements HasColor, HasLabel
{
    case Activity = 'activity';
    case Bug = 'bug';
    case Feature = 'feature';
    case Improvement = 'improvement';

    public function getLabel(): string
    {
        return match ($this) {
            self::Activity => 'Attività',
            self::Bug => 'Bug',
            self::Feature => 'Funzionalità',
            self::Improvement => 'Miglioria',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Activity => 'gray',
            self::Bug => 'danger',
            self::Feature => 'info',
            self::Improvement => 'success',
        };
    }
}
