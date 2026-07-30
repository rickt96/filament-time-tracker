<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ClientSyncDriver: string implements HasLabel
{
    case ClickUp = 'clickup';
    case Jira = 'jira';

    public function getLabel(): string
    {
        return match ($this) {
            self::ClickUp => 'ClickUp',
            self::Jira => 'Jira',
        };
    }

    public function defaultConfig(): array
    {
        return match ($this) {
            self::ClickUp => [
                'team' => '',
                'api_key' => '',
            ],
            self::Jira => [
                // TODO
            ],
        };
    }
}
