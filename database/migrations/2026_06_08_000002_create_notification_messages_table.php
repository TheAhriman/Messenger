<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_messages', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('batch_id')->constrained('notification_batches')->cascadeOnDelete();
            $table->string('recipient_id')->index();
            $table->unsignedSmallInteger('status')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            // Идентификатор, выданный провайдером; по нему webhook находит уведомление.
            $table->string('provider_message_id')->nullable()->index();
            $table->string('error_message', 1024)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            // Одно сообщение получателю в рамках batch'а, даже при повторе запроса.
            $table->unique(['batch_id', 'recipient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_messages');
    }
};
