<?php
namespace Lequocnam\Orient;

use DateTime, Closure;
use PhpOrient\PhpOrient;
use Illuminate\Database\Eloquent\Collection;
use Lequocnam\Orient\Query\Builder as QueryBuilder;
use Illuminate\Database\Connection as BaseConnection;



class Connection extends BaseConnection
{
	protected $client;

	protected $transaction;

	protected $driverName = 'orientdb';

	public function __construct(array $config = [])
	{
		$this->config = $config;

		// Create OrientDB Client
		$this->client = $this->createConnection();

		$this->useDefaultQueryGrammar();

		$this->useDefaultPostProcessor();
	}

	public function getClient()
	{
		return $this->client;
	}

	public function setClient(PhpOrient $client)
	{
		$this->client = $client;
	}

	public function getHostname()
	{
		return $this->getConfig('hostname');
	}

	public function getPort()
	{
		return $this->getConfig('port');
	}

	public function getUsername()
	{
		return $this->getConfig('username');
	}

	public function getPassword()
	{
		return $this->getConfig('password');
	}

	public function getDatabase()
	{
		return $this->getConfig('database');
	}

	public function getDriverName()
	{
		return $this->driverName;
	}

	public function getConfig($option)
	{
		return array_get($this->config, $option);
	}

	public function createConnection()
	{
		$client = new PhpOrient();

		$client->configure([
			'username' => $this->getUsername(),
			'password' => $this->getPassword(),
			'hostname' => $this->getHostname(),
			'port' => $this->getPort()
		]);

		$client->connect();

		$client->dbOpen($this->getDatabase(), $this->getUsername(), $this->getPassword());

		return $client;
	}

	/**
     * Get the default post processor instance.
     *
     * @return Query\Processor
     */
    protected function getDefaultPostProcessor()
    {
        return new Query\Processor;
    }

    /**
     * Get the default query grammar instance.
     *
     * @return \Illuminate\Database\Query\Grammars\Grammar
     */
    protected function getDefaultQueryGrammar()
    {
        return new Query\Grammars\Grammar;
    }

	/**
     * Begin a fluent query against a database table.
     *
     * @param  string  $table
     * @return \Lequocnam\Orient\Query\Builder
     */
    public function table($table)
    {
        return $this->query()->from($table);
    }

    /**
     * Get a new query builder instance.
     *
     * @return \Lequocnam\Orient\Query\Builder
     */
    public function query()
    {
        return new QueryBuilder(
            $this, $this->getQueryGrammar(), $this->getPostProcessor()
        );
    }

	/**
     * Run a select statement against the database.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @param  bool  $useReadPdo
     * @return array
     */
    public function select($query, $bindings = [], $useReadPdo = true)
    {
    	return $this->run($query, $bindings, function ($me, $query, $bindings) {
            if ($me->pretending()) {
                return [];
            }

            // For select statements, we'll simply execute the query and return an array
            // of the database result set. Each element in the array will be a single
            // row from the database table, and will either be an array or objects.
         	
         	$result = $me->client->query($query);
         	
	        $instances  = array();
	        
	        foreach ($result as $record)
	        {
	        	$data = $record->getOData();
	        	$data['@rid'] = $record->getRid();
	            $model = (object) $data;

	            $instances[] = $model;
	        }

	        return $instances;
        });
    }

    /**
     * Execute an SQL statement and return the boolean result.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @return bool
     */
    public function statement($query, $bindings = [])
    {
        return $this->run($query, $bindings, function ($me, $query, $bindings) {
            if ($me->pretending()) {
                return true;
            }

            $bindings = $me->prepareBindings($bindings);

            // return $me->getPdo()->prepare($query)->execute($bindings);
            $result = $me->client->command($query);
            if ($result && !empty($result->getOData()))
            {
            	return true;
            }

            return false;
        });
    }

    /**
     * Run an SQL statement and get the number of rows affected.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @return int
     */
    public function affectingStatement($query, $bindings = [])
    {
        return $this->run($query, $bindings, function ($me, $query, $bindings) {
            if ($me->pretending()) {
                return 0;
            }

            // For update or delete statements, we want to get the number of rows affected
            // by the statement and return that back to the developer. We'll first need
            // to execute the statement and then we'll use PDO to fetch the affected.
            
            // $statement = $me->getPdo()->prepare($query);

            // $statement->execute($me->prepareBindings($bindings));

            // return $statement->rowCount();

            $result = $me->client->command($query);
            if ($result)
            {
            	return $result->getOData()['result'];
            }

            return 0;
        });
    }
}
