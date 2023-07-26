<?php

namespace Samehdoush\LaravelPayments\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Samehdoush\LaravelPayments\LaravelPayments
 */
class LaravelPayments extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Samehdoush\LaravelPayments\LaravelPayments::class;
    }
}
