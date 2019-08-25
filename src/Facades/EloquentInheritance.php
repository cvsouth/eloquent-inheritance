<?php namespace Cvsouth\EloquentInheritance\Facades;

use Illuminate\Support\Facades\Facade;

class EloquentInheritance extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'elouquent-inheritance';
    }
}
