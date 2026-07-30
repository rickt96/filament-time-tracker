<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TaskStatus: string implements HasColor, HasLabel
{
    case Backlog = 'backlog';
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Test = 'test';
    case Done = 'done';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Backlog => 'Backlog',
            self::Todo => 'Todo',
            self::InProgress => 'In corso',
            self::Test => 'Test (QA)',
            self::Done => 'Completato',
            self::Cancelled => 'Annullato',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Backlog => 'gray',
            self::Todo => 'gray',
            self::InProgress => 'info',
            self::Test => 'warning',
            self::Done => 'success',
            self::Cancelled => 'danger',
        };
    }
}
