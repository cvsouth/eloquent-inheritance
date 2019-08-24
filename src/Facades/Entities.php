<?php namespace Cvsouth\Entities\Facades;

use Illuminate\Support\Facades\Facade;

class Entities extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'entities';
    }
}
