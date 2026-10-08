<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenders', function (Blueprint $table) {
            $table->unsignedSmallInteger('default_margin_bp')->default(2000)->after('estimated_value_sen');
            $table->unsignedBigInteger('bid_price_override_sen')->nullable()->after('default_margin_bp');
        });

        Schema::create('costing_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tender_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('description', 500);
            $table->string('unit', 50)->default('unit');
            $table->unsignedInteger('quantity')->default(1);
            $table->string('frequency', 10)->default('one_off');
            $table->unsignedSmallInteger('months')->default(1);
            $table->unsignedTinyInteger('project_year')->default(1);
            $table->unsignedBigInteger('unit_cost_sen')->default(0);
            $table->unsignedSmallInteger('margin_bp')->default(2000);
            $table->string('vendor')->nullable();
            $table->string('quote_url', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('costing_sub_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('costing_line_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('description', 500);
            $table->string('unit', 50)->default('unit');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_cost_sen')->default(0);
            $table->string('vendor')->nullable();
            $table->string('quote_url', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('costing_sub_items');
        Schema::dropIfExists('costing_lines');
        Schema::table('tenders', fn (Blueprint $table) => $table->dropColumn(['default_margin_bp', 'bid_price_override_sen']));
    }
};
