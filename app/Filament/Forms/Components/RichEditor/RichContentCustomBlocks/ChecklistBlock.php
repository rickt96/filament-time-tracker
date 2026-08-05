<?php

namespace App\Filament\Forms\Components\RichEditor\RichContentCustomBlocks;

use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\RichEditor\RichContentCustomBlock;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;

class ChecklistBlock extends RichContentCustomBlock
{
    public static function getId(): string
    {
        return 'checklist';
    }

    public static function getLabel(): string
    {
        return 'Checklist';
    }

    public static function configureEditorAction(Action $action): Action
    {
        return $action
            ->modalDescription(null)
            ->modalWidth('5xl')
            ->schema([
                /* TextInput::make('heading')
                    ->required(),
                TextInput::make('subheading'), */
                Repeater::make('items')
                    ->label('Attività')
                    ->schema([
                        Checkbox::make('is_completed')
                            ->label('Completato')
                            ->columnSpan(1),
                        TextInput::make('task')
                            ->hiddenLabel()
                            ->columnSpan(3),
                    ])
                    ->columns(4)
            ]);
    }

    // https://filamentphp.com/docs/5.x/forms/rich-editor#using-custom-blocks
    // FINIRE STO BLOCCO
    public static function toPreviewHtml(array $config): string
    {
        //dd($config);
        return view('filament.forms.components.rich-editor.rich-content-custom-blocks.hero.preview', [
            'items' => $config["items"]
        ])->render();
    }

    public static function toHtml(array $config, array $data): string
    {
        return view('filament.forms.components.rich-editor.rich-content-custom-blocks.hero.index', [
             'items' => $config["items"]
        ])->render();
    }
}
