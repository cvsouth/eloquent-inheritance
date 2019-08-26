<?php namespace Cvsouth\EloquentInheritance;

use Illuminate\Database\Eloquent\Model as BaseModel;

use Illuminate\Support\Collection;

use Illuminate\Support\Facades\Cache;

use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Log;

use Illuminate\Support\Facades\Schema;

use Exception;

use RecursiveDirectoryIterator;

use RecursiveIteratorIterator;

use RegexIterator;

class InheritableModel extends BaseModel
{
    public static $name = 'Inheritable Model';

    public static $name_plural = 'Inheritable Models';

    public $table = 'base_models';

    protected $fillable = [];

    protected $guarded = [];

    protected $hidden = [];

    protected $parent_ = null;

    public $timestamps = false;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }
    public function newEloquentBuilder($query)
    {
        return new Builder($query);
    }
    protected function newBaseQueryBuilder()
    {
        $connection = $this->getConnection();

        return new QueryBuilder($connection, $connection->getQueryGrammar(), $connection->getPostProcessor());
    }
    public function __set($key, $value)
    {
        if($key == 'common_id')
        {
            if(static::class == self::class)

                return $this->setAttribute('id', $value);

            else return $this->setAttribute($key, $value);
        }
        else if($this->hasAttribute($key)) return $this->setAttribute($key, $value);

        else if(static::class == self::class) return $this->setAttribute($key, $value);

        else return $this->parent_model()->$key = $value;
    }
    public function tableForAttribute($attr)
    {
        $entity_class = $this->entityClassForAttribute($attr);

        $result = $entity_class::tableName();
      
        return $result;
    }
    public function entityClassForAttribute($attr)
    {
        $entity = $this;

        if($attr === 'id') return $this;

        while(!$entity->hasAttribute($attr) && \get_class($entity) !== self::class)

            $entity = $entity->parent_model();

        if(($entity_class = \get_class($entity)) === self::class)

            return $this->hasAttribute($attr);

        else return $entity_class;
    }
    public static function createNew($entity_class, array $attributes)
    {
        return static::unguarded(function () use ($entity_class, $attributes)
        {
            $entity = new $entity_class;
          
            $entity->fill($attributes);

            if(isset($attributes['id']))
              
                $entity->exists = true;

            return $entity;
        });
    }
    public static function topClassWithCommonId($common_id)
    {
        $cache_key = '#' . $common_id;

        if(Cache::has($cache_key))

            return Cache::get($cache_key);

        else
        {
            $top_class = DB

                ::table(InheritableModel::tableName())

                ->where('id', '=', $common_id)

                ->value('top_class');

            if($top_class)

                Cache::forever('#' . $common_id, $top_class);

            return $top_class;
        }
    }
    public static function elevateMultiple($models)
    {
        $is_collection = $models instanceof Collection;

        // group into model types

        $groups = [];

        foreach($models as $model)
        {
            $common_id = $model->common_id;

            $class = get_class($model);

            $top_class = self::topClassWithCommonId($common_id);

            if($class !== $top_class)
            {
                if(!isset($groups[$top_class]))

                    $groups[$top_class] = [];

                $groups[$top_class][] = $common_id;
            }
        }
        // elevate in groups

        $elevated = [];

        foreach($groups as $top_class => $group)
        {
            $elevation = $top_class::whereIn('common_id', $group)->get();

            foreach($elevation as $model)

                $elevated[$model->common_id] = $model;
        }
        // reorder collection

        $elevated_models = [];

        foreach($models as $i => $model)
        {
            $common_id = $model->common_id;

            if(isset($elevated[$common_id]))

                $elevated_models[] = $elevated[$common_id];

            else $elevated_models[] = $model;
        }
        if($is_collection) $elevated_models = collect($elevated_models);

        return $elevated_models;
    }
    public function getCommonId()
    {
        return $this->common_id;
    }
    public function __get($key)
    {
        switch($key)
        {
            case 'common_id':

                if(static::class === self::class)

                    return $this->id;

                else return $this->getAttribute('common_id');

                break;

            case 'fillable':

                return $this->getRecursiveFillable(); break;

            case 'guarded':

                return $this->getRecursiveGuarded(); break;

            case 'hidden':

                return $this->getRecursiveHidden(); break;

            case 'columns':

                return $this->getRecursiveColumns(); break;

            default:

                if($this->hasAttribute($key) || static::class === self::class)

                    return $this->getAttribute($key);

                else $this->parent_model()->$key;
        }
    }
    public function id_as($entity_class_)
    {
        $entity = $this;

        while(($entity_class = \get_class($entity)) !== $entity_class_ && $entity_class !== self::class)

            $entity = $entity->parent_model();

        return $entity->id;
    }
    // given attributes are optional and just for when the object is created but not yet saved to database

    protected function parent_model($given_attributes = [])
    {
        $func = function() use($given_attributes)
        {
            if($this->parent_ === null)
            {
                $parent_class = get_parent_class($this);

                if($parent_class == BaseModel::class)
                {
                    $this->parent_ = false;

                    return false;
                }
                if($parent_class != self::class)

                    $common_id_column = 'common_id';

                else $common_id_column = 'id';

                if($this->hasAttribute('common_id') && ($common_id = $this->common_id))
                {
//                    $parent_table_name = $parent_class::tableName();

//                    $data = (array) DB::table($parent_table_name)->where($common_id_column, '=', $common_id)->first();

                    $data = [$common_id_column => $common_id];

                    if(\is_array($given_attributes) && \count($given_attributes) > 0)

                        $data = \array_merge($data, $given_attributes);
                }
                else $data = $given_attributes;

                if($data === null) $data = [];

                $this->parent_ = self::createNew($parent_class, $data);
            }
            else if(\is_array($given_attributes) && \count($given_attributes) > 0)

                $this->parent_->fill($given_attributes);

            return $this->parent_;
        };
        return $func();
    }
    public function elevate()
    {
        $common_id = $this->common_id;
        
        $top_class = self::topClassWithCommonId($common_id);
        
        $current_class = static::class;
        
        if($current_class !== $top_class)
        {
            if($top_class !== self::class)

                $top_entity = $top_class::where('common_id', $common_id)->first();

            else $top_entity = $top_class::where('id', $common_id)->first();

            return $top_entity;
        }
        else return $this;
    }
    public function getFillable()
    {
        return $this->fillable ?? [];
    }
    public function getGuarded()
    {
        return $this->guarded ?? [];
    }
    public function getHidden()
    {
        return $this->hidden ?? [];
    }
    public function getDates()
    {
        return $this->dates ?? [];
    }
    public function getColumns()
    {
        $cache_key = self::class . '__getFields__' . static::class;

        if(Cache::has($cache_key))

            return Cache::get($cache_key);

        else
        {
            $table_name = $this->table;

            $table_columns = DB::connection()->getDoctrineSchemaManager()->listTableColumns($table_name);

            $columns = [];

            foreach($table_columns as $table_column)

                $columns[] = $table_column->getName();

            Cache::forever($cache_key, $columns);

            return $columns;
        }
    }
    public function getRecursiveHidden()
    {
        $cache_key = self::class . '__getRecursiveHidden__' . static::class;

        if(Cache::has($cache_key))

            return Cache::get($cache_key);

        else
        {
            $hidden = [];

            $chain = [];

            $entity = $this;

            while($entity !== null && \get_class($entity) !== self::class)
            {
                $chain[] = $entity;

                $entity = $entity->parent_model();
            }
            $chain = array_reverse($chain);

            foreach($chain as $item)

                $hidden = \array_merge($hidden, $item->getHidden());

            $hidden = array_unique($hidden);

            Cache::forever($cache_key, $hidden);

            return $hidden;
        }
    }
    public function getRecursiveFillable()
    {
        $cache_key = self::class . '__getRecursiveFillable__' . static::class;

        if(Cache::has($cache_key))

            return Cache::get($cache_key);

        else
        {
            $fillable = [];

            $chain = [];

            $entity = $this;

            while($entity !== null && \get_class($entity) !== self::class)
            {
                $chain[] = $entity;

                $entity = $entity->parent_model();
            }
            $chain = array_reverse($chain);

            foreach($chain as $item)

                $fillable = \array_merge($fillable, $item->getFillable());

            $fillable = array_unique($fillable);

            Cache::forever($cache_key, $fillable);

            return $fillable;
        }
    }
    public function getRecursiveColumns()
    {
        $cache_key = self::class . '__getRecursiveColumns__' . static::class;

        if(Cache::has($cache_key))

            return Cache::get($cache_key);

        else
        {
            $columns = [];

            $chain = [];

            $entity = $this;

            while($entity !== null && \get_class($entity) !== self::class)
            {
                $chain[] = $entity;

                $entity = $entity->parent_model();
            }
            $chain = array_reverse($chain);

            foreach($chain as $item)

                $columns = \array_merge($columns, $item->getColumns());

            $columns = array_unique($columns);

            Cache::forever($cache_key, $columns);

            return $columns;
        }
    }
    public function getRecursiveGuarded()
    {
        $cache_key = self::class . '__getRecursiveGuarded__' . static::class;

        if(Cache::has($cache_key))

            return Cache::get($cache_key);

        else
        {
            $guarded = [];

            $chain = [];

            $entity = $this;

            while($entity !== null && \get_class($entity) !== self::class)
            {
                $chain[] = $entity;

                $entity = $entity->parent_model();
            }
            $chain = array_reverse($chain);

            foreach($chain as $item)

                $guarded = \array_merge($guarded, $item->getGuarded());

            $guarded = array_unique($guarded);

            Cache::forever($cache_key, $guarded);

            return $guarded;
        }
    }
    public function getRecursiveDates()
    {
        $cache_key = self::class . '__getRecursiveDates__' . static::class;

        if(Cache::has($cache_key))

            return Cache::get($cache_key);
        
        else
        {
            $dates = [];

            $chain = [];

            $entity = $this;

            while($entity !== null && \get_class($entity) !== self::class)
            {
                $chain[] = $entity;

                $entity = $entity->parent_model();
            }
            $chain = array_reverse($chain);

            foreach($chain as $item)

                $dates = \array_merge($dates, $item->getDates());

            $dates = array_unique($dates);

            Cache::forever($cache_key, $dates);

            return $dates;
        }
    }
    public function fill(array $attributes_)
    {
        $immediately_fillable = [];

        $not_immediately_fillable = [];

        $attributes = [];

        $recursive_columns = $this->getRecursiveColumns();

        foreach($attributes_ as $key_ => $attribute_)

            if(in_array($key_, $recursive_columns) || \in_array($key_, ['common_id', 'id', 'top_class']))

                $attributes[$key_] = $attribute_;

        $columns = $this->getColumns();

        foreach($attributes as $key => $value)
        {
            if (!\in_array($key, $columns) && !\in_array($key, ['common_id', 'id']))

                $not_immediately_fillable[$key] = $value;

            else $immediately_fillable[$key] = $value;
        }
        if(\is_array($not_immediately_fillable) && \count($not_immediately_fillable) >= 1)

            // attributes given in case parent entities not created yet. this can occur when an entity is

            // created using the new keyword, given attributes pertaining to parents, but has not been saved.

            $this->parent_model($not_immediately_fillable);

        $result = parent::fill($immediately_fillable);

        if(isset($immediately_fillable['common_id']))
            
            $this->setAttribute('common_id', $immediately_fillable['common_id']);

        return $result;
    }
    public function save(array $options = [])
    {
        $this->fill($options);

        $static_class = static::class;

        $save_queue = [];

        $entity = $this;

        while(($current_class = \get_class($entity)) !== self::class)
        {
            $save_queue[] = $entity;

            $entity = $entity->parent_model();
        }
        $entity->__set('top_class', $static_class);

        $entity->raw_save();

        $common_id = $entity->common_id;

        $save_queue = array_reverse($save_queue);

        foreach($save_queue as $save_item)
        {
            $current_class = \get_class($save_item);

            if($current_class !== self::class)

                $save_item->common_id = $common_id;

            $save_item->raw_save();
        }
    }
    private function raw_save($options = [])
    {
        $query = $this->newQueryWithoutScopes();

        if ($this->fireModelEvent('saving') === false) return false;

        if ($this->exists)

            $saved = $this->isDirty()?$this->performUpdate($query, $options) : true;
      
        else
        {
            $saved = $this->performInsert($query);

            // TODO: Debug this. Does the new entity even have top_class / parent classes registered?
        }

        if ($saved)
        {
            $this->finishSave($options);

            Cache::forever('#' . $this->common_id, $this->top_class);
        }
        return $saved;
    }
    public function delete()
    {
        if(is_null($this->getKeyName()))

            throw new Exception('No primary key defined on model.');

        if($this->exists)
        {
            $parent_model = $this->parent_model();

            if($this->fireModelEvent('deleting') === false)

                return false;

            $this->touchOwners();

            $this->performDeleteOnModel();

            $this->exists = false;

            $this->fireModelEvent('deleted', false);

            if($parent_model == null)
            {
                Cache::forget('#' . $this->common_id);

                return true;
            }
            else return $parent_model->delete();
        }
    }
    public static function tableName($field_name = null)
    {
        return with(new static)->getTableName($field_name);
    }
    public function getTableName($field_name = null)
    {
        $cache_key = self::class . '__getTableName__' . static::class . '__' . $field_name;

        if(Cache::has($cache_key))

            return Cache::get($cache_key);

        else
        {
            if($field_name !== null && !$this->hasAttribute($field_name))
            {
                $parent = $this->parent_model();

                if($parent === false || $field_name === null)

                    throw new Exception('Could not find field ' . $field_name . ', reached the top of the parent tree for ' . static::class . '.');

                $table_name = $parent->table_name($field_name);
            }
            else $table_name = with(new static)->getTable();

            Cache::forever($cache_key, $table_name);

            return $table_name;
        }
    }
    public function hasAttribute($key)
    {
        $cache_key = self::class . '__hasAttribute__' . static::class . '__' . $key;

        if(Cache::has($cache_key))

            return Cache::get($cache_key);

        else
        {
            $has_attribute = Schema::hasColumn($this->getTable(), $key) || Schema::hasColumn($this->getTable(), $key . '_id');

            Cache::forever($cache_key, $has_attribute);

            return $has_attribute;
        }
    }
    private static function getClasses()
    {
        $classes = array();

        $allFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path()));

        $phpFiles = new RegexIterator($allFiles, '/\.php$/');

        foreach ($phpFiles as $phpFile)
        {
            $path = $phpFile->getRealPath();

            $content = file_get_contents($path);

            if(!instr('public $table', $content)) continue;

            $tokens = token_get_all($content);

            $namespace = '';

            for ($index = 0; isset($tokens[$index]); $index++)
            {
                if (!isset($tokens[$index][0]))

                    continue;

                if (T_NAMESPACE === $tokens[$index][0])
                {
                    $index += 2; // Skip namespace keyword and whitespace

                    while (isset($tokens[$index]) && is_array($tokens[$index]))

                        $namespace .= $tokens[$index++][1];
                }
                if (T_CLASS === $tokens[$index][0] && T_WHITESPACE === $tokens[$index + 1][0] && T_STRING === $tokens[$index + 2][0])
                {
                    $index += 2; // Skip class keyword and whitespace

                    $classes[] = [$path, $namespace.'\\'.$tokens[$index][1]];

                    break;
                }
            }
        }
        $inheritable_models = [];

        foreach($classes as $class)
        {
            if(class_exists($class[1]) && is_a($class[1], static::class, true))

                $inheritable_models[] = $class[1];
        }
        return $inheritable_models;
    }
    public static function tableClasses()
    {
        $cache_key = self::class . '__tableClasses';

        if(Cache::has($cache_key))

            return Cache::get($cache_key);

        else
        {
            $classes = self::getClasses();

            $table_classes = [];

            foreach($classes as $class)
            {
                $table_classes[$class::tableName()] = $class;
            }
            Cache::forever($cache_key, $table_classes);

            return $table_classes;
        }
    }
    public static function classForTableName($table_name)
    {
        $table_classes = self::tableClasses();
        
        if(isset($table_classes[$table_name]))
            
            return $table_classes[$table_name];
        
        else return null;
    }
}
