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
 * Represents a collection of node elements with utility methods to modify, access, and inspect its contents.
 */
interface INodeCollection
{
    /**
     * Adds a node to the collection.
     *
     * @param Node $node The node to be added.
     *
     * @return void
     */
    public function addNode(Node $node): void;

    /**
     * Retrieves all nodes from the collection.
     *
     * @return Node[] An array of nodes.
     */
    public function getNodes(): array;

    /**
     * Counts the total number of elements.
     *
     * @return int The total number of elements.
     */
    public function count(): int;

    /**
     * Retrieves the node at the specified index.
     *
     * @param int $index The index of the node to retrieve.
     * @return Node The node located at the given index.
     */
    public function getNodeAt(int $index): Node;

    /**
     * Determines whether the collection or data structure is empty.
     *
     * @return bool True if it is empty, otherwise false.
     */
    public function isEmpty(): bool;
}
