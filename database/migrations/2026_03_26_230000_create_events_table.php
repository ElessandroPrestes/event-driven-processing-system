<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('event_name', 120);
            $table->json('payload');
            $table->json('metadata')->nullable();
            $table->string('status', 40);
            $table->string('idempotency_key')->unique();
            $table->string('content_hash', 64);
            $table->timestampTz('occurred_at')->nullable();
            $table->timestampTz('queued_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestampsTz();

            $table->index(['event_name', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
