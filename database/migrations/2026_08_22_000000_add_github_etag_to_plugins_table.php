<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plugins', function (Blueprint $table) {
            $table->string('github_etag')->nullable()->after('default_branch');
        });
    }

    public function down(): void
    {
        Schema::table('plugins', function (Blueprint $table) {
            $table->dropColumn('github_etag');
        });
    }
};