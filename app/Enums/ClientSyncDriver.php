<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ClientSyncDriver: string implements HasLabel
{
    case ClickUp = 'clickup';
    case Jira = 'jira';
    case H2Bit = 'h2bit';

    public function getLabel(): string
    {
        return match ($this) {
            self::ClickUp => 'ClickUp',
            self::Jira => 'Jira',
            self::H2Bit => 'H2Bit',
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
            self::H2Bit => [
                'base_url' => '',
                'organization' => '',
                'api_key' => '',
            ],
        };
    }
}
