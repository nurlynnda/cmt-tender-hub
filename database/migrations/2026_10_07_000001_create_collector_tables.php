<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collected_tenders', function (Blueprint $table) {
            $table->id();
            $table->string('dedup_key', 191)->unique();
            $table->string('reference_no', 191)->default('');
            $table->text('title');
            $table->string('status', 10)->index();
            $table->string('procurement_type', 20)->nullable()->index();
            $table->string('ministry')->nullable()->index();
            $table->string('agency')->nullable();
            $table->string('category')->nullable();
            $table->date('advertised_date')->nullable();
            $table->date('closing_date')->nullable()->index();
            $table->unsignedBigInteger('indicative_price_sen')->nullable();
            $table->json('events')->nullable();
            $table->json('winners')->nullable();
            $table->json('raw')->nullable();
            $table->json('field_updated_at')->nullable();
            $table->timestamp('scraped_at')->nullable();
            $table->timestamps();
            $table->fullText(['title', 'reference_no', 'agency']);
        });

        Schema::create('collected_tender_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collected_tender_id')->constrained()->cascadeOnDelete();
            $table->string('source', 30)->index();
            $table->string('source_id', 191);
            $table->string('source_url', 500);
            $table->unique(['collected_tender_id', 'source']);
        });

        Schema::create('collected_tender_field_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collected_tender_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50)->index();
            $table->unique(['collected_tender_id', 'code']);
        });

        Schema::create('collection_runs', function (Blueprint $table) {
            $table->id();
            $table->string('trigger', 20);
            $table->string('scope', 10);
            $table->foreignId('started_by')->nullable()->constrained('users');
            $table->string('status', 20)->index();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->json('results')->nullable();
            $table->unsignedInteger('closed_stale')->default(0);
        });

        Schema::table('tenders', function (Blueprint $table) {
            $table->foreignId('collected_tender_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tenders', fn (Blueprint $table) => $table->dropConstrainedForeignId('collected_tender_id'));
        Schema::dropIfExists('collection_runs');
        Schema::dropIfExists('collected_tender_field_codes');
        Schema::dropIfExists('collected_tender_sources');
        Schema::dropIfExists('collected_tenders');
    }
};
