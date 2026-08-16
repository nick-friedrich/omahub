<?php

use App\Enums\SubmissionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plugin_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('repository_url');
            $table->foreignId('plugin_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default(SubmissionStatus::Pending->value)->index();
            $table->text('failure_reason')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plugin_submissions');
    }
};
