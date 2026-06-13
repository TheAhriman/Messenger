<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_batches', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            // Источник истины для дедубликации запросов (exactly-once приём).
            $table->string('idempotency_key')->unique();
            $table->unsignedSmallInteger('channel');
            $table->unsignedSmallInteger('priority');
            $table->text('text');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_batches');
    }
};
