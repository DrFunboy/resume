<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('amo_account', function (Blueprint $table) {
            $table->id();
            $table->string('client_id');
            $table->string('domain')->comment('Ссылка на amoCRM');
            $table->string('refresh_token');
            $table->timestamps();
            $table->comment('Аккаунт с интеграцией');
        });

        Schema::create('amo_filter', function (Blueprint $table) {
            $table->id();
            $table->uuid();
            $table->string('name');
            $table->foreignId('account_id')
                ->constrained(table: 'amo_account', indexName: 'id')
                ->onDelete('cascade');
            $table->integer('pipeline_id')->comment('ID воронки в amo');
            $table->text('filter_url')->comment('URL Фильтра');
            $table->integer('amo_author')->comment('Создатель');
            $table->string('comment')->nullable();
            $table->timestamps();
            $table->comment('Фильтр выгрузки');
        });

        Schema::create('amo_connection', function (Blueprint $table) {
            $table->id();
            $table->uuid();
            $table->foreignId('account_id')
                ->constrained(table: 'amo_account', indexName: 'account_id')
                ->onDelete('cascade');
            $table->foreignId('filter_id')
                ->constrained(table: 'amo_filter', indexName: 'filter_id')
                ->onDelete('cascade');
            $table->string('sheet_id')->comment('ID Google таблицы');
            $table->dateTime('date_sync')->nullable()->comment('Дата синхронизации');
            $table->integer('amo_author')->comment('Создатель');
            $table->timestamps();
            $table->comment('Синхронизируемые воронки');
        });
    }

    public function down(): void
    {
        Schema::drop('amo_connection');
        Schema::drop('amo_filter');
        Schema::drop('amo_account');
    }
};
