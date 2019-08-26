<?php

use Illuminate\Support\Facades\Schema;

use Illuminate\Database\Schema\Blueprint;

use Illuminate\Database\Migrations\Migration;

use Cvsouth\EloquentInheritance\InheritableModel;

class CreateBaseModelsTable extends Migration
{
    public function up()
    {
        Schema::create(InheritableModel::tableName(), function (Blueprint $table)
        {
            $table->bigIncrements('id');
            
            $table->string('top_class', 300);
        });
    }
    public function down()
    {
        Schema::drop(InheritableModel::tableName());
    }
}
