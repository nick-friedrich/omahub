<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePluginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:4000'],
            'author_name' => ['nullable', 'string', 'max:255'],
            'author_url' => ['nullable', 'url', 'max:255'],
            'license' => ['nullable', 'string', 'max:255'],
            'homepage_url' => ['nullable', 'url', 'max:255'],
            'categories' => ['array'],
            'categories.*' => ['integer', Rule::exists('categories', 'id')],
            'tags' => ['array'],
            'tags.*' => ['integer', Rule::exists('tags', 'id')],
        ];
    }
}
