<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plugin_id')->constrained()->cascadeOnDelete();
            $table->foreignId('security_scan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('commit_sha', 64);
            $table->string('status')->index();
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->string('risk_level')->nullable();
            $table->string('recommendation')->nullable();
            $table->text('summary')->nullable();
            $table->json('concerns')->nullable();
            $table->longText('raw_response')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['plugin_id', 'commit_sha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_reviews');
    }
};
