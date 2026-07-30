<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TimeEntrySyncStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Synced = 'synced';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'In attesa',
            self::Synced => 'Sincronizzato',
            self::Failed => 'Fallito'
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Synced => 'success',
            self::Failed => 'danger'
        };
    }
}
