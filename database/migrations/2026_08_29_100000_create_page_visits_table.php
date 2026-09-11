<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_visits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('visitable');
            $table->timestamp('last_opened_at');

            $table->unique(['user_id', 'visitable_id', 'visitable_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_visits');
    }
};
