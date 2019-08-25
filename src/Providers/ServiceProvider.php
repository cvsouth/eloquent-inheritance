<?php namespace Cvsouth\EloquentInheritance\Providers;

use Cvsouth\EloquentInheritance\InheritableModel;

use Cvsouth\EloquentInheritance\ModelType;

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

        AliasLoader::getInstance()->alias('ModelType', ModelType::class);
    }
}
