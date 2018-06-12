<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        if (!class_exists('Storage')) {
            class_alias('Illuminate\Support\Facades\Storage', 'Storage');
        }

        $this->app->register(\Illuminate\Filesystem\FilesystemServiceProvider::class);

#        $this->app->configure('filesystems');

        if ($this->app->environment() !== 'production') {
            $this->app->register(\Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider::class);
        }
    }
}
