<?php namespace atroxic\EloquentInheritance\Providers;

use atroxic\EloquentInheritance\InheritableModel;

use Illuminate\Foundation\AliasLoader;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__.'/../Migrations');
    }
    public function register()
    {
        AliasLoader::getInstance()->alias('InheritableModel', InheritableModel::class);
    }
}
