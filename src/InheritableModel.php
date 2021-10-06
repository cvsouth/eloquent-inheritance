<?php namespace Cvsouth\EloquentInheritance;

use Illuminate\Database\Eloquent\Concerns\HasRelationships;

use Illuminate\Database\Eloquent\Concerns\HasTimestamps;

use Illuminate\Database\Eloquent\Model as BaseModel;

use Illuminate\Database\Eloquent\Collection;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection as BaseCollection;

use Illuminate\Support\Carbon;

use Illuminate\Support\Facades\Cache;

use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Log;

use Illuminate\Support\Facades\Schema;

use DateTime;

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

    public $timestamps = true;

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
    protected static function boot()
    {
        parent::boot();

        static::creating(function($model)
        {
            if($model->timestamps) $model->created_at = Carbon::now();
        });
        static::updating(function($model)
        {
            if($model->timestamps) $model->updated_at = Carbon::now();
        });
    }
    public function __set($key, $value)
    {
        if($key == 'base_id')
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
        $model_class = $this->modelClassForAttribute($attr);

        $result = $model_class::tableName();

        return $result;
    }
    public function modelClassForAttribute($attr)
    {
        $model = $this;

        if($attr === 'id') return $this;

        while(!$model->hasAttribute($attr) && \get_class($model) !== self::class)

            $model = $model->parent_model();

        if(($model_class = \get_class($model)) === self::class)

            return $this->hasAttribute($attr);

        else return $model_class;
    }
    public static function createNew($model_class, array $attributes)
    {
        return static::unguarded(function () use ($model_class, $attributes)
        {
            $model = new $model_class;

            $model->fill($attributes);

            if(isset($attributes['id']))

                $model->exists = true;

            return $model;
        });
    }
    public static function topClassWithBaseId($base_id)
    {
        $cache_key = self::class . '#' . $base_id;

        if(Cache::has($cache_key))

            return Cache::get($cache_key);

        else
        {
            $top_class = DB

                ::table(InheritableModel::tableName())

                ->where('id', '=', $base_id)

                ->value('top_class');

            if($top_class)

                Cache::forever(self::class . '#' . $base_id, $top_class);

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
            $base_id = $model->base_id;

            $class = get_class($model);

            if(!($model instanceof self))

                $top_class = $class;

            else $top_class = self::topClassWithBaseId($base_id);

            if($class !== $top_class)
            {
                if(!isset($groups[$top_class]))

                    $groups[$top_class] = [];

                $groups[$top_class][] = $base_id;
            }
        }
        // elevate in groups

        $elevated = [];

        foreach($groups as $top_class => $group)
        {
            $elevation = $top_class::whereIn('base_id', $group)->get();

            foreach($elevation as $model)

                $elevated[$model->base_id] = $model;
        }
        // reorder collection

        $elevated_models = [];

        foreach($models as $i => $model)
        {
            $base_id = $model->base_id;

            if(isset($elevated[$base_id]))

                $elevated_models[] = $elevated[$base_id];

            else $elevated_models[] = $model;
        }
        if($is_collection) $elevated_models = new Collection($elevated_models);

        return $elevated_models;
    }
    public function getBaseId()
    {
        return $this->base_id;
    }
    public function __get($key)
    {
        switch($key)
        {
            case 'base_id':

                if(static::class === self::class)

                    return $this->id;

                else return $this->getAttribute('base_id');

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

                $value = $this->getAttribute($key);

                if(!empty($value) || static::class === self::class || $this->hasAttribute($key))

                    return $value;

                else return $this->parent_model()->$key;
        }
    }
    public function toArray()
    {
        return array_merge($this->recursiveAttributesToArray(), $this->relationsToArray());
    }
    public function recursiveAttributesToArray()
    {
        $attributes = $this->addDateAttributesToArray
        (
            $attributes = $this->getRecursiveArrayableAttributes()
        );
        $attributes = $this->addMutatedAttributesToArray
        (
            $attributes, $mutatedAttributes = $this->getMutatedAttributes()
        );
        $attributes = $this->addCastAttributesToArray
        (
            $attributes, $mutatedAttributes
        );
        foreach($this->getArrayableAppends() as $key)

            $attributes[$key] = $this->mutateAttributeForArray($key, null);

        return $attributes;
    }
    protected function getRecursiveArrayableAttributes()
    {
        return $this->getArrayableItems($this->getRecursiveAttributes());
    }
    public function getRecursiveAttributes()
    {
        $columns = $this->getRecursiveColumns();

        if($columns === false) return [];

        $attributes = [];

        foreach($columns as $column)
        {
            $value = $this->$column;

            $attributes[$column] = $value;
        }
        return $attributes;
    }
    public function updated_at_as($model_class_)
    {
        $column = $this->getUpdatedAtColumn();

        $model = $this;

        while(($model_class = \get_class($model)) !== $model_class_ && $model_class !== self::class)

            $model = $model->parent_model();

        return $model->hasColumn($column) ? $model->$column : null;
    }
    public function created_at_as($model_class_)
    {
        $column = $this->getCreatedAtColumn();

        $model = $this;

        while(($model_class = \get_class($model)) !== $model_class_ && $model_class !== self::class)

            $model = $model->parent_model();

        return $model->hasColumn($column) ? $model->$column : null;
    }
    public function hasRecursiveColumn($column)
    {
        return in_array($column, $this->getRecursiveColumns());
    }
    public function hasColumn($column)
    {
        return in_array($column, $this->getColumns());
    }
    public function id_as($model_class_)
    {
        $model = $this;

        while(($model_class = \get_class($model)) !== $model_class_ && $model_class !== self::class)

            $model = $model->parent_model();

        return $model->id;
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

                    $base_id_column = 'base_id';

                else $base_id_column = 'id';

                if($this->hasAttribute('base_id') && ($base_id = $this->base_id))
                {
                    $parent_table_name = $parent_class::tableName();

                    $data = (array) DB::table($parent_table_name)->where($base_id_column, '=', $base_id)->first();

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
        $base_id = $this->base_id;

        $top_class = self::topClassWithBaseId($base_id);

        $current_class = static::class;

        if($current_class !== $top_class)
        {
            if($top_class !== self::class)

                $top_model = $top_class::where('base_id', $base_id)->first();

            else $top_model = $top_class::where('id', $base_id)->first();

            return $top_model;
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

            $columns = [];

            if(DB::connection()->getDoctrineSchemaManager()->tablesExist([$table_name]))
            {
                $table_columns = DB::connection()->getDoctrineSchemaManager()->listTableColumns($table_name);

                foreach($table_columns as $table_column)

                    $columns[] = $table_column->getName();

                Cache::forever($cache_key, $columns);

                return $columns;
            }
            else return false;
        }
    }
    public function getRecursiveHidden()
    {
        $cache_key = self::class . '__getRecursiveHidden__' . static::class;

        if(Cache::has($cache_key))

            return Cache::get($cache_key);

        else
        {
            $hidden = $this->getHidden();

            $chain = [];

            $model = $this;

            while($model !== null && \get_class($model) !== self::class)
            {
                $chain[] = $model;

                $model = $model->parent_model();
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
            $fillable = $this->getFillable();

            $chain = [];

            $model = $this;

            while($model !== null && \get_class($model) !== self::class)
            {
                $chain[] = $model;

                $model = $model->parent_model();
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
            $columns = $this->getColumns();

            if($columns === false) return false;

            $chain = [];

            $model = $this;

            while($model !== null && \get_class($model) !== self::class)
            {
                $chain[] = $model;

                $model = $model->parent_model();
            }
            $chain = array_reverse($chain);

            foreach($chain as $item)
            {
                $item_columns = $item->getColumns();

                if($item_columns === false) return false;

                $columns = \array_merge($columns, $item_columns);
            }

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
            $guarded = $this->getGuarded();

            $chain = [];

            $model = $this;

            while($model !== null && \get_class($model) !== self::class)
            {
                $chain[] = $model;

                $model = $model->parent_model();
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
            $dates = $this->getDates();

            $chain = [];

            $model = $this;

            while($model !== null && \get_class($model) !== self::class)
            {
                $chain[] = $model;

                $model = $model->parent_model();
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

            if(($recursive_columns && in_array($key_, $recursive_columns)) || \in_array($key_, ['base_id', 'id', 'top_class']))

                $attributes[$key_] = $attribute_;

        $columns = $this->getColumns();

        if($columns === false) $columns = [];

        foreach($attributes as $key => $value)
        {
            if (!\in_array($key, $columns) && !\in_array($key, ['base_id', 'id']))

                $not_immediately_fillable[$key] = $value;

            else $immediately_fillable[$key] = $value;
        }
        if(\is_array($not_immediately_fillable) && \count($not_immediately_fillable) >= 1)

            // attributes given in case parent entities not created yet. this can occur when an model is

            // created using the new keyword, given attributes pertaining to parents, but has not been saved.

            $this->parent_model($not_immediately_fillable);

        $result = parent::fill($immediately_fillable);

        if(isset($immediately_fillable['base_id']))

            $this->setAttribute('base_id', $immediately_fillable['base_id']);

        return $result;
    }
    public function save(array $options = [])
    {
        $this->fill($options);

        $static_class = static::class;

        $save_queue = [];

        $model = $this;

        while(($current_class = \get_class($model)) !== self::class)
        {
            $save_queue[] = $model;

            $model = $model->parent_model();
        }
        $model->__set('top_class', $static_class);

        $model->raw_save();

        $base_id = $model->base_id;

        $save_queue = array_reverse($save_queue);

        foreach($save_queue as $save_item)
        {
            $current_class = \get_class($save_item);

            if($current_class !== self::class)

                $save_item->base_id = $base_id;

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
            $saved = $this->performInsert($query);

        if ($saved)
        {
            $this->finishSave($options);

            Cache::forever(self::class . '#' . $this->base_id, $this->top_class);
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
                Cache::forget(self::class . '#' . $this->base_id);

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

                $table_name = $parent->tableName($field_name);
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
    public static function classes()
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
            $classes = self::classes();

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
    protected function getRelationshipFromMethod($method)
    {
        $relation = $this->$method();

        if (! $relation instanceof Relation)
        {
            if (is_null($relation))

                throw new LogicException(sprintf('%s::%s must return a relationship instance, but "null" was returned. Was the "return" keyword used?', static::class, $method));

            throw new LogicException(sprintf('%s::%s must return a relationship instance.', static::class, $method));
        }
        $value = $relation->getResults();

        if($value instanceof self)

            $value = $value->elevate();

        elseif($value instanceof Collection)

            $value = static::elevateMultiple($value);

        return tap($value, function ($results) use ($method)
        {
            $this->setRelation($method, $results);
        });
    }
    public static function select()
    {
        $model = new static;

        return $model->forwardCallTo($model->newQuery(), 'select', func_get_args());
    }
    public static function where()
    {
        $model = new static;

        return $model->forwardCallTo($model->newQuery(), 'where', func_get_args());
    }
    public static function chunk()
    {
        $model = new static;

        return $model->forwardCallTo($model->newQuery(), 'chunk', func_get_args());
    }
    public static function addSelect()
    {
        $model = new static;

        return $model->forwardCallTo($model->newQuery(), 'addSelect', func_get_args());
    }
    public static function cursor()
    {
        $model = new static;

        return $model->forwardCallTo($model->newQuery(), 'cursor', func_get_args());
    }
    public static function orderByDesc()
    {
        $model = new static;

        return $model->forwardCallTo($model->newQuery(), 'orderByDesc', func_get_args());
    }
    public static function orderByAsc()
    {
        $model = new static;

        return $model->forwardCallTo($model->newQuery(), 'orderByAsc', func_get_args());
    }
    public static function find()
    {
        $model = new static;

        return $model->forwardCallTo($model->newQuery(), 'find', func_get_args());
    }
    public static function findOrFail()
    {
        $model = new static;

        return $model->forwardCallTo($model->newQuery(), 'findOrFail', func_get_args());
    }
    public static function create()
    {
        $model = new static;

        return $model->forwardCallTo($model->newQuery(), 'create', func_get_args());
    }
    public static function firstOrCreate()
    {
        $model = new static;

        return $model->forwardCallTo($model->newQuery(), 'firstOrCreate', func_get_args());
    }
    public static function firstOrNew()
    {
        $model = new static;

        return $model->forwardCallTo($model->newQuery(), 'firstOrNew', func_get_args());
    }
    public static function updateOrCreate()
    {
        $model = new static;

        return $model->forwardCallTo($model->newQuery(), 'updateOrCreate', func_get_args());
    }
    public static function withTrashed()
    {
        $model = new static;

        return $model->forwardCallTo($model->newQuery(), 'withTrashed', func_get_args());
    }
    public static function onlyTrashed()
    {
        $model = new static;

        return $model->forwardCallTo($model->newQuery(), 'onlyTrashed', func_get_args());
    }
    public static function withoutGlobalScope()
    {
        $model = new static;

        return $model->forwardCallTo($model->newQuery(), 'withoutGlobalScope', func_get_args());
    }
    public static function withoutGlobalScopes()
    {
        $model = new static;

        return $model->forwardCallTo($model->newQuery(), 'withoutGlobalScopes', func_get_args());
    }
    public static function popular()
    {
        $model = new static;

        return $model->forwardCallTo($model->newQuery(), 'popular', func_get_args());
    }
    public static function ofType()
    {
        $model = new static;

        return $model->forwardCallTo($model->newQuery(), 'ofType', func_get_args());
    }
    public static function has()
    {
        $model = new static;

        return $model->forwardCallTo($model->newQuery(), 'has', func_get_args());
    }
    public static function whereHas()
    {
        $model = new static;

        return $model->forwardCallTo($model->newQuery(), 'whereHas', func_get_args());
    }
    public static function whereNull()
    {
        $model = new static;

        return $model->forwardCallTo($model->newQuery(), 'whereNull', func_get_args());
    }
    public static function whereNotNull()
    {
        $model = new static;

        return $model->forwardCallTo($model->newQuery(), 'whereNotNull', func_get_args());
    }
    public static function whereIn()
    {
        $model = new static;

        return $model->forwardCallTo($model->newQuery(), 'whereIn', func_get_args());
    }
    public static function doesntHave()
    {
        $model = new static;

        return $model->forwardCallTo($model->newQuery(), 'doesntHave', func_get_args());
    }
    public static function whereDoesntHave()
    {
        $model = new static;

        return $model->forwardCallTo($model->newQuery(), 'whereDoesntHave', func_get_args());
    }
    public static function whereHasMorph()
    {
        $model = new static;

        return $model->forwardCallTo($model->newQuery(), 'whereHasMorph', func_get_args());
    }
    public static function whereDoesntHaveMorph()
    {
        $model = new static;

        return $model->forwardCallTo($model->newQuery(), 'whereDoesntHaveMorph', func_get_args());
    }
    public static function withCount()
    {
        $model = new static;

        return $model->forwardCallTo($model->newQuery(), 'withCount', func_get_args());
    }
    public static function count()
    {
        $model = new static;

        return $model->forwardCallTo($model->newQuery(), 'count', func_get_args());
    }
    public static function first()
    {
        $model = new static;

        return $model->forwardCallTo($model->newQuery(), 'first', func_get_args());
    }
    public static function without()
    {
        $model = new static;

        return $model->forwardCallTo($model->newQuery(), 'without', func_get_args());
    }
}
