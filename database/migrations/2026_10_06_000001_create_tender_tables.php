<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wo_sequences', function (Blueprint $table) {
            $table->date('date')->primary();
            $table->unsignedInteger('last_seq')->default(0);
        });

        Schema::create('tenders', function (Blueprint $table) {
            $table->id();
            $table->string('wo_number', 20)->unique();
            $table->date('wo_date');
            $table->string('mode', 10);
            $table->string('type', 20);
            $table->string('category', 50);
            $table->string('tender_code', 100)->index();
            $table->text('title');
            $table->string('client');
            $table->text('scope')->nullable();
            $table->foreignId('pic_id')->constrained('users');
            $table->foreignId('owner_id')->nullable()->constrained('users');
            $table->date('publish_date')->nullable();
            $table->date('closing_date')->index();
            $table->boolean('has_briefing')->default(false);
            $table->date('briefing_date')->nullable();
            $table->unsignedBigInteger('estimated_value_sen')->nullable();
            $table->string('status', 20)->index();
            $table->unsignedBigInteger('submitted_price_sen')->nullable();
            $table->unsignedBigInteger('winning_price_sen')->nullable();
            $table->text('lost_reason')->nullable();
            $table->boolean('was_cancelled')->default(false);
            $table->timestamp('done_at')->nullable();
            $table->timestamp('awarded_at')->nullable();
            $table->timestamp('lost_at')->nullable();
            $table->timestamp('closing_soon_notified_at')->nullable();
            $table->timestamp('briefing_notified_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });

        Schema::create('tender_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tender_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('position');
            $table->boolean('is_done')->default(false);
            $table->foreignId('done_by')->nullable()->constrained('users');
            $table->timestamp('done_at')->nullable();
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tender_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->string('event', 40);
            $table->text('description');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('tender_documents');
        Schema::dropIfExists('tenders');
        Schema::dropIfExists('wo_sequences');
    }
};
