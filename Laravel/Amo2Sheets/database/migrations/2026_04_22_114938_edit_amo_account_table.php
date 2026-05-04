<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('amo_account', function (Blueprint $table) {
            $table->text('amo_refresh')->nullable()->comment('refresh token для amoCRM');
            $table->text('amo_secret')->nullable()->comment('Secret key для amoCRM');
            $table->string('refresh_token')->change()->nullable()->comment('refresh token для Google');
            $table->renameColumn('refresh_token', 'google_refresh');
        });
    }

    public function down(): void
    {
        Schema::table('amo_account', function (Blueprint $table) {
            $table->dropColumn('amo_refresh');
            $table->renameColumn('google_refresh', 'refresh_token');
        });
    }
};
