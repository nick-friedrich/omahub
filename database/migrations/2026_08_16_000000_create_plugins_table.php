<?php

use App\Enums\PluginStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plugins', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('repository_url')->unique();
            $table->string('repository_owner');
            $table->string('repository_name');
            $table->string('author_name')->nullable();
            $table->string('author_url')->nullable();
            $table->string('license')->nullable();
            $table->string('homepage_url')->nullable();
            $table->string('icon_url')->nullable();
            $table->json('manifest_data');
            $table->longText('readme_markdown')->nullable();
            $table->string('default_branch')->nullable();
            $table->string('latest_commit_sha', 64)->nullable();
            $table->string('latest_version')->nullable();
            $table->unsignedInteger('stars_count')->default(0);
            $table->unsignedInteger('forks_count')->default(0);
            $table->unsignedInteger('open_issues_count')->default(0);
            $table->boolean('is_archived')->default(false);
            $table->timestamp('last_pushed_at')->nullable();
            $table->timestamp('last_indexed_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('status')->default(PluginStatus::Pending->value)->index();
            $table->timestamps();

            $table->unique(['repository_owner', 'repository_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plugins');
    }
};
