<?php

namespace Lequocnam\Orient\Query;

use Lequocnam\Orient\Connection;
use Lequocnam\Orient\Query\Grammars\Grammar;
use Illuminate\Database\Query\Builder as BaseBuilder;

class Builder extends BaseBuilder
{
     /**
     * Create a new query builder instance.
     *
     * @param  \Lequocnam\Orient\Connection  $connection
     * @param  \Lequocnam\Orient\Query\Grammars\Grammar  $grammar
     * @param  \Illuminate\Database\Query\Processors\Processor  $processor
     * @return void
     */
    public function __construct(Connection $connection,
                                Grammar $grammar = null,
                                Processor $processor = null)
    {
        $this->connection = $connection;
        $this->grammar = $grammar ?: $connection->getQueryGrammar();
        $this->processor = $processor ?: $connection->getPostProcessor();
    }

    /**
     * Delete a record from the database.
     *
     * @param  mixed  $id
     * @return int
     */
    public function delete($id = null)
    {
        // If an ID is passed to the method, we will set the where clause to check
        // the ID to allow developers to simply and quickly remove a single row
        // from their database without manually specifying the where clauses.
        if (! is_null($id)) {
            $this->where('@rid', '=', $id);
        }

        $sql = $this->grammar->compileDelete($this);

        return $this->connection->delete($sql, $this->getBindings());
    }

    
}
