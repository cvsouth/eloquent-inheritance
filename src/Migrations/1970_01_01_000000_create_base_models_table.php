<?php

use Illuminate\Support\Facades\Schema;

use Illuminate\Database\Schema\Blueprint;

use Illuminate\Database\Migrations\Migration;

use Cvsouth\EloquentInheritance\InheritableModel;

class CreateBaseModelsTable extends Migration
{
    public function up()
    {
        if(!empty(env('DB_CONNECTION_INHERITABLE_MODELS_BASE_TABLE', null)))

            $connection = env('DB_CONNECTION_INHERITABLE_MODELS_BASE_TABLE', null);

        else $connection = config('database.default');

        $ignore = env('INHERITABLE_MODELS_NO_MIGRATION', false);
        
        if(!$ignore) Schema::connection($connection)->create(InheritableModel::tableName(), function (Blueprint $table)
        {
            $table->bigIncrements('id');

            $table->string('top_class', 300);

            $table->timestamps();
        });
    }
    public function down()
    {
        if(!empty(env('DB_CONNECTION_INHERITABLE_MODELS_BASE_TABLE', null)))

            $connection = env('DB_CONNECTION_INHERITABLE_MODELS_BASE_TABLE', null);

        else $connection = config('database.default');

        Schema::connection($connection)->drop(InheritableModel::tableName());
    }
}
