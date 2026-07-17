<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum WorkspaceRole: string implements HasLabel
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';

    public function getLabel(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Admin => 'Admin',
            self::Member => 'Member',
        };
    }

    public function canManageWorkspace(): bool
    {
        return match ($this) {
            self::Owner, self::Admin => true,
            self::Member => false,
        };
    }
}
