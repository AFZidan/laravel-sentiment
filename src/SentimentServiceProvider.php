<?php

namespace Zidan\LaravelSentiment;

use Illuminate\Support\ServiceProvider;

class SentimentServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app['LaravelSentiment'] = $this->app->singleton('SentimentServiceProvider',
            function ($app) {
                return new Analise();
            });

            $this->mergeConfigFrom(
                __DIR__.'/config/laravel-sentiment.php',
                'laravel-sentiment'
            );

    }
}
