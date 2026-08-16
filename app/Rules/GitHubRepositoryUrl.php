<?php

namespace App\Rules;

use App\Exceptions\InvalidGitHubRepositoryUrl;
use App\ValueObjects\GitHubRepository;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class GitHubRepositoryUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute must be a text value.');

            return;
        }

        try {
            GitHubRepository::fromUrl($value);
        } catch (InvalidGitHubRepositoryUrl) {
            $fail('Enter a public GitHub repository URL such as https://github.com/owner/repository.');
        }
    }
}
