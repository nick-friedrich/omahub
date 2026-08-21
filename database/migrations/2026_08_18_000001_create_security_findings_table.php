<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('security_scan_id')->constrained()->cascadeOnDelete();
            $table->string('rule');
            $table->string('severity');
            $table->string('file');
            $table->unsignedInteger('line')->nullable();
            $table->text('snippet')->nullable();
            $table->text('description');
            $table->timestamps();

            $table->index(['security_scan_id', 'rule']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_findings');
    }
};
