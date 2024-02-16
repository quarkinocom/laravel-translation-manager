<?php

namespace Quarkinocom\TranslationManager;

use Illuminate\Support\ServiceProvider;

class TranslationManagerServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Register the command if running in console
        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\LanguageTranslationCommand::class,
            ]);

            // Publish the config file
            $this->publishes([
                __DIR__.'/config/translation-manager.php' => config_path('translation-manager.php'),
            ], 'config');
        }
    }

    public function register()
    {
        // Merge the package config file with the application's copy
        $this->mergeConfigFrom(
            __DIR__.'/config/translation-manager.php', 'translation-manager'
        );
    }

}
