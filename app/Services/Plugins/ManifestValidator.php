<?php

namespace App\Services\Plugins;

use App\Exceptions\ManifestValidationException;
use JsonException;
use stdClass;

class ManifestValidator
{
    private const SUPPORTED_KINDS = [
        'bar-widget',
        'panel',
        'overlay',
        'menu',
        'service',
        'bar',
    ];

    /** @return array<string, mixed> */
    public function validate(string $json): array
    {
        try {
            $manifest = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ManifestValidationException(['The file is not valid JSON: '.$exception->getMessage()]);
        }

        if (! $manifest instanceof stdClass) {
            throw new ManifestValidationException(['The root value must be an object.']);
        }

        $data = (array) $manifest;
        $errors = [];

        if (($data['schemaVersion'] ?? null) !== 1) {
            $errors[] = 'schemaVersion must be the number 1.';
        }

        $id = $data['id'] ?? null;
        if (! is_string($id) || ! preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $id) || str_contains((string) $id, '..')) {
            $errors[] = 'id must start with a letter or digit and contain only letters, digits, dots, underscores, and hyphens.';
        } elseif (str_starts_with(strtolower($id), 'omarchy.')) {
            $errors[] = 'id may not use the reserved omarchy.* namespace.';
        }

        foreach (['name', 'version'] as $field) {
            if (! is_string($data[$field] ?? null) || trim($data[$field]) === '') {
                $errors[] = "{$field} must be a non-empty string.";
            }
        }

        $kinds = $data['kinds'] ?? null;
        if (! is_array($kinds) || $kinds === []) {
            $errors[] = 'kinds must be a non-empty array.';
        } else {
            foreach ($kinds as $kind) {
                if (! is_string($kind) || ! in_array($kind, self::SUPPORTED_KINDS, true)) {
                    $errors[] = 'kinds contains an unsupported plugin kind.';
                    break;
                }
            }
        }

        $entryPoints = $data['entryPoints'] ?? null;
        if (! $entryPoints instanceof stdClass) {
            $errors[] = 'entryPoints must be an object.';
        } else {
            foreach ((array) $entryPoints as $path) {
                if (! $this->isSafeRelativePath($path)) {
                    $errors[] = 'Every entry point must be a safe path inside the plugin directory.';
                    break;
                }
            }
        }

        foreach (['author', 'description', 'license'] as $field) {
            if (isset($data[$field]) && (! is_string($data[$field]) || trim($data[$field]) === '')) {
                $errors[] = "{$field} must be a non-empty string when present.";
            }
        }

        if ($errors !== []) {
            throw new ManifestValidationException($errors);
        }

        $data['entryPoints'] = (array) $entryPoints;

        return $data;
    }

    private function isSafeRelativePath(mixed $path): bool
    {
        return is_string($path)
            && $path !== ''
            && ! str_starts_with($path, '/')
            && ! str_contains($path, '..')
            && ! str_contains($path, "\n")
            && ! str_contains($path, "\r");
    }
}
