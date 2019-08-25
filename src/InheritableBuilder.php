<?php namespace Cvsouth\EloquentInheritance;

use Cvsouth\EloquentInheritance\Facades\EloquentInheritance;
use Cvsouth\EloquentInheritance\Facades\Entities;

use Illuminate\Database\Eloquent\Builder;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Pagination\Paginator;

class InheritableBuilder extends Builder
{
    public function setModel(Model $model)
    {
        $builder = parent::setModel($model);
       
        $this->query->setModel($model);
        
        return $builder;
    }
    public function get($columns = ['*'], $elevate = true)
    {
        $collection = parent::get($columns);

        if($elevate) $collection = EloquentInheritance::elevateMultiple($collection);
      
        return $collection;
    }
    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        if (is_string($column))
            
            $column = $this->query->prefixColumn($column, true);
        
        return parent::where($column, $operator, $value, $boolean);
    }
    public function increment($column, $amount = 1, array $extra = [])
    {
        $column = $this->query->prefixColumn($column, true);
        
        return parent::increment($column, $amount, $extra);
    }
    public function decrement($column, $amount = 1, array $extra = [])
    {
        $column = $this->query->prefixColumn($column, true);
        
        return parent::decrement($column, $amount, $extra);
    }
    public function paginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null)
    {
        $page = $page ?: Paginator::resolveCurrentPage($pageName);

        $perPage = $perPage ?: $this->model->getPerPage();

        $results = ($total = $this->toBase()->getCountForPagination())
        
            ? $this->forPage($page, $perPage)->get($columns)
            
            : $this->model->newCollection();

        return $this->paginator($results, $total, $perPage, $page,
        [
            'path' => Paginator::resolveCurrentPath(),
            
            'pageName' => $pageName,
        ]);
    }
    public function simplePaginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null)
    {
        $page = $page ?: Paginator::resolveCurrentPage($pageName);

        $perPage = $perPage ?: $this->model->getPerPage();

        $this->skip(($page - 1) * $perPage)->take($perPage + 1);

        return $this->simplePaginator($this->get($columns), $perPage, $page,
        [
            'path' => Paginator::resolveCurrentPath(),
            
            'pageName' => $pageName,
        ]);
    }
}
