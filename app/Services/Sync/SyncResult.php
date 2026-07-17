<?php

namespace App\Services\Sync;

readonly class SyncResult
{
    private function __construct(
        public bool $successful,
        public ?string $errorMessage,
    ) {}

    public static function success(): self
    {
        return new self(successful: true, errorMessage: null);
    }

    public static function failure(string $errorMessage): self
    {
        return new self(successful: false, errorMessage: $errorMessage);
    }
}
