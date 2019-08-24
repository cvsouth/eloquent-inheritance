<?php

use Cvsouth\EloquentInheritance\ModelType;

use Cvsouth\EloquentInheritance\InheritableModel;

use Illuminate\Database\Migrations\Migration;

class InsertModelTypes extends Migration
{
    public function up()
    {
        $entity_type = new ModelType(["entity_class" => InheritableModel::class]); $entity_type->save();
       
        $entity_type = new ModelType(["entity_class" => ModelType::class]); $entity_type->save();
    }
    public function down()
    {
        $entity_type = ModelType::where("entity_class", InheritableModel::class)->first(); if($entity_type) ModelType::destroy([$entity_type->id]);
    
        $entity_type = ModelType::where("entity_class", ModelType::class)->first(); if($entity_type) ModelType::destroy([$entity_type->id]);
    }
}
