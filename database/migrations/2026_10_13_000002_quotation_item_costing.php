<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/** Quotation items get the tender-costing fields and their own SST tick; existing items keep their exact price. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->unsignedInteger('frequency')->default(1)->after('unit');
            $table->unsignedBigInteger('unit_cost_sen')->default(0)->after('frequency');
            $table->unsignedSmallInteger('margin_bp')->default(2000)->after('unit_cost_sen');
            $table->unsignedBigInteger('unit_price_override_sen')->nullable()->after('margin_bp');
            $table->string('vendor')->nullable()->after('unit_price_sen');
            $table->string('quote_url', 500)->nullable()->after('vendor');
            $table->boolean('has_sst')->default(true)->after('quote_url');
            $table->json('sub_items')->nullable()->after('has_sst');
        });
        DB::table('quotation_items')->update(['unit_price_override_sen' => DB::raw('unit_price_sen')]); // old prices stay exact
        Schema::table('quotations', fn (Blueprint $table) => $table->unsignedSmallInteger('default_margin_bp')->default(2000)->after('sst_bp'));
    }

    public function down(): void
    {
        Schema::table('quotation_items', fn (Blueprint $table) => $table->dropColumn(['frequency', 'unit_cost_sen', 'margin_bp',
            'unit_price_override_sen', 'vendor', 'quote_url', 'has_sst', 'sub_items']));
        Schema::table('quotations', fn (Blueprint $table) => $table->dropColumn('default_margin_bp'));
    }
};
