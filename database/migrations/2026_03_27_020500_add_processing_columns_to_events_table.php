<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->timestampTz('consumed_at')->nullable()->after('queued_at');
            $table->timestampTz('processed_at')->nullable()->after('consumed_at');
            $table->unsignedSmallInteger('processing_attempts')->default(0)->after('processed_at');
            $table->json('processing_result')->nullable()->after('processing_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn([
                'consumed_at',
                'processed_at',
                'processing_attempts',
                'processing_result',
            ]);
        });
    }
};
