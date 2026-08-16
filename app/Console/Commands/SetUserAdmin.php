<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class SetUserAdmin extends Command
{
    protected $signature = 'user:admin {--username= : Grant admin to user by GitHub username} {--id= : Grant admin to user by user ID} {--remove : Revoke admin instead of granting}';

    protected $description = 'Grant or revoke admin access for a user';

    public function handle(): int
    {
        $username = $this->option('username');
        $id = $this->option('id');

        if (($username === null && $id === null) || ($username !== null && $id !== null)) {
            $this->error('Provide exactly one of --username or --id.');

            return self::FAILURE;
        }

        $query = User::query();

        if ($id !== null) {
            $query->where('id', (int) $id);
        } else {
            $query->where('github_username', $username);
        }

        $user = $query->first();

        if ($user === null) {
            $this->error($id !== null ? 'No user found with that ID.' : 'No user found with that GitHub username.');

            return self::FAILURE;
        }

        $removing = (bool) $this->option('remove');

        $user->update(['is_admin' => ! $removing]);

        $this->info(
            $removing
                ? "Revoked admin from {$user->github_username}."
                : "Granted admin to {$user->github_username}.",
        );

        return self::SUCCESS;
    }
}
