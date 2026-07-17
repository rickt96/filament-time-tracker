<?php

namespace App\Filament\Pages\Tenancy;

use App\Actions\Workspace\CreateWorkspaceAction;
use App\Models\User;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\RegisterTenant;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class RegisterWorkspace extends RegisterTenant
{
    public static function getLabel(): string
    {
        return 'Crea workspace';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nome')
                    ->required()
                    ->maxLength(255),
                Textarea::make('description')
                    ->label('Descrizione')
                    ->maxLength(1000),
            ]);
    }

    protected function handleRegistration(array $data): Model
    {
        /** @var User $user */
        $user = Auth::user();

        return app(CreateWorkspaceAction::class)->handle($user, $data);
    }
}
