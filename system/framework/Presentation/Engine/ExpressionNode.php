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
 * Represents an expression block {{ ... }} that contains complex operators.
 * Unlike VariableNode (simple variable access), this handles full conditional expressions.
 */
class ExpressionNode extends Node implements INodeCollection
{
    /**
     * @var array Parts of the variable (e.g., identifiers, properties, indices, method calls).
     */
    private array $subNodes = [];

    /**
     * Initialize a node with a type, value, and optional filters.
     *
     */
    public function __construct(Token $token)
    {
        parent::__construct($token);
    }

    /**
     * Adds a part to the variable, such as an identifier, property, or method call.
     *
     * @param Node $node A node representing a specific part of the variable.
     */
    public function addNode(Node $node): void
    {
        $this->subNodes[] = $node;
    }

    /**
     * Retrieves all nodes.
     *
     * @return array An array of nodes.
     */
    public function getNodes(): array
    {
        return $this->subNodes;
    }

    /**
     * Determines if the collection of nodes is empty.
     *
     * @return bool True if there are no nodes, false otherwise.
     */
    public function isEmpty(): bool
    {
        return count($this->subNodes) === 0;
    }

    /**
     * Counts the number of elements in the subNodes array.
     *
     * @return int The total number of elements present in the subNodes array.
     */
    public function count(): int
    {
        return count($this->subNodes);
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
        return $this->subNodes[$index] ?? throw new \OutOfBoundsException();
    }
}