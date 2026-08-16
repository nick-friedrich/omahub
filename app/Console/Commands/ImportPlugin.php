<?php

namespace App\Console\Commands;

use App\Exceptions\GitHubRequestException;
use App\Exceptions\InvalidGitHubRepositoryUrl;
use App\Exceptions\ManifestValidationException;
use App\Services\Plugins\GitHubRepositoryImporter;
use Illuminate\Console\Command;
use UnexpectedValueException;

class ImportPlugin extends Command
{
    protected $signature = 'plugins:import {url : Public GitHub repository URL}';

    protected $description = 'Import or update an Omarchy plugin from GitHub';

    public function handle(GitHubRepositoryImporter $importer): int
    {
        try {
            $plugin = $importer->import($this->argument('url'));
        } catch (InvalidGitHubRepositoryUrl|GitHubRequestException|ManifestValidationException|UnexpectedValueException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Imported {$plugin->name} ({$plugin->slug}) at commit {$plugin->latest_commit_sha}.");

        return self::SUCCESS;
    }
}
