<?php

namespace Tests\Unit\Services;

use App\Exceptions\ManifestValidationException;
use App\Services\Plugins\ManifestValidator;
use PHPUnit\Framework\TestCase;

class ManifestValidatorTest extends TestCase
{
    public function test_it_accepts_a_valid_omarchy_manifest(): void
    {
        $json = file_get_contents(__DIR__.'/../../Fixtures/plugins/valid/manifest.json');
        $manifest = (new ManifestValidator)->validate($json);

        $this->assertSame('acme.workspace-switcher', $manifest['id']);
        $this->assertSame(['service', 'bar-widget'], $manifest['kinds']);
        $this->assertSame('Widget.qml', $manifest['entryPoints']['barWidget']);
    }

    public function test_it_reports_all_basic_structure_errors(): void
    {
        try {
            (new ManifestValidator)->validate('{"schemaVersion":"1","id":"../bad","name":"","kinds":[],"entryPoints":[]}');
            $this->fail('Validation should have failed.');
        } catch (ManifestValidationException $exception) {
            $this->assertGreaterThanOrEqual(5, count($exception->errors));
            $this->assertStringContainsString('schemaVersion', $exception->getMessage());
            $this->assertStringContainsString('entryPoints', $exception->getMessage());
        }
    }

    public function test_it_rejects_entry_points_outside_the_plugin_directory(): void
    {
        $manifest = [
            'schemaVersion' => 1,
            'id' => 'acme.plugin',
            'name' => 'Plugin',
            'version' => '1.0.0',
            'kinds' => ['panel'],
            'entryPoints' => ['panel' => '../Panel.qml'],
        ];

        $this->expectException(ManifestValidationException::class);

        (new ManifestValidator)->validate(json_encode($manifest, JSON_THROW_ON_ERROR));
    }
}
