<?php

namespace Lequocnam\Orient;

use Illuminate\Database\Connection as BaseConnection;
use Lequocnam\Orient\Query\Builder as QueryBuilder;
use PhpOrient\PhpOrient;

class Connection extends BaseConnection
{
    /**
     * @var PhpOrient, OrientDB client
     **/
    protected $client;

    /**
     * @var Boolean, whether or not the driver is currently handling an open transaction
     *               Don't support nested transactions
     */
    protected $inTransaction;

    /**
     * @var Array, Transaction commands
     **/
    protected $transactionCommands;

    /**
     * @var String, Driver name
     **/
    protected $driverName = 'orientdb';

    public function __construct(array $config = [])
    {
        $this->config = $config;

        // Create OrientDB Client
        $this->client = $this->createConnection();

        $this->useDefaultQueryGrammar();

        $this->useDefaultPostProcessor();

        // $this->transaction = null;

        $this->inTransaction = false;

        $this->transactionCommands = [];

        $this->transactions = 0;
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
            'port'     => $this->getPort(),
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
        return new Query\Processor();
    }

    /**
     * Get the default query grammar instance.
     *
     * @return \Illuminate\Database\Query\Grammars\Grammar
     */
    protected function getDefaultQueryGrammar()
    {
        return new Query\Grammars\Grammar();
    }

    /**
     * Begin a fluent query against a database table.
     *
     * @param string $table
     *
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
     * @param string $query
     * @param array  $bindings
     * @param bool   $useReadPdo
     *
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

            if ($this->inTransaction) {
                $this->addTransactionCommand($query);
            } else {
                $result = $me->client->query($query);

                $instances = [];

                foreach ($result as $record) {
                    $data = $record->getOData();
                    $data['@rid'] = $record->getRid();
                    $model = (object) $data;

                    $instances[] = $model;
                }

                return $instances;
            }

            return [];
        });
    }

    /**
     * Execute an SQL statement and return the boolean result.
     *
     * @param string $query
     * @param array  $bindings
     *
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

            if ($this->inTransaction) {
                $this->addTransactionCommand($query);
            } else {
                $result = $me->client->command($query);
                if ($result && !empty($result->getOData())) {
                    return true;
                }
            }

            return false;
        });
    }

    /**
     * Run an SQL statement and get the number of rows affected.
     *
     * @param string $query
     * @param array  $bindings
     *
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

            if ($this->inTransaction) {
                $this->addTransactionCommand($query);
            } else {
                $result = $me->client->command($query);
                if ($result) {
                    return $result->getOData()['result'];
                }
            }

            return 0;
        });
    }

    /**
     * Reconnect to the database if a PDO connection is missing.
     *
     * @return void
     */
    protected function reconnectIfMissingConnection()
    {
        if ($this->client == null) {
            $this->reconnect();
        }
    }

    /**
     * Start a new database transaction.
     *
     * @throws Exception
     *
     * @return void
     */
    public function beginTransaction()
    {
        if ($this->inTransaction) {
            throw new \Exception('A Transaction already exists. You can not nest transactions');
        }

        $this->inTransaction = true;

        $this->transactionCommands = [];

        $this->transactions = 1;

        $this->fireConnectionEvent('beganTransaction');
    }

    /**
     * Commit the active database transaction.
     *
     * @return void
     */
    public function commit()
    {
        if (!$this->inTransaction) {
            throw new \Exception('No transaction was started');
        }

        // Commit query
        $command = 'BEGIN;';
        if (count($this->transactionCommands) > 0) {
            $command .= implode(';', $this->transactionCommands).';';
        }
        $command .= 'COMMIT;';

        $this->client->sqlBatch($command);

        $this->inTransaction = false;

        $this->transactionCommands = [];

        $this->transactions = 0;

        $this->fireConnectionEvent('committed');
    }

    /**
     * Rollback the active database transaction.
     *
     * @return void
     */
    public function rollBack()
    {
        if (!$this->inTransaction) {
            throw new \Exception('No transaction was started');
        }

        // Rollback
        // Nothing to do here

        $this->inTransaction = false;

        $this->transactionCommands = [];

        $this->transactions = 0;

        $this->fireConnectionEvent('rollingBack');
    }

    /**
     * Add new transaction command.
     *
     * @param $query, String
     *
     * @return void
     */
    protected function addTransactionCommand($query)
    {
        $index = count($this->transactionCommands) + 1;

        $this->transactionCommands[] = 'LET t'.$index.' = '.$query;
    }
}
