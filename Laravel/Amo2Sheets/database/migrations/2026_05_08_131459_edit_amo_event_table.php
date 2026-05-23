<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('amo_filter_pipeline', function (Blueprint $table) {
            $table->id();
            $table->integer('pipeline_id')->comment('ID воронки в amo');
            $table->foreignId('filter_id')
                ->constrained(table: 'amo_filter', indexName: 'amo_filter_pipeline_filter_id')
                ->onDelete('cascade');
            $table->timestamps();
            $table->comment('Связь фильтра и воронок');
        });

        $items = DB::table('amo_filter')->whereNotNull('pipeline_id')->get();
        foreach ($items as $item) {
            DB::table('amo_filter_pipeline')->insert([
                'filter_id' => $item->id,
                'pipeline_id' => $item->pipeline_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('amo_event', function (Blueprint $table) {
            $table->integer('pipeline_id')->after('status')->nullable()->comment('ID воронки в amo');
//            $table->dropForeign(['connection_id ']);
//            $table->dropColumn('connection_id ');
        });

        DB::table('amo_event')
            ->whereNull('pipeline_id')
            ->update(['pipeline_id' => DB::raw("event_body->'$.pipeline_id'")]);
    }

    public function down(): void
    {
        Schema::drop('amo_filter_pipeline');
        Schema::table('amo_event', function (Blueprint $table) {
            $table->dropColumn('pipeline_id');
        });
    }
};
