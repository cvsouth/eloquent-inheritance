<?php

use Illuminate\Support\Facades\Schema;

use Illuminate\Database\Schema\Blueprint;

use Illuminate\Database\Migrations\Migration;

class CreateEntitiesTable extends Migration
{
    public function up()
    {
        Schema::create('entities', function (Blueprint $table)
        {
            $table->bigIncrements('id');
            
            $table->string('top_class', 300);
        });
    }
    public function down()
    {
        Schema::drop('entities');
    }
}
