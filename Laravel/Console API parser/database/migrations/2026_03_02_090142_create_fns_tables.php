<?php

use App\Enums\CompanyGroupStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->comment('Компании');
            $table->id();
            $table->string('inn')->comment('ИНН');
            $table->integer('region_code')->comment('Код региона');
            $table->string('main_okved')->comment('Основной ОКВЭД');
            $table->date('registration_date')->comment('Дата регистрации');
            $table->date('liquidation_date')->nullable()->comment('Дата ликвидации');
            $table->string('raw_status')->comment('Статус ЕГРЮЛ');
            $table->enum('status_group', CompanyGroupStatus::values())
                ->default(CompanyGroupStatus::ACTIVE)
                ->comment('Группа статуса');
            $table->string('status_code')->comment('Код статуса');
            $table->timestamps();
        });

        Schema::create('aggregates', function (Blueprint $table) {
            $table->comment('Агрегированные показатели по годам');
            $table->id();
            $table->integer('year')->comment('Отчетный год');
            $table->enum('status_group', CompanyGroupStatus::values())
                ->default(CompanyGroupStatus::ACTIVE)
                ->comment('Группа статуса');
            $table->string('status_code')->comment('Код статуса');
            $table->integer('companies_count')->comment('Количество компаний');
            $table->integer('data_version')->comment('Версия');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
        Schema::dropIfExists('aggregates');
    }
};
