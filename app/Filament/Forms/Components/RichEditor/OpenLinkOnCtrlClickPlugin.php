<?php

namespace App\Filament\Forms\Components\RichEditor;

use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Support\Facades\FilamentAsset;
use Tiptap\Core\Extension;

/**
 * Lets ctrl/cmd+click open a link or mention inside the editor in a new tab,
 * without disturbing plain-click cursor placement (Filament's link mark and
 * the mention node both ship with openOnClick: false on purpose). Purely a
 * client-side interaction — no document schema change, so no PHP-side
 * TipTap extension is needed for the render/parse round trip.
 *
 * Estensione Tiptap custom registrata via il meccanismo ufficiale RichEditor::plugins():
 * - resources/js/rich-editor/open-link-on-ctrl-click.js: estensione Tiptap che aggiunge un plugin ProseMirror con handleClick — se il click porta ctrlKey/metaKey e il target è (o è dentro) un <a href>, apre il link in una nuova tab e blocca il posizionamento del cursore; altrimenti lascia fare il comportamento normale dell'editor. Copre sia i link che le mention (le mention con url risolto sono renderizzate come <a>, per come già verificato in MentionExtension), senza bisogno di due implementazioni separate.
 * - OpenLinkOnCtrlClickPlugin.php: implementa RichContentPlugin, espone l'estensione JS via FilamentAsset::getScriptSrc(). Nessuna estensione PHP-side: non cambia lo schema del documento, quindi non serve nulla per il round-trip di rendering/parsing server.
 * - AppServiceProvider::boot(): registra l'asset JS (FilamentAsset::register([Js::make(...)])), pubblicato con php artisan filament:assets in public/js/app/open-link-on-ctrl-click.js.
 * - TaskForm.php: ->plugins([OpenLinkOnCtrlClickPlugin::make()]) sul RichEditor della descrizione.
 */
class OpenLinkOnCtrlClickPlugin implements RichContentPlugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * @return array<Extension>
     */
    public function getTipTapPhpExtensions(): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    public function getTipTapJsExtensions(): array
    {
        return [
            FilamentAsset::getScriptSrc('open-link-on-ctrl-click'),
        ];
    }

    /**
     * @return array<RichEditorTool>
     */
    public function getEditorTools(): array
    {
        return [];
    }

    /**
     * @return array<Action>
     */
    public function getEditorActions(): array
    {
        return [];
    }
}
