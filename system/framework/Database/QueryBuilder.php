<?php
/**
 * Plexis Core
 *
 * PHP Version 8.4.2 or newer is required.
 *
 * @author:       Steven Wilson
 * @copyright:    Copyright 2026, Steven Wilson, All rights reserved.
 * @license:      GNU GPL v3
 */
namespace System\Database;

use InvalidArgumentException;
use PDOException;

/**
 * Abstract class representing a query builder for SQL statements, providing methods
 * to construct and manipulate queries dynamically for database interaction.
 */
abstract class QueryBuilder
{
    /**
     * @var string
     */
    protected string $table = '';

    /**
     * @var array|string[]
     */
    protected array $fields = ['*'];

    /**
     * @var array
     */
    public array $conditions = [];

    /**
     * @var array
     */
    public array $havings = [];

    /**
     * @var array
     */
    protected array $joins = [];

    /**
     * @var array
     */
    protected array $groupings = [];

    /**
     * @var array
     */
    protected array $orderings = [];

    /**
     * @var int|null
     */
    protected ?int $limit = null;

    /**
     * @var int|null
     */
    protected ?int $offset = null;

    /**
     * @var array
     */
    public array $bindings = [];

    /**
     * @var string
     */
    protected string $command = '';

    /**
     * @var DbConnection
     */
    protected DbConnection $connection;

    /**
     * Constructor method for initializing the object with a database connection.
     *
     * @param DbConnection $connection The database connection instance to be used.
     * @return void
     */
    public function __construct(DbConnection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Sets the table to be used in the query.
     *
     * @param string $table The name of the table to be used.
     *
     * @return static Returns the current instance for method chaining.
     */
    public function from(string $table): static
    {
        $this->table = $table;
        return $this;
    }

    /**
     * Selects the specified fields for the query, supporting optional aliases.
     *
     * @param string|array ...$fields The fields to be selected. Can be string values (columns)
     *                                or an associative array where the key is the column name
     *                                and the value is the alias.
     *
     * @return static Returns the current instance for method chaining.
     */
    public function select(...$fields): static
    {
        // Reset to default if no fields are provided
        if (empty($fields))
        {
            $this->fields = ['*'];
            return $this;
        }

        // Remove the select ALL that is set by default
        if (count($this->fields) === 1 && $this->fields[0] === '*')
        {
            $this->fields = [];
        }

        // Process each field for alias support
        foreach ($fields as $field)
        {
            if (is_array($field))
            {
                foreach ($field as $column => $alias)
                {
                    $this->fields[] = "{$this->quoteColumn($column)} AS {$this->quoteColumn($alias)}";
                }
            }
            else
            {
                $this->fields[] = $this->quoteColumn($field);
            }
        }

        return $this;
    }


    /**
     * Adds a COUNT aggregate function to the query with an optional alias.
     *
     * @param string $column The name of the column to apply the COUNT function on.
     * @param mixed $alias The alias to be used for the COUNT result.
     *
     * @return static Returns the current instance for method chaining.
     */
    public function count(string $column, string $alias): static
    {
        $this->fields[] = "COUNT({$this->quoteColumn($column)}) AS {$this->quoteColumn($alias)}";
        return $this;
    }

    /**
     * Adds a SUM aggregate function to the query with a specified alias.
     *
     * @param string $column The column name to apply the SUM function on.
     * @param string $alias The alias for the resulting SUM value. Defaults to 'sum'.
     *
     * @return static Returns the current instance for method chaining.
     */
    public function sum(string $column, string $alias = 'sum'): static
    {
        $this->fields[] = "SUM({$this->quoteColumn($column)}) AS {$this->quoteColumn($alias)}";
        return $this;
    }

    /**
     * Adds an SQL average (AVG) function to the query for a specified column with an optional alias.
     *
     * @param string $column The name of the column to calculate the average for.
     * @param string $alias The alias name for the average result, default is 'average'.
     *
     * @return static Returns the current instance with the modified query.
     */
    public function average(string $column, string $alias = 'average'): static
    {
        $this->fields[] = "AVG({$this->quoteColumn($column)}) AS {$this->quoteColumn($alias)}";
        return $this;
    }

    /**
     * Adds a condition to the query with the specified column, operator, and value.
     *
     * @return ConditionGroup Returns the current instance for method chaining.
     */
    public function where($column): ConditionGroup
    {
        $group = new ConditionGroup($this);
        return $group->and($column);
    }

    /**
     * Create a grouped WHERE condition using AND logic.
     *
     * @param callable $callback A callback to define the group's conditions.
     *
     * @return static
     */
    public function whereGroup(callable $callback): static
    {
        // Add AND logic only if there are existing conditions
        if (!empty($this->conditions)) {
            $this->conditions[] = ['type' => 'AND', 'condition' => '('];
        } else {
            $this->conditions[] = ['type' => '', 'condition' => '('];
        }

        $group = new ConditionGroup($this);
        $callback($group);
        $group->apply();

        $this->conditions[] = ['type' => '', 'condition' => ')'];

        return $this;
    }

    /**
     * Create a grouped WHERE condition using OR logic.
     *
     * @param callable $callback A callback to define the group's conditions.
     *
     * @return static
     */
    public function orGroup(callable $callback): static
    {
        // Add OR logic only if there are existing conditions
        if (!empty($this->conditions)) {
            $this->conditions[] = ['type' => 'OR', 'condition' => '('];
        } else {
            $this->conditions[] = ['type' => '', 'condition' => '('];
        }

        $group = new ConditionGroup($this);
        $callback($group);  // Pass control to the user to define the group
        $group->apply();

        $this->conditions[] = [ 'type' => '', 'condition' => ')' ];    // End OR group

        return $this;
    }


    /**
     * Adds an ordering condition to the query based on the specified column and direction.
     *
     * @param string $column The name of the column to sort by.
     * @param Direction $direction The sorting direction, either ascending (ASC) or descending (DESC). Defaults to ASC.
     *
     * @return static Returns the current instance for method chaining.
     */
    public function orderBy(string $column, Direction $direction = Direction::ASC): static
    {
        $this->orderings[] = "{$this->quoteColumn($column)} {$direction->value}";
        return $this;
    }

    /**
     * Adds a descending order by clause for the specified column to the query.
     *
     * @param string $column The name of the column to sort by in descending order.
     * @return static Returns the current instance with the added ordering clause.
     */
    public function orderByDesc(string $column): static
    {
        $this->orderings[] = "{$this->quoteColumn($column)} DESC";
        return $this;
    }

    /**
     * Adds the specified columns to the grouping criteria.
     *
     * @param string ...$columns The columns to group by.
     *
     * @return static The current instance for method chaining.
     */
    public function groupBy(string ...$columns): static
    {
        $this->groupings = array_merge($this->groupings, $columns);
        return $this;
    }

    /**
     * Adds a HAVING clause to the query with the specified column, operator, and value.
     *
     * @param string $column The name of the column to be used in the HAVING clause.
     * @param Operator $operator The comparison operator for the HAVING clause.
     * @param mixed $value The value to compare against in the HAVING clause.
     *
     * @return static The current instance with the updated HAVING clause.
     */
    public function having(string $column, Operator $operator, mixed $value): static
    {
        $this->havings[] = [
            'column' => $this->quoteColumn($column),
            'operator' => $operator->value,
            'value' => '?'
        ];
        $this->bindings[] = $value;
        return $this;
    }

    /**
     * Sets the limit for the query and returns the current instance.
     *
     * @param int $limit The maximum number of records to retrieve.
     *
     * @return static The current instance with the limit applied.
     */
    public function limit(int $limit): static
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * Sets the offset value and returns the current instance.
     *
     * @param int $offset The offset value to be set.
     *
     * @return static The current instance for method chaining.
     */
    public function offset(int $offset): static
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * Adds a LEFT JOIN clause to the query.
     *
     * @param string $table The name of the table to join.
     * @param string $first The first column for the ON condition.
     * @param string $second The second column for the ON condition.
     * @param Operator $operator The operator to use in the ON condition, defaults to equals.
     *
     * @return static Returns the current instance with the added LEFT JOIN clause.
     */
    public function leftJoin(string $table, string $first, string $second, Operator $operator = Operator::Equals): static
    {
        $this->validateJoinParams($first, $second);
        $this->joins[] = "LEFT JOIN {$table} ON {$first} {$operator->value} {$second}";
        return $this;
    }

    /**
     * Adds a RIGHT JOIN clause to the query.
     *
     * @param string $table The name of the table to join.
     * @param string $first The first column for the join condition.
     * @param string $second The second column for the join condition.
     * @param Operator $operator The operator defining the relationship between the columns. Defaults to Operator::Equals.
     *
     * @return static The current instance for method chaining.
     */
    public function rightJoin(string $table, string $first, string $second, Operator $operator = Operator::Equals): static
    {
        $this->validateJoinParams($first, $second);
        $this->joins[] = "RIGHT JOIN {$table} ON {$first} {$operator->value} {$second}";
        return $this;
    }

    /**
     * Adds an INNER JOIN clause to the query.
     *
     * @param string $table The name of the table to join with.
     * @param string $first The first column in the join condition.
     * @param string $second The second column in the join condition.
     * @param Operator $operator The operator to use in the join condition. Defaults to Operator::Equals.
     *
     * @return static Returns the current instance for method chaining.
     */
    public function innerJoin(string $table, string $first, string $second, Operator $operator = Operator::Equals): static
    {
        $this->validateJoinParams($first, $second);
        $this->joins[] = "INNER JOIN {$table} ON {$first} {$operator->value} {$second}";
        return $this;
    }

    /**
     * Adds an OUTER JOIN clause to the query.
     *
     * @param string $table The name of the table to join with.
     * @param string $first The first column in the join condition.
     * @param string $second The second column in the join condition.
     * @param Operator $operator The operator to use in the join condition. Defaults to Operator::Equals.
     *
     * @return static Returns the current query instance for method chaining.
     */
    public function outerJoin(string $table, string $first, string $second, Operator $operator = Operator::Equals): static
    {
        $this->validateJoinParams($first, $second);
        $this->joins[] = "OUTER JOIN {$table} ON {$first} {$operator->value} {$second}";
        return $this;
    }

    /**
     * Appends a natural join clause for the specified table to the query.
     *
     * @param string $table The name of the table to join naturally.
     * @return static The current instance for method chaining.
     */
    public function naturalJoin(string $table): static
    {
        $this->joins[] = "NATURAL JOIN {$table}";
        return $this;
    }

    /**
     * Validates required parameters for join types that are not NATURAL.
     *
     * @param string|null $first The first column for the join
     * @param string|null $second The second column for the join
     *
     * @throws InvalidArgumentException If either parameter is null or invalid
     */
    protected function validateJoinParams(?string $first, ?string $second): void
    {
        if ($first === null || $second === null) {
            throw new InvalidArgumentException("Parameters `first` and `second` are required for this join type.");
        }
    }

    /**
     * Retrieves the bindings associated with the instance.
     *
     * @return array The list of bindings.
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * Executes the constructed query using the database connection, and returns the operation result as a SqlStatement.
     *
     * @return SqlStatement|false The result set of the query execution, or false on failure.
     *
     * @throws SqlException
     */
    public function execute() : SqlStatement|false
    {
        $this->buildQuery();
        if (empty($this->bindings))
        {
            try {
                return $this->connection->query($this->command, \PDO::FETCH_ASSOC);
            }
            catch (PDOException $e) {
                throw new SqlException($this->connection->errorInfo(), $this->command, $e);
            }
        }

        $stmt = new DbCommand($this->command, $this->connection);
        $stmt->execute($this->bindings);
		return $stmt->getStatement();
    }

    /**
     * Executes the current database command as a read operation and returns a data reader instance.
     *
     * @return DbDataReader An instance of DbDataReader to iterate over the query result set.
     *
     * @throws SqlException
     */
    public function executeReader(): DbDataReader
    {
        $this->buildQuery(); // Builds the SQL command
        if (empty($this->bindings))
        {
            try {
                $result = $this->connection->query($this->command, \PDO::FETCH_ASSOC);
                return new DbDataReader($result);
            }
            catch (PDOException $e) {
                throw new SqlException($this->connection->errorInfo(), $this->command, $e);
            }
        }

        $stmt = new DbCommand($this->command, $this->connection);
        return $stmt->executeReader($this->bindings);
    }

    /**
     * Checks whether any rows exist matching the current query conditions.
     *
     * @return bool True if at least one row matches, false otherwise.
     *
     * @throws SqlException
     */
    public function exists(): bool
    {
        $originalFields = $this->fields;
        $originalLimit = $this->limit;

        $this->fields = ['1'];
        $this->limit = 1;

        $result = $this->execute();

        // Restore original state
        $this->fields = $originalFields;
        $this->limit = $originalLimit;

        return $result !== false && $result->rowCount() > 0;
    }

    /**
     * Executes the query and returns the first row, or null if no rows match.
     *
     * @return DbRow|null The first row as a DbRow, or null.
     *
     * @throws SqlException
     */
    public function firstOrDefault(): ?DbRow
    {
        $originalLimit = $this->limit;
        $this->limit = 1;

        $result = $this->execute();
        $this->limit = $originalLimit;

        if ($result === false) {
            return null;
        }

        $row = $result->fetch(\PDO::FETCH_BOTH);
        return $row !== false ? new DbRow($row) : null;
    }

    /**
     * Executes a COUNT query and returns the result as an integer.
     *
     * @param string $column The column to count. Defaults to '*'.
     *
     * @return int The count result.
     *
     * @throws SqlException
     */
    public function countRows(string $column = '*'): int
    {
        $originalFields = $this->fields;
        $this->fields = ["COUNT({$this->quoteColumn($column)}) AS cnt"];

        $result = $this->execute();
        $this->fields = $originalFields;

        if ($result === false) {
            return 0;
        }

        $row = $result->fetch(\PDO::FETCH_ASSOC);
        return $row !== false ? (int)$row['cnt'] : 0;
    }

    /**
     * Builds the SQL query string based on the specified table, fields, and conditions.
     *
     * This method constructs a complete SQL query string including clauses such as
     * SELECT, FROM, JOIN, WHERE, GROUP BY, ORDER BY, LIMIT, and OFFSET. It requires
     * the table name to be specified through the `from` method, and may throw an exception
     * if the table name is not provided.
     *
     * @return void
     *
     * @throws InvalidArgumentException If the table name is not specified.
     */
    protected function buildQuery(): void
    {
        if (empty($this->table)) {
            throw new InvalidArgumentException('Table name must be specified using the `from` method.');
        }

        // SELECT clause
        $fields = implode(', ', $this->fields);
        $this->command = "SELECT {$fields}";

        // FROM clause
        $this->command .= " FROM {$this->table}";

        // JOIN clause
        if (!empty($this->joins)) {
            $this->command .= ' ' . implode(' ', $this->joins);
        }

        // WHERE clause
        $this->command .= $this->compileConditions();

        // GROUP BY clause
        if (!empty($this->groupings)) {
            $this->command .= ' GROUP BY ' . implode(', ', $this->groupings);
        }

        // HAVING clause
        if (!empty($this->havings))
        {
            $havingParts = array_map(
                fn($h) => "{$h['column']} {$h['operator']} {$h['value']}",
                $this->havings
            );
            $this->command .= ' HAVING ' . implode(' AND ', $havingParts);
        }

        // ORDER BY clause
        if (!empty($this->orderings)) {
            $this->command .= ' ORDER BY ' . implode(', ', $this->orderings);
        }

        // LIMIT clause
        if ($this->limit !== null) {
            $this->command .= " LIMIT {$this->limit}";
        }

        // OFFSET clause
        if ($this->offset !== null) {
            $this->command .= " OFFSET {$this->offset}";
        }
    }

    /**
     * Compile WHERE conditions into a SQL string.
     *
     * @return string
     */
    protected function compileConditions(): string
    {
        if (empty($this->conditions)) {
            return '';
        }

        // Build the WHERE clause by combining all conditions
        $sqlParts = array_map(
            fn($condition) => "{$condition['type']} {$condition['condition']}",
            $this->conditions
        );

        // Combine all parts into a single string
        $whereClause = implode(' ', $sqlParts);

        // Remove any leading "AND" or "OR" (just to be safe)
        return ' WHERE ' . preg_replace('/^(AND|OR) /', '', trim($whereClause));
    }

    /**
     * Quotes a column name to ensure it is safely used in database queries.
     *
     * @param string $column The name of the column to be quoted.
     * @return string The quoted column name.
     */
    public function quoteColumn(string $column): string
    {
        return ($column === '*') ? '*' :  $this->connection->quoteIdentifier($column);
    }
}