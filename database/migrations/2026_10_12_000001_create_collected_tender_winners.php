<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collected_tender_winners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collected_tender_id')->constrained()->cascadeOnDelete();
            $table->string('name', 500);
            $table->string('name_key', 500);
            $table->unsignedBigInteger('price_sen')->nullable();
            $table->unsignedSmallInteger('position');
            $table->index(['name_key', 'collected_tender_id']);
            $table->index(['collected_tender_id', 'position']);
        });
        Schema::table('finance_settings', fn (Blueprint $table) => $table->text('own_company_names')->nullable());
        DB::table('finance_settings')->update(['own_company_names' => '10 CREATIVE SOLUTIONS SDN BHD']);
    }

    public function down(): void
    {
        Schema::dropIfExists('collected_tender_winners');
        Schema::table('finance_settings', fn (Blueprint $table) => $table->dropColumn('own_company_names'));
    }
};
