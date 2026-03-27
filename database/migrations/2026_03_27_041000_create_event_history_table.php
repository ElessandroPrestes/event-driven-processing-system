<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_history', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('event_id');
            $table->string('action', 80);
            $table->string('source', 40);
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40)->nullable();
            $table->json('context')->nullable();
            $table->timestampTz('created_at');

            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
            $table->index(['event_id', 'created_at']);
            $table->index(['action', 'source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_history');
    }
};
