<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * workspace_id is filtered on every single tenant-scoped query in the app
 * (every Resource's getEloquentQuery()). The foreignId()->constrained() FK
 * only guarantees an index on MySQL — SQLite/Postgres don't create one for
 * free — so it's added explicitly here rather than relied upon implicitly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->index('workspace_id');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->index('workspace_id');
        });

        Schema::table('tags', function (Blueprint $table) {
            $table->index('workspace_id');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex(['workspace_id']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['workspace_id']);
        });

        Schema::table('tags', function (Blueprint $table) {
            $table->dropIndex(['workspace_id']);
        });
    }
};
