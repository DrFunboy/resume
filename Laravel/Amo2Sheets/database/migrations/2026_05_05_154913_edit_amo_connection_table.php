<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('amo_connection', function (Blueprint $table) {
            $table->boolean('active')->after('date_sync')->default(true)->comment('Активна ли выгрузка');
        });
    }

    public function down(): void
    {
        Schema::table('amo_connection', function (Blueprint $table) {
            $table->dropColumn('active');
        });
    }
};
