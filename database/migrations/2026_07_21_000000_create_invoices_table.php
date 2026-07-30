<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('number');
            $table->decimal('amount', 10, 2);
            $table->string('status')->default('draft');
            $table->date('sent_at')->nullable();
            $table->date('collected_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['workspace_id', 'year', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
