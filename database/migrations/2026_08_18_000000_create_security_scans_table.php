<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plugin_id')->constrained()->cascadeOnDelete();
            $table->string('commit_sha', 64);
            $table->string('status')->index();
            $table->string('risk_level')->nullable();
            $table->json('rules_run')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['plugin_id', 'commit_sha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_scans');
    }
};
