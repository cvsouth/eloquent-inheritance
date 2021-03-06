<?php namespace Cvsouth\EloquentInheritance;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Query\Builder as BaseQueryBuilder;

use Illuminate\Pagination\Paginator;

class QueryBuilder extends BaseQueryBuilder
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
    public function getModelClass()
    {
        return get_class($this->model());
    }
    public function model()
    {
        if($this->model !== null) return $this->model;

        else
        {
            $from = $this->from;

            $class = InheritableModel::classForTableName($from);

            $model = new $class;

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

        $inheritable_model = new InheritableModel();

        if($column === "top_class")

            $table = $inheritable_model->tableName();

        else
        {
            $recursive_columns = $model->getRecursiveColumns();

            if(!$recursive_columns || !in_array($column, $recursive_columns)) return $column;

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
            $base_id_column = (($table === $inheritable_model->tableName()) ? ($table . ".id") : ($table . ".base_id"));

            $this->join($table, $base_id_column, "=", $this->from . ".base_id");
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
