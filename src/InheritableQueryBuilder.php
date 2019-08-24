<?php namespace Cvsouth\EloquentInheritance;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Query\Builder as QueryBuilder;

use Illuminate\Pagination\Paginator;

class InheritableQueryBuilder extends QueryBuilder
{
    private $model = null;

    public function orderBy($column, $direction = 'asc')
    {
        $column = $this->prefixColumn($column, true);

        $query = parent::orderBy($column, $direction);

        return $query;
    }
    public function setModel(Model $model)
    {
        $this->model = $model;
    }
    public function getEntityClass()
    {
        return get_class($this->model());
    }
    public function model()
    {
        if($this->model !== null) return $this->model;
        
        else
        {
            $from = $this->from;
           
            $entity_class = ModelType::FromTableName($from)->entity_class;
            
            $model = new $entity_class;
           
            return $model;
        }
    }
    public function prefixColumn($column, $join_if_necessary = false)
    {
        // ignore wildcards
        
        if($column === "*" || (substr($column, -2) === ".*"))
        
            return $column;

        // already prefixed?
       
        if(strpos($column, ".") !== false)
       
            return $column;

        $model = $this->model();
      
        $entity_model = new Entity;

        if($column === $entity_model->getUpdatedAtColumn()
       
        || $column === "top_class")

            $table = $entity_model->table_name();

        else
        {
            // column is part of entity hierarchy?
            
            if(!in_array($column, $model->getRecursiveFillable())) return $column;

            // get target class/table
            
            $table = $model->tableForAttribute($column);
        }
        // prefix
        
        $column = $table . "." . $column;

        if($table === $this->from)
     
            return $column;

        // add to join if necessary
        
        if($join_if_necessary)
        {
            $joins = $this->joins;
    
            if($joins)
            {
                foreach($joins as $join)
           
                    if($join->table === $table)
           
                        return $column;
            }
            $entity_id_column = (($table === $entity_model->table_name()) ? ($table . ".id") : ($table . ".entity_id"));
            
            $this->join($table, $entity_id_column, "=", $this->from . ".entity_id");
        }
        return $column;
    }
    public function select($columns = ['*'])
    {
        $this->columns = is_array($columns) ? $columns : func_get_args();

        return $this;
    }
    public function addSelect($column)
    {
        $column = is_array($column) ? $column : func_get_args();

        $this->columns = array_merge((array) $this->columns, $column);

        return $this;
    }
}
