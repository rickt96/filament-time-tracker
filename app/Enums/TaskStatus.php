<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Mokhosh\FilamentKanban\Concerns\IsKanbanStatus;

enum TaskStatus: string implements HasColor, HasLabel
{
    /** Turns the cases into the columns of TasksKanbanBoard. */
    use IsKanbanStatus;

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
            self::Test => 'Testing',
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

    /**
     * Column heading on the kanban board — `IsKanbanStatus` would otherwise
     * fall back to the raw case value.
     */
    public function getTitle(): string
    {
        return $this->getLabel();
    }

    /**
     * Solo gli stati "attivi" di lavorazione finiscono in kanban: Done e
     * Cancelled non hanno colonna, quindi un task che ci finisce sparisce
     * dalla board.
     *
     * @return array<int, self>
     */
    public static function kanbanCases(): array
    {
        return [
            self::Backlog,
            self::Todo,
            self::InProgress,
            self::Test,
        ];
    }
}
