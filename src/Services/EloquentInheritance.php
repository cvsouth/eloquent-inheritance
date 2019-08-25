<?php namespace Cvsouth\EloquentInheritance\Services;

use Cvsouth\EloquentInheritance\InheritableModel;

use Illuminate\Support\Collection;

use Illuminate\Support\Facades\Cache;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EloquentInheritance
{
    public function topClassWithBaseId($base_id)
    {
        $cache_key = '#' . $base_id;

        if(Cache::has($cache_key))
            
            return Cache::get($cache_key);
        
        else
        {
            $top_class = DB::table(InheritableModel::tableName())->select('top_class')->where('id', '=', $base_id)->first();
            
            if($top_class)

                Cache::forever('#' . $base_id, $top_class);
            
            return $top_class;
        }
        
//        else // cache is granular and should be permanant, but if the cache is bust or something,
//            // when that occurs, let's just rebuild the entire cache again.
//            // for a large database this could take a long time
//            // an alternative would be to have it only build the cache for itself.
//            // in the long term that would mean a more database queries
//            // and also a massive burst when the cache is first being rebuilt
//            // it wont take too long, let's just rebuild the whole thing
//        {
//            // We do our best to ensure this won't run multiple times simultaneously
//            // It could happen from time to time in theory but not much.
//            // Block until the primary thread is done instead.
//            
//            // Instead of using a cache value, convert to using sem_get
//            // http://php.net/manual/en/function.sem-get.php
//            // This should be even more accurate. Polyfil for Windows in foundation
//            
//            $blocked = false;
//
//            do
//            {
//                $locked = Cache::has('#...');
//
//                if($locked)
//                {
//                    $blocked = true;
//
//                    sleep(0.1);
//                }
//            }
//            while($locked);
//            
//            if(!$blocked)
//            {
//                Cache::put('#...', true);
//
//                Log::warning('RECALCULATING ENTITY TOP_CLASSES');
//
//                DB::table(InheritableModel::tableName())->orderBy('id', 'asc')->chunk(300, function($models)
//                {
//                    foreach($models as $model)
//                        
//                        Cache::forever('#' . $model->id, $model->top_class);
//                });
//                Cache::forget('#...');
//            }
//            return $this->topClassWithBaseId($base_id);
//        }
    }
    public function getWithBaseId($base_id, $top_class = null)
    {
        $top_class = $top_class ?? $this->topClassWithBaseId($base_id);
        
        if($top_class != InheritableModel::class)
            
            $entity = $top_class::where('base_id', $base_id)->first();
        
        else $entity = $top_class::where('id', $base_id)->first();

        return $entity;
    }
    public function elevateMultiple($entities)
    {
        // TODO: always fetch top class values, in grouped queries

        $is_collection = $entities instanceof Collection;

        $elevated_entities = [];

        foreach($entities as $i => $entity)
            
            $elevated_entities[$i] = $entity->elevate();

        if($is_collection) $elevated_entities = collect($elevated_entities);

        return $elevated_entities;
    }

}
