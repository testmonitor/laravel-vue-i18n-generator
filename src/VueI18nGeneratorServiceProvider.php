<?php

namespace TestMonitor\VueI18nGenerator;

use Illuminate\Support\ServiceProvider;
use TestMonitor\VueI18nGenerator\Console\GenerateVueTranslations;

class VueI18nGeneratorServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/config.php' => config_path('vue-i18n-generator.php'),
            ], 'config');

            // Registering package command
            $this->commands([
                GenerateVueTranslations::class,
            ]);
        }
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'vue-i18n-generator');
    }
}
