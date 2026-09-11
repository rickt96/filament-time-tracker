<?php

namespace App\Filament\Widgets\Concerns;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;

/**
 * Risolve tipo / titolo / URL di un record morphable per i widget tabella.
 * L'URL punta alla resource Filament del record (view, poi edit).
 */
trait ResolvesRecordLink
{
    protected function typeLabelFor(?Model $model, string $fallbackType): string
    {
        $resource = $model ? Filament::getModelResource($model) : null;

        return $resource
            ? (string) $resource::getModelLabel()
            : class_basename($fallbackType);
    }

    protected function titleFor(?Model $model): string
    {
        if (! $model) {
            return '(eliminato)';
        }

        // ponytail: name/title copre i model attuali; aggiungi un contratto se uno non ha nessuno dei due
        return $model->name ?? $model->title ?? class_basename($model).' #'.$model->getKey();
    }

    protected function linkFor(?Model $model): ?string
    {
        $resource = $model ? Filament::getModelResource($model) : null;

        if (! $model || ! $resource) {
            return null;
        }

        foreach (['view', 'edit'] as $page) {
            if ($resource::hasPage($page)) {
                return $resource::getUrl($page, ['record' => $model], tenant: Filament::getTenant());
            }
        }

        return null;
    }
}
