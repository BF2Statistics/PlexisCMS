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

/**
 * Handles logical grouping and condition building for a query within a QueryBuilder.
 * This class allows defining complex conditional logic, such as AND/OR connections,
 * comparison operators, and range-based filters, before finalizing and returning
 * to the parent QueryBuilder.
 */
class ConditionGroup
{
    /**
     * @var QueryBuilder|NonQuery Reference to the parent QueryBuilder
     */
    private QueryBuilder|NonQuery $builder;

    /**
     * @var array Holds all conditions in this group
     */
    private array $conditions = [];

    /**
     * @var array Holds parameter bindings
     */
    private array $bindings = [];

    /**
     * Constructor to initialize the QueryBuilder|NonQuery instance.
     *
     * @param QueryBuilder|NonQuery $builder The query builder instance to be used.
     * @return void
     */
    public function __construct(QueryBuilder|NonQuery $builder)
    {
        $this->builder = $builder;
    }

    /**
     * Add a condition with an AND logical operator.
     */
    public function and(string $column): static
    {
        // Check if this is the first condition in the group
        $logic = empty($this->conditions) ? '' : 'AND';
        $this->conditions[] = ['logic' => $logic, 'column' => $column];
        return $this;
    }

    /**
     * Add a condition with an OR logical operator.
     */
    public function or(string $column): static
    {
        // Check if this is the first condition in the group
        $logic = empty($this->conditions) ? '' : 'OR';
        $this->conditions[] = ['logic' => $logic, 'column' => $column];
        return $this;
    }

    /**
     * Add an equals condition (=) to the last column.
     */
    public function equals(mixed $value): static
    {
        return $this->addCondition('=', $value);
    }

    /**
     * Add a not equals condition (!=) to the last column.
     */
    public function notEqual(mixed $value): static
    {
        return $this->addCondition('!=', $value);
    }

    /**
     * Add a greater-than condition (>) to the last column.
     */
    public function greaterThan(mixed $value): static
    {
        return $this->addCondition('>', $value);
    }

    /**
     * Add a less-than condition (<) to the last column.
     */
    public function lessThan(mixed $value): static
    {
        return $this->addCondition('<', $value);
    }

    /**
     * Add a greater-than or equal condition (>=) to the last column.
     */
    public function greaterThanOrEqual(mixed $value): static
    {
        return $this->addCondition('>=', $value);
    }

    /**
     * Add a less-than or equal condition (<=) to the last column.
     */
    public function lessThanOrEqual(mixed $value): static
    {
        return $this->addCondition('<=', $value);
    }

    /**
     * Add a BETWEEN condition to the last column.
     *
     * @param mixed $lower The lower bound value.
     * @param mixed $upper The upper bound value.
     * @return static
     */
    public function between(mixed $lower, mixed $upper): static
    {
        $lastKey = array_key_last($this->conditions);
        $this->conditions[$lastKey]['operator'] = 'BETWEEN';
        $this->bindings[] = $lower;
        $this->bindings[] = $upper;

        return $this;
    }

    /**
     * Add an IN condition to the last column.
     *
     * @param array $values An array of values for the IN clause.
     * @return static
     */
    public function in(array $values): static
    {
        $lastKey = array_key_last($this->conditions);
        $this->conditions[$lastKey]['operator'] = 'IN';
        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $this->conditions[$lastKey]['placeholders'] = $placeholders;

        // Convert any boolean values to integers for database compatibility
        $convertedValues = array_map(function($value) {
            return is_bool($value) ? ($value ? 1 : 0) : $value;
        }, $values);

        $this->bindings = array_merge($this->bindings, $convertedValues);

        return $this;
    }

    /**
     * Add a NOT IN condition to the last column.
     *
     * @param array $values An array of values for the NOT IN clause.
     * @return static
     */
    public function notIn(array $values): static
    {
        $lastKey = array_key_last($this->conditions);
        $this->conditions[$lastKey]['operator'] = 'NOT IN';
        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $this->conditions[$lastKey]['placeholders'] = $placeholders;

        $convertedValues = array_map(function($value) {
            return is_bool($value) ? ($value ? 1 : 0) : $value;
        }, $values);

        $this->bindings = array_merge($this->bindings, $convertedValues);

        return $this;
    }

        /**
     * Add a LIKE condition to the last column.
     *
     * @param string $pattern The LIKE pattern (e.g., '%search%').
     * @return static
     */
    public function like(string $pattern): static
    {
        return $this->addCondition('LIKE', $pattern);
    }

    /**
     * Add a NOT LIKE condition to the last column.
     *
     * @param string $pattern The NOT LIKE pattern.
     * @return static
     */
    public function notLike(string $pattern): static
    {
        return $this->addCondition('NOT LIKE', $pattern);
    }

    /**
     * Add an IS NULL condition to the last column.
     *
     * @return static
     */
    public function isNull(): static
    {
        $lastKey = array_key_last($this->conditions);
        $this->conditions[$lastKey]['operator'] = 'IS NULL';
        $this->conditions[$lastKey]['noValue'] = true;
        return $this;
    }

    /**
     * Add an IS NOT NULL condition to the last column.
     *
     * @return static
     */
    public function isNotNull(): static
    {
        $lastKey = array_key_last($this->conditions);
        $this->conditions[$lastKey]['operator'] = 'IS NOT NULL';
        $this->conditions[$lastKey]['noValue'] = true;
        return $this;
    }

    /**
     * Applies the defined conditions to the query builder.
     *
     * This method processes the stored conditions and transforms them into SQL-compatible expressions,
     * adding them as a conditional group to the parent query builder instance. It also updates the
     * query's bindings to match the parameters required by the constructed SQL.
     *
     * @return QueryBuilder|NonQuery Returns the query builder instance with the applied conditions,
     *                               or a non-query object if applicable.
     */
    public function apply(): QueryBuilder|NonQuery
    {
        if (!empty($this->conditions))
        {
            $groupSql = implode(
                ' ',
                array_map(
                    function ($item)
                    {
                        $col = $this->builder->quoteColumn($item['column']);
                        if (isset($item['noValue']) && $item['noValue']) {
                            return "{$item['logic']} {$col} {$item['operator']}";
                        }
                        if ($item['operator'] === 'BETWEEN') {
                            return "{$item['logic']} {$col} BETWEEN ? AND ?";
                        }
                        if ($item['operator'] === 'IN') {
                            return "{$item['logic']} {$col} IN ({$item['placeholders']})";
                        }
                        if ($item['operator'] === 'NOT IN') {
                            return "{$item['logic']} {$col} NOT IN ({$item['placeholders']})";
                        }
                        return "{$item['logic']} {$col} {$item['operator']} ?";
                    },
                    $this->conditions
                )
            );

            $this->builder->conditions[] = [
                'type' => '',
                'condition' => "($groupSql)"
            ];
            $this->builder->bindings = array_merge($this->builder->bindings, $this->bindings);
        }

        return $this->builder;
    }

    /**
     * Alias for apply() - applies the condition group to the parent builder.
     */
    public function end(): QueryBuilder|NonQuery
    {
        return $this->apply();
    }

    /**
     * Executes the appropriate database operation based on the query mode
     *
     * @return int|SqlStatement|false
     * @throws SqlException
     */
    public function execute(): int|SqlStatement|false
    {
        $this->apply();
        return $this->builder->execute();
    }

    /**
     * Internal: Add an operator and value to the last condition.
     */
    private function addCondition(string $operator, mixed $value): static
    {
        $lastKey = array_key_last($this->conditions);
        $this->conditions[$lastKey]['operator'] = $operator;

        // Convert boolean to integer for database compatibility
        if (is_bool($value)) {
            $value = $value ? 1 : 0;
        }

        $this->bindings[] = $value;

        return $this;
    }
}