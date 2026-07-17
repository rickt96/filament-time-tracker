<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TaskStatus: string implements HasColor, HasLabel
{
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Done = 'done';

    public function getLabel(): string
    {
        return match ($this) {
            self::Todo => 'Da fare',
            self::InProgress => 'In corso',
            self::Done => 'Completato',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Todo => 'gray',
            self::InProgress => 'info',
            self::Done => 'success',
        };
    }
}
