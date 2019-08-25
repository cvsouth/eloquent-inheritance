<?php namespace Cvsouth\EloquentInheritance;

use Cvsouth\EloquentInheritance\Facades\EloquentInheritance;

use Cvsouth\EloquentInheritance\Enums\ColumnType;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Support\Collection;

use Illuminate\Support\Facades\Cache;

use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Log;

use Illuminate\Support\Facades\Schema;

use Exception;

class InheritableModel extends Model
{
    public static $name = 'Inheritable Model';

    public static $name_plural = 'Inheritable Models';

    public $table = 'base_models';

    protected $fillable = ['id', 'top_class', 'base_id'];

    protected $guarded = [];

    protected $hidden = [];

    protected $parent_ = null;

    public $timestamps = false;

    protected $loaded = [];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }
    public function newEloquentBuilder($query)
    {
        return new InheritableBuilder($query);
    }
    protected function newBaseQueryBuilder()
    {
        $connection = $this->getConnection();

        return new InheritableQueryBuilder($connection, $connection->getQueryGrammar(), $connection->getPostProcessor());
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
    public function getBaseId()
    {
        return $this->base_id;
    }
    public function fetchLocalAttributes()
    {
        $table = static::tableName();
        
        $base_id = $this->getBaseId();
        
        $attributes_array = $this->fillable;
        
        $attributes_array[] = 'id';

        $data = (array) DB::table($table)->where('base_id', '=', $base_id)->first();

        foreach($data as $key => $value)
        
            if(in_array($key, $attributes_array))
            
                $this->setAttribute($key, $value);

        return true;
    }
    public function __get($attr_)
    {
        switch($attr_)
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

            case 'fields':

                return $this->getRecursiveFields(); break;

            default:

                $attr = $this->getAttribute($attr_);

                if(static::class === self::class)

                    return $attr;

                else if($attr === null && !$this->hasAttribute($attr_))

                    return $this->parent_model()->$attr_;
            
                else if($attr === null && !array_key_exists($attr_, $this->attributesToArray()))
                {
                    $this->fetchLocalAttributes();

                    return $this->getAttribute($attr_);
                }
                else return $attr;
        }
    }
    public function id_as($entity_class_)
    {
        if($entity_class_ === null)

            throw new Exception('InheritableModel::id_as() called without entity_class being specified (NULL)');

        $entity = $this;

        while(($entity_class = \get_class($entity)) !== $entity_class_ && $entity_class !== self::class)

            $entity = $entity->parent_model();

        return $entity->id;
    }
    public function model_as($entity_class_)
    {
        return EloquentInheritance::getWithBaseId($this->base_id, $entity_class_);
    }
    // given attributes are optional and just for when the object is created but not yet saved to database

    protected function parent_model($given_attributes = [])
    {
        $func = function() use($given_attributes)
        {
            if($this->parent_ === null)
            {
                $parent_class = get_parent_class($this);

                if($parent_class == Model::class)
                {
                    $this->parent_ = false;

                    return false;
                }
                if($parent_class != self::class)

                    $base_id_column = 'base_id';

                else $base_id_column = 'id';

                if($this->hasAttribute('base_id') && ($base_id = $this->base_id))
                {
//                    $parent_table_name = $parent_class::tableName();

//                    $data = (array) DB::table($parent_table_name)->where($base_id_column, '=', $base_id)->first();

                    $data = [$base_id_column => $base_id];

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
    public static function getWithBaseId($base_id, $entity_class = null, $elevate = true)
    {
        if($entity_class === null)

            $entity_class = self::class;

        if($entity_class !== self::class)

            $entity = $entity_class::where('base_id', $base_id)->first();

        else $entity = $entity_class::where('id', $base_id)->first();

        return $entity;
    }
    public static function getWithID($entity_class, $id)
    {
        return $entity_class::where('id', $id)->first();
    }
    public function parent()
    {
        return $this->parent_;
    }
    public function set_parent($entity)
    {
        $this->parent_ = $entity;
    }
    public function elevate($attributes = [])
    {
        $base_id = $this->base_id;
        
        $top_class = EloquentInheritance::topClassWithBaseId($base_id); // TODO: Try using inline code instead of Service call at high volume and whether it is better to do that for performance reasons
        
        $current_class = static::class;
        
        if($current_class !== $top_class)
        {
            $top_entity = EloquentInheritance::getWithBaseId($base_id, $top_class);

//            $attributes['base_id'] = $base_id;
//
//            $top_entity = new $top_class($attributes);
//
//            $entity = $top_entity;
//
//            while($entity->parent() === null)
//            {
//                if(get_parent_class($entity) === $current_class)
//
//                    $entity->set_parent($this);
//
//                else $entity = $entity->parent_model($attributes);
//            }
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
    public function getColumns()
    {
        return $this->columns ?? [];
    }
    public function getHiddenColumns()
    {
        return $this->hidden_columns ?? [];
    }
    public function getFields()
    {
        return $this->fields ?? [];
    }
    public function getHiddenFields()
    {
        return $this->hidden_fields ?? [];
    }
    public function getDates()
    {
        return $this->dates ?? [];
    }
    public function getRecursiveHidden()
    {
        $cache_key = 'Entity__getRecursiveHidden__' . static::class;

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
        $cache_key = 'Entity__getRecursiveFillable__' . static::class;

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
    public function getRecursiveGuarded()
    {
        $cache_key = 'Entity__getRecursiveGuarded__' . static::class;

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
        $cache_key = 'Entity__getRecursiveDates__' . static::class;

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
    public function getRecursiveFields()
    {
        $cache_key = 'Entity__getRecursiveFields__' . static::class;

        if(Cache::has($cache_key))

            return Cache::get($cache_key);

        else
        {
            $fields = [];

            $chain = [];

            $entity = $this;

            while($entity !== null && \get_class($entity) !== self::class)
            {
                $chain[] = $entity;

                $entity = $entity->parent_model();
            }
            $chain = array_reverse($chain);

            foreach($chain as $item)
            {
                $fields_ = $item->getFields();

                $hidden_fields_ = $item->getHiddenFields();

                $fields = array_diff($fields, $fields_);

                $fields = \array_merge($fields, $fields_);

                $fields = array_diff($fields, $hidden_fields_);
            }
            $fields = array_unique($fields);

            $fields = $this->assumeColumnPrefixing($fields);

            Cache::forever($cache_key, $fields);

            return $fields;
        }
    }
    public function getRecursiveColumns()
    {
        $cache_key = 'Entity__getRecursiveColumns__' . static::class;

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
            {
                $columns_ = $item->getColumns();

                $hidden_columns_ = $item->getHiddenColumns();

                $columns = array_diff($columns, $columns_);

                $columns = \array_merge($columns, $columns_);

                $columns = array_diff($columns, $hidden_columns_);
            }
            $columns = array_unique($columns);

            $columns = $this->assumeColumnPrefixing($columns);

            Cache::forever($cache_key, $columns);

            return $columns;
        }
    }
    public function assumeColumnPrefixing($identifiers, $type = null)
    {
        if($type === null)

            $type = ModelType::From(static::class);

        $entity_class = $type->entity_class;

        $phantom = new $entity_class;

        foreach($identifiers as $i => $identifier)

            if(!strpos($identifier, ':'))
            {
                $table = $phantom->tableForAttribute($identifier);

                $identifiers[$i] = ColumnType::STANDARD . ':' . $table . ':' . $identifier;
            }
        return $identifiers;
    }
    public function fill(array $attributes_)
    {
        $immediately_fillable = [];

        $not_immediately_fillable = [];

        $attributes = [];

        $recursive_fillable = $this->getRecursiveFillable();

        foreach($attributes_ as $key_ => $attribute_)

            if(in_array($key_, $recursive_fillable) || \in_array($key_, ['base_id', 'id', 'top_class']))

                $attributes[$key_] = $attribute_;

        $fillable = $this->fillable;

        foreach($attributes as $key => $value)
        {
            if (!\in_array($key, $fillable) && !\in_array($key, ['base_id', 'id']))

                $not_immediately_fillable[$key] = $value;

            else $immediately_fillable[$key] = $value;
        }
        if(\is_array($not_immediately_fillable) && \count($not_immediately_fillable) >= 1)

            // attributes given in case parent entities not created yet. this can occur when an entity is

            // created using the new keyword, given attributes pertaining to parents, but has not been saved.

            $this->parent_model($not_immediately_fillable);

        $result = parent::fill($immediately_fillable);

        if(isset($immediately_fillable['base_id']))
            
            $this->setAttribute('base_id', $immediately_fillable['base_id']);

        return $result;
    }
    public function attributeEntity($attribute_name)
    {
        $attribute_name = preg_replace('/_id$/', '', $attribute_name);

        return $this->{$attribute_name};
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

        $base_id = $entity->base_id;

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
        {
            $saved = $this->performInsert($query);

            // TODO: Debug this. Does the new entity even have top_class / parent classes registered?

            Cache::forever('#' . $this->base_id, $this->top_class);
        }

        if ($saved) $this->finishSave($options);

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
                Cache::forget('#' . $this->base_id);

                return true;
            }
            else return $parent_model->delete();
        }
    }

    public static function tableName($field_name = null)
    {
        return with(new static)->table_name($field_name);
    }
    public function table_name($field_name = null)
    {
        $cache_key = 'Entity__table_name__' . static::class . '__' . $field_name;

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
        $cache_key = 'Entity__hasAttribute__' . static::class . '__' . $key;

        if(Cache::has($cache_key))

            return Cache::get($cache_key);

        else
        {
            $has_attribute = Schema::hasColumn($this->getTable(), $key);

            Cache::forever($cache_key, $has_attribute);

            return $has_attribute;
        }
    }
}
