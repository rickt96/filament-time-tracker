<?php

namespace App\Services\Sync\Exceptions;

use RuntimeException;

/**
 * Thrown by ClickUpTaskImportService for any reason the import can't
 * complete (missing configuration, network failure, unknown ClickUp task,
 * already-imported task, ...) — the message is user-facing, shown as-is in
 * the import modal's error notification.
 */
class ClickUpImportException extends RuntimeException {}
