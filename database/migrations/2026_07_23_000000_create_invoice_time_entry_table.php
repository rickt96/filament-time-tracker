<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_time_entry', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('time_entry_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['invoice_id', 'time_entry_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_time_entry');
    }
};
