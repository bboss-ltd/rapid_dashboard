<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $manifestPath = public_path('build/manifest.json');
        if (!file_exists($manifestPath)) {
            $dir = dirname($manifestPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $manifest = [
                'resources/css/app.css' => [
                    'file' => 'assets/app.css',
                    'src' => 'resources/css/app.css',
                    'isEntry' => true,
                ],
                'resources/js/app.js' => [
                    'file' => 'assets/app.js',
                    'src' => 'resources/js/app.js',
                    'isEntry' => true,
                ],
            ];

            file_put_contents($manifestPath, json_encode($manifest, JSON_UNESCAPED_SLASHES));
        }
    }
}
