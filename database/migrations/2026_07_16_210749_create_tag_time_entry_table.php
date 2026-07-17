<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tag_time_entry', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->foreignId('time_entry_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['tag_id', 'time_entry_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tag_time_entry');
    }
};
