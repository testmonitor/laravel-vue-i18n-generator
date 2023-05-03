<?php

namespace TestMonitor\VueI18nGenerator\Tests;

use Exception;
use Illuminate\Contracts\Config\Repository;
use TestMonitor\VueI18nGenerator\VueI18nGeneratorServiceProvider;

class TestCase extends \Orchestra\Testbench\TestCase
{
    public function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Define environment setup.
     *
     * @param \Illuminate\Foundation\Application $app
     *
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        tap($app->make('config'), function (Repository $config) {
            $config->set('vue-i18n-generator.outputFile', __DIR__ . '/data/output.js');
        });

        $app->useLangPath(__DIR__ . '/data/lang');
    }

    protected function getPackageProviders($app)
    {
        return [
            VueI18nGeneratorServiceProvider::class
        ];
    }
}