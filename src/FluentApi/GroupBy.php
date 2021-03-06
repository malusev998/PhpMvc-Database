<?php

namespace BrosSquad\MicroORM\FluentApi;


use BrosSquad\MicroORM\Model;
use stdClass;

class GroupBy extends Fluent
{
    protected $sql;

    public function __construct(
        Model& $model,
        string $select,
        string $join,
        string $where,
        string $limit,
        string $orderBy,
        string $groupBy,
        string $class = stdClass::class,
        array& $bindings = [],
        int $current = 0
    )
    {
        parent::__construct($model, $class);
        $this->select = $select;
        $this->join = $join;
        $this->where = $where;
        $this->limit = $limit;
        $this->orderBy = $orderBy;
        $this->groupBy = $groupBy;
        $this->bindings = $bindings;
        $this->current = $current;
    }

    public function having(string $column, string $operator, $value)
    {
        $having = ' HAVING ' . $this->whereGenerator($column, $operator, $value) . ' ';
        return new Having(
            $this->model,
            $having,
            $this->select,
            $this->join,
            $this->where,
            $this->limit,
            $this->orderBy,
            $this->groupBy,
            $this->bind,
            $this->bindings,
            $this->current
        );
    }

}
