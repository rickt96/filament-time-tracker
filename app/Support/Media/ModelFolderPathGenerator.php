<?php

namespace App\Support\Media;

use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

/**
 * Same layout as Spatie's own DefaultPathGenerator ({prefix}/{media id}/...),
 * with one folder prepended for the owning model: {model}/{media id}/... —
 * so every model's media lands under its own top-level folder (tasks/,
 * invoices/, ...) on the same disk, derived from the media's own
 * polymorphic model_type. One generic path_generator for the whole app,
 * instead of registering a PathGenerator per model in
 * config('media-library.custom_path_generators').
 */
class ModelFolderPathGenerator implements PathGenerator
{
    public function getPath(Media $media): string
    {
        return $this->getBasePath($media).'/';
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->getBasePath($media).'/conversions/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->getBasePath($media).'/responsive-images/';
    }

    protected function getBasePath(Media $media): string
    {
        $modelFolder = Str::plural(Str::kebab(class_basename($media->model_type)));

        $segments = array_filter([
            config('media-library.prefix', ''),
            $modelFolder,
            (string) $media->getKey(),
        ], fn (string $segment): bool => $segment !== '');

        return implode('/', $segments);
    }
}
