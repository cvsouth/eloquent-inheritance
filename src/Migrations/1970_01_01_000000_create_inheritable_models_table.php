<?php

use Illuminate\Support\Facades\Schema;

use Illuminate\Database\Schema\Blueprint;

use Illuminate\Database\Migrations\Migration;

class CreateInheritableModelsTable extends Migration
{
    public function up()
    {
        Schema::create('base_models', function (Blueprint $table)
        {
            $table->bigIncrements('id');
            
            $table->string('top_class', 300);
        });
    }
    public function down()
    {
        Schema::drop('base_models');
    }
}
