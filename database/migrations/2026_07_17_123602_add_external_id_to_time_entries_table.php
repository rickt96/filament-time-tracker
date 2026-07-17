<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirrors Task.external_id: lets an imported/synced Time Entry (e.g. from
 * Clockify) be traced back to its source record, so re-running an import
 * can skip entries it already created instead of duplicating them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->string('external_id')->nullable()->after('sync_error');
            $table->index('external_id');
        });
    }

    public function down(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->dropIndex(['external_id']);
            $table->dropColumn('external_id');
        });
    }
};
