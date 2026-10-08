<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    private const TERMS = [
        'Prices quoted are in Ringgit Malaysia (RM).',
        'This quotation is valid for the number of days stated above from the date of issue.',
        'Payment terms: 30 days from the date of invoice.',
        'Delivery within 4–6 weeks upon receipt of official Purchase Order (PO) / Letter of Award.',
        'Any changes to scope, quantity or specification may affect the quoted price.',
        'Warranty as per principal / manufacturer terms unless stated otherwise.',
    ];

    public function up(): void
    {
        Schema::create('company_profile', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('registration_no')->nullable();
            $table->string('sst_no')->nullable();
            $table->text('address')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('stamp_path')->nullable();
            $table->text('default_terms')->nullable();
            $table->unsignedSmallInteger('default_sst_bp')->default(800);
            $table->timestamps();
        });
        DB::table('company_profile')->insert([
            'name' => 'CMT Sdn. Bhd.',
            'registration_no' => '201901000000 (1234567-X)',
            'sst_no' => null,
            'address' => "Level 8, Menara Example, Jalan Tun Razak,\n50400 Kuala Lumpur, Malaysia",
            'phone' => '+603-0000 0000',
            'email' => 'sales@cmt.com.my',
            'website' => 'www.cmt.com.my',
            'default_terms' => implode("\n", self::TERMS),
            'default_sst_bp' => 800,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('quotation_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('last_seq');
        });

        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->foreignId('revision_of_id')->nullable()->constrained('quotations')->nullOnDelete();
            $table->string('status', 10)->default('draft');
            $table->date('quote_date');
            $table->unsignedSmallInteger('validity_days')->default(30);
            $table->string('customer_name')->nullable();
            $table->string('attention')->nullable();
            $table->string('attention_phone', 50)->nullable();
            $table->string('attention_email')->nullable();
            $table->text('customer_address')->nullable();
            $table->string('subject', 500)->nullable();
            $table->foreignId('prepared_by')->constrained('users');
            $table->string('preparer_position')->nullable();
            $table->string('preparer_phone', 50)->nullable();
            $table->string('preparer_email')->nullable();
            $table->boolean('show_signature')->default(true);
            $table->boolean('show_stamp')->default(true);
            $table->unsignedSmallInteger('sst_bp')->default(800);
            $table->text('terms')->nullable();
            $table->json('letterhead');
            foreach (['sent', 'accepted', 'rejected'] as $s) {
                $table->timestamp("{$s}_at")->nullable();
                $table->foreignId("{$s}_by")->nullable()->constrained('users')->nullOnDelete();
            }
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->index(['status', 'quote_date']);
        });

        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('title', 255);
            $table->text('details')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('unit', 50)->default('Unit');
            $table->unsignedBigInteger('unit_price_sen')->default(0);
            $table->timestamps();
        });

        // A project (Stage 4) and a history entry now belong to either a tender or a quotation.
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('tender_id')->nullable()->change();
            $table->foreignId('quotation_id')->nullable()->unique()->after('tender_id')->constrained()->restrictOnDelete();
        });
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->foreignId('tender_id')->nullable()->change();
            $table->foreignId('quotation_id')->nullable()->after('tender_id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', fn (Blueprint $table) => $table->dropConstrainedForeignId('quotation_id'));
        Schema::table('projects', fn (Blueprint $table) => $table->dropConstrainedForeignId('quotation_id'));
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
        Schema::dropIfExists('quotation_sequences');
        Schema::dropIfExists('company_profile');
    }
};
