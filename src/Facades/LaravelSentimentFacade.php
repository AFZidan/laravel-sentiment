<?php

namespace Zidan\LaravelSentiment\Facades;

use Illuminate\Support\Facades\Facade;

class LaravelSentimentFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'LaravelSentiment';
    }
}