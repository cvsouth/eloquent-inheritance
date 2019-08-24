<?php

use Illuminate\Support\Facades\Schema;

use Illuminate\Database\Schema\Blueprint;

use Illuminate\Database\Migrations\Migration;

class CreateModelTypesTable extends Migration
{
    public function up()
    {
        Schema::create('model_types', function (Blueprint $table)
        {
            $table->bigInteger('entity_id')->unsigned();
            
            $table->bigIncrements('id');
           
            $table->string('entity_class', 250);
        });
    }
    public function down()
    {
        Schema::dropIfExists('model_types');
    }
}
