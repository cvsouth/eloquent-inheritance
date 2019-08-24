<?php namespace Cvsouth\Entities\Providers;

use Cvsouth\Entities\Entity;

use Cvsouth\Entities\EntityType;

use Cvsouth\Entities\Services\Entities;

use Cvsouth\Entities\Facades\Entities as EntitiesFacade;

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
        AliasLoader::getInstance()->alias('Entity', Entity::class);

        AliasLoader::getInstance()->alias('EntityType', EntityType::class);


        $this->app->singleton('entities', function () { return new Entities(); });
        AliasLoader::getInstance()->alias('Entities', EntitiesFacade::class);

    }
}
