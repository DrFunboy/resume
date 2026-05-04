<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('amo_connection', function (Blueprint $table) {
            $table->jsonb('sheet_fields')->after('sheet_id')->nullable()->comment('Экспортируемые поля');
        });
    }

    public function down(): void
    {
        Schema::table('amo_account', function (Blueprint $table) {
            $table->dropColumn('sheet_fields');
        });
    }
};
