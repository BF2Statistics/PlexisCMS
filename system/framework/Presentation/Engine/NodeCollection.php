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
 * Represents a collection of Node objects, providing methods to manage and access the nodes.
 */
class NodeCollection implements INodeCollection
{
    /**
     * @var Node[] Array of nodes
     */
    private array $nodes = [];

    /**
     * Constructor to initialize the collection with optional nodes.
     *
     * @param Node[] $nodes
     */
    public function __construct(array $nodes = [])
    {
        $this->nodes = $nodes;
    }

    /**
     * Add a node to the collection.
     *
     * @param Node $node
     */
    public function addNode(Node $node): void
    {
        $this->nodes[] = $node;
    }

    /**
     * Get all nodes in the collection.
     *
     * @return Node[]
     */
    public function getNodes(): array
    {
        return $this->nodes;
    }

    /**
     * Retrieve the node at a specific index.
     *
     * @param int $index
     * @return Node|null Returns the node, or null if index is invalid.
     */
    public function getNode(int $index): ?Node
    {
        return $this->nodes[$index] ?? null;
    }

    /**
     * Get the total number of nodes in the collection.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->nodes);
    }

    /**
     * Check if the collection is empty.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->nodes);
    }

    public function getNodeAt(int $index): Node
    {
        return $this->nodes[$index] ?? throw new \OutOfBoundsException();
    }
}
