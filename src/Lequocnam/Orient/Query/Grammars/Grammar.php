<?php

namespace Lequocnam\Orient\Query\Grammars;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\Grammar as BaseGrammar;

class Grammar extends BaseGrammar
{
    /**
     * Wrap a single string in keyword identifiers.
     *
     * @param  string  $value
     * @return string
     */
    protected function wrapValue($value)
    {
        if ($value === '*') {
            return $value;
        }

        // return '"'.str_replace('"', '""', $value).'"';
        return str_replace('"', '""', $value);

    }

    /** Get the appropriate query parameter place-holder for a value.
     *
     * @param  mixed   $value
     * @return string
     */
    public function parameter($value)
    {
    	// return $this->isExpression($value) ? $this->getValue($value) : '?';

        if (is_bool($value))
        {
            // For the boolean column this need to be un-quote unless orientdb will reject
            return $value ? 'true' : 'false';
        }
        else if (is_string($value) && is_array(json_decode($value, true)) && (json_last_error() == JSON_ERROR_NONE))
        {
        	return $value;
        }
        elseif ($this->isExpression($value))
        {
            return $this->getValue($value);
        }
        else
        {
        	$value = str_replace(array('\\', "\0", "\n", "\r", "'", '"', "\x1a"), array('\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'), $value);
	        
	        if(preg_match('~#(-)?[0-9]+:[0-9]+~', $value))
	        {//is (graph) id, don't wrap it or error would be thrown
	            return $value;
	        }
			return "'" . $value . "'";
        }
    }

    /**
     * Compile a delete statement into SQL.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return string
     */
    public function compileDelete(Builder $query)
    {
        $table = $this->wrapTable($query->from);

        $where = is_array($query->wheres) ? $this->compileWheres($query) : '';

        return trim("delete vertex $table ".$where);
    }
}
