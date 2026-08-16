<?php

namespace App\Http\Requests;

use App\Rules\GitHubRepositoryUrl;
use Illuminate\Foundation\Http\FormRequest;

class SubmitPluginRequest extends FormRequest
{
    /**
     * The submission form is public; no authentication is required.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'repository_url' => ['required', 'url', new GitHubRepositoryUrl],
        ];
    }
}
