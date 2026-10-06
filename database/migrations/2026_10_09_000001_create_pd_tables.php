<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    /** The old PD system's project types and their approved margins (basis points). */
    private const TYPES = [
        ['Audio Visual', 1500], ['Consultancy (BIM)', 3000], ['Consultancy (ICT)', 3000], ['DC Infrastructure', 1500],
        ['Distributorship', 0], ['Enterprise Solution', 1500], ['Installation Services', 3000], ['Leasing (Audio Visual)', 600],
        ['Leasing (Enterprise Solution)', 1100], ['Leasing (ICT Peripherals)', 400], ['Leasing (Networking)', 1000],
        ['Leasing (Others)', 1000], ['Managed Services', 1500], ['Networking', 2000], ['Project Management', 2000],
        ['Security Solution', 1000], ['Trading - General', 1500], ['Trading - ICT Peripherals', 1500],
        ['Trading - Medical (disposable)', 1500], ['Trading - Medical (Drugs)', 1500], ['Maintenance Services', 3000],
        ['Support (ASP)', 3000],
    ];

    public function up(): void
    {
        Schema::create('project_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->unsignedSmallInteger('approved_margin_bp');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        $now = now();
        DB::table('project_types')->insert(array_map(
            fn ($t) => ['name' => $t[0], 'approved_margin_bp' => $t[1], 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            self::TYPES,
        ));

        Schema::create('finance_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('project_charge_bp');
            $table->unsignedSmallInteger('commission_share_bp');
            $table->timestamps();
        });
        DB::table('finance_settings')->insert(['project_charge_bp' => 900, 'commission_share_bp' => 5000, 'created_at' => $now, 'updated_at' => $now]);

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tender_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('project_type_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('approved_margin_bp')->default(0);
            $table->unsignedSmallInteger('project_charge_bp');
            $table->unsignedSmallInteger('commission_share_bp');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });

        Schema::create('pd_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('pd_group', 20);
            $table->string('name', 255);
            $table->string('reference', 100)->nullable();
            $table->unsignedBigInteger('budget_sen')->default(0);
            $table->date('scheduled_date')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->index(['project_id', 'pd_group', 'position']);
        });

        Schema::create('pd_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pd_line_id')->constrained()->restrictOnDelete(); // money records never vanish with their line
            $table->string('type', 10);
            $table->string('number', 100)->nullable();
            $table->date('date');
            $table->unsignedBigInteger('amount_sen');
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('costing_lines', function (Blueprint $table) {
            $table->string('pd_group', 20)->default('principal')->after('position');
        });
    }

    public function down(): void
    {
        Schema::table('costing_lines', fn (Blueprint $table) => $table->dropColumn('pd_group'));
        Schema::dropIfExists('pd_entries');
        Schema::dropIfExists('pd_lines');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('finance_settings');
        Schema::dropIfExists('project_types');
    }
};
