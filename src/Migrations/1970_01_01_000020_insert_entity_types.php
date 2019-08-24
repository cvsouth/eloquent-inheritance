<?php

use Cvsouth\Entities\Entity;

use Cvsouth\Entities\EntityType;

use Illuminate\Database\Migrations\Migration;

class InsertEntityTypes extends Migration
{
    public function up()
    {
        $entity_type = new EntityType(["entity_class" => Entity::class]); $entity_type->save();
       
        $entity_type = new EntityType(["entity_class" => EntityType::class]); $entity_type->save();
    }
    public function down()
    {
        $entity_type = EntityType::where("entity_class", Entity::class)->first(); if($entity_type) EntityType::destroy([$entity_type->id]);
    
        $entity_type = EntityType::where("entity_class", EntityType::class)->first(); if($entity_type) EntityType::destroy([$entity_type->id]);
    }
}
