<?php

namespace Lequocnam\Orient\Eloquent;

use Illuminate\Database\Eloquent\Model as BaseModel;
use Lequocnam\Orient\Query\Builder as QueryBuilder;

class Model extends BaseModel
{
    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = '@rid';

    /**
     * The "type" of the primary key @rid.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * @rid isn't auto-increament.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * Create a new Eloquent query builder for the model.
     *
     * @param \Lequocnam\Orient\Query\Builder $query
     *
     * @return \Lequocnam\Orient\Eloquent\Builder|static
     */
    public function newEloquentBuilder($query)
    {
        return new Builder($query);
    }

    /**
     * Get a new query builder instance for the connection.
     *
     * @return \Lequocnam\Orient\Query\Builder
     */
    protected function newBaseQueryBuilder()
    {
        $conn = $this->getConnection();

        $grammar = $conn->getQueryGrammar();

        return new QueryBuilder($conn, $grammar, $conn->getPostProcessor());
    }

    /**
     * Get the @class (Class Name) associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        if (isset($this->table)) {
            return $this->table;
        }

        return str_replace('\\', '', Str::snake(Str::plural(class_basename($this))));
    }

    /**
     * Get the table qualified key name.
     *
     * @return string
     */
    public function getQualifiedKeyName()
    {
        return $this->getKeyName();
    }

    /**
     * Get the casts array.
     *
     * @return array
     */
    public function getCasts()
    {
        // if ($this->getIncrementing()) {
        //     return array_merge([
        //         $this->getKeyName() => $this->keyType,
        //     ], $this->casts);
        // }

        // return $this->casts;

        return array_merge([
            $this->getKeyName() => $this->keyType,
        ], $this->casts);
    }
}
