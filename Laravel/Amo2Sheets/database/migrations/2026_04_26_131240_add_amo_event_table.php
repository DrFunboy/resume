<?php

use App\Enums\AmoEventType;
use App\Enums\AmoEventStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('amo_event', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')
                ->comment('id Записи из Amo');
            $table->enum('type', AmoEventType::cases())
                ->default(AmoEventType::LEAD->value())
                ->comment('Тип записи из Amo');
            $table->enum('status', AmoEventStatus::cases())
                ->default(AmoEventStatus::WAITING->value())
                ->comment('Статус обработки');
            $table->foreignId('connection_id')
                ->constrained(table: 'amo_connection', indexName: 'connection_id');
            $table->jsonb('event_body')
                ->comment('Содержимое события');
            $table->smallInteger('try_count')
                ->default(0)
                ->comment('Количество попыток записать в Google Sheet');
            $table->timestamp('date_start')
                ->nullable()
                ->comment('Дата начала обработки');
            $table->timestamp('date_end')
                ->nullable()
                ->comment('Дата записи в Google Sheet');
            $table->timestamps();
            $table->comment('События из AmoCRM');

            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::drop('amo_event');
    }
};
