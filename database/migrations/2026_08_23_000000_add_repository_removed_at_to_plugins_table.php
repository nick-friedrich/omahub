<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plugins', function (Blueprint $table) {
            $table->timestamp('repository_removed_at')->nullable()->after('is_archived');
        });
    }

    public function down(): void
    {
        Schema::table('plugins', function (Blueprint $table) {
            $table->dropColumn('repository_removed_at');
        });
    }
};
