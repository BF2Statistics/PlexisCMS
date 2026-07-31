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
namespace System\Presentation\Engine;

/**
 * Represents a variable in a parsed template.
 * Handles complex variable structures like `user.details[10]->getName()`.
 */
class VariableNode extends Node implements INodeCollection
{
    /**
     * @var array Parts of the variable (e.g., identifiers, properties, indices, method calls).
     */
    private array $parts = [];

    /**
     * @var array
     */
    public array $filters;

    /**
     * Initialize a node with a type, value, and optional filters.
     *
     * @param array $filters An array of filters to apply (optional).
     */
    public function __construct(Token $token, array $filters = [])
    {
        parent::__construct($token);
        $this->filters = $filters;
    }

    /**
     * Adds a part to the variable, such as an identifier, property, or method call.
     *
     * @param Node $node A node representing a specific part of the variable.
     */
    public function addNode(Node $node): void
    {
        $this->parts[] = $node;
    }

    /**
     * Retrieves all parts of the variable.
     *
     * @return array An array of nodes representing the structure of the variable.
     */
    public function getNodes(): array
    {
        return $this->parts;
    }

    /**
     * Determines if the collection of nodes is empty.
     *
     * @return bool True if there are no nodes, false otherwise.
     */
    public function isEmpty(): bool
    {
        return count($this->parts) === 0;
    }

    /**
     * Counts the number of elements in the subNodes array.
     *
     * @return int The total number of elements present in the subNodes array.
     */
    public function count(): int
    {
        return count($this->parts);
    }

    /**
     * Retrieves the node at the specified index.
     *
     * @param int $index The index of the node to retrieve.
     * @return Node The node at the provided index.
     * @throws \OutOfBoundsException If the index is not valid.
     */
    public function getNodeAt(int $index): Node
    {
        return $this->parts[$index] ?? throw new \OutOfBoundsException();
    }
}
