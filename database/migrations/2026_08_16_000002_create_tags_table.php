<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('plugin_tag', function (Blueprint $table) {
            $table->foreignId('plugin_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['plugin_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plugin_tag');
        Schema::dropIfExists('tags');
    }
};
