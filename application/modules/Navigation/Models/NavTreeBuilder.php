<?php
/**
 * Plexis CMS, the Battlefield 2 private statistics frontend.
 *
 * Originally created for use by the community of BF2Statistics.com
 * before it shut down in April 2023.
 *
 * PHP Version 8.4.2 or newer required.
 *
 * @author:       Steven Wilson
 * @copyright:    Copyright 2025, Steven Wilson, All rights reserved.
 * @license:      GNU GPL v3
 */

namespace Modules\Navigation\Models;

use JetBrains\PhpStorm\ArrayShape;
use System\Cache\CacheService;
use System\Database\DbConnection;
use System\Database\DbFactory;
use System\Database\SqlException;
use System\Http\Request;

/**
 * Class responsible for building and managing a navigation tree structure.
 *
 * This class creates a hierarchical menu tree from flat database records, handles
 * caching, formats URLs, and computes active state for dynamic navigation menus.
 */
class NavTreeBuilder
{
    /**
     * @var DbConnection|false The stats database connection
     */
    private DbConnection|false $connection;

    /**
     * HomeModel constructor.
     * @throws \Exception if the database connection fails.
     */
    public function __construct()
    {
        // Fetch database connection
        $this->connection = DbFactory::GetConnection('web');
        if ($this->connection === false)
            throw new \Exception('Unable to connect to the stats database.');
    }

    /**
     * The main entry point. Returns the fully built, hierarchical, and active-aware menu tree.
     *
     * @throws \Exception
     */
    #[ArrayShape([
        'id'           => 'int',
        'parent_id'    => 'int|null',
        'label'        => 'string',
        'title'        => 'string',
        'href'         => 'string',
        'route_names'  => 'array',
        'icon'         => 'string',
        'target'       => 'string',
        'hasSeparator' => 'bool',
        'isCurrent'    => 'bool',
        'isActive'     => 'bool',
        'children'     => 'array',
    ])]
    public function getTree(Request $request, string $tableName): array
    {
        $cache = CacheService::Default();
        $tree = $cache->getOrRegenerateWithLock(
            $tableName .'_tree',
            fn() => $this->buildTree($request, $tableName),
            3600,
            3000
        );

        // Calculate Active State. We don't cache this result for obvious reasons.
        $this->processActiveState($tree, $request);

        return $tree;
    }

    /**
     * Builds a hierarchical tree structure from a flat list of data.
     *
     * @param Request $request The request object containing context and base URL information.
     * @param string $tableName The name of the database table containing the raw data.
     *
     * @return array The hierarchical tree structure generated from the raw data.
     *
     * @throws \Exception
     */
    #[ArrayShape([
        'id'           => 'int',
        'parent_id'    => 'int|null',
        'label'        => 'string',
        'title'        => 'string',
        'href'         => 'string',
        'route_names'  => 'array',
        'icon'         => 'string',
        'target'       => 'string',
        'hasSeparator' => 'bool',
        'isCurrent'    => 'bool',
        'isActive'     => 'bool',
        'children'     => 'array',
    ])]
    protected function buildTree(Request $request, string $tableName): array
    {
        // Fetch Raw Data (Flat List)
        $rawRows = $this->fetchRawData($tableName);

        // Build Hierarchy (Adjacency List -> Nested Tree)
        return $this->buildAdjacencyList($rawRows, $request->baseUrl());
    }

    /**
     * Fetches raw data from the specified database table.
     *
     * @param string $tableName The name of the database table to query.
     *
     * @return array The fetched rows as an associative array.
     *
     * @throws SqlException
     */
    private function fetchRawData(string $tableName): array
    {
        $query = $this->connection->from($tableName)
            ->select('id', 'parent_id', 'label', 'title', 'url', 'route_names', 'icon', 'target', 'separator_below')
            ->orderBy('sort_order')
            ->where('is_visible')->equals(1);

        // Assuming $this->db is your Database wrapper available in the Base Model
        return $query->apply()->execute()->fetchAll();
    }

    /**
     * Converts flat database rows into a nested array structure.
     */
    #[ArrayShape([
        'id'           => 'int',
        'parent_id'    => 'int|null',
        'label'        => 'string',
        'title'        => 'string',
        'href'         => 'string',
        'route_names'  => 'array',
        'icon'         => 'string',
        'target'       => 'string',
        'hasSeparator' => 'bool',
        'isCurrent'    => 'bool',
        'isActive'     => 'bool',
        'children'     => 'array',
    ])]
    private function buildAdjacencyList(array $rows, string $baseUrl): array
    {
        $itemsById = [];
        $tree = [];

        // Step A: Index all items by ID and format their properties
        foreach ($rows as $row)
        {
            // Decode route_name from JSON (supports multiple route names)
            $routeNames = json_decode($row['route_names'], true);
            if (!is_array($routeNames)) {
                $routeNames = [$row['route_names']]; // Fallback for non-JSON values
            }

            $id = (int)$row['id'];
            $itemsById[$id] = [
                'id'           => $id,
                'parent_id'    => $row['parent_id'] ? (int)$row['parent_id'] : null,
                'label'        => $row['label'],
                'title'        => $row['title'],
                'href'         => $this->formatUrl($row['url'], $baseUrl),
                'route_names'  => $routeNames,
                'icon'         => $row['icon'],
                'target'       => $row['target'] ?: '_self',
                'hasSeparator' => (bool)$row['separator_below'],
                'isCurrent'    => false, // Default state
                'isActive'     => false, // Default state
                'children'     => []
            ];
        }

        // Step B: Build the Reference Tree
        foreach ($itemsById as $id => &$node)
        {
            $parentId = $node['parent_id'];
            if ($parentId === null)
            {
                // Root Item: Add directly to the main tree
                $tree[] = &$node;
            }
            else
            {
                // Child Item: Append to the parent's 'children' array
                // Check if parent exists (orphan safety)
                if (isset($itemsById[$parentId]))
                {
                    $itemsById[$parentId]['children'][] = &$node;
                }
            }
        }

        return $tree;
    }

    /**
     * Recursive Bubble-Up to determine Active/Open states.
     * Returns TRUE if the current branch contains the active page.
     */
    private function processActiveState(array &$items, Request $request): bool
    {
        $branchHasActive = false;

        // Get the current route name from the request
        $currentRouteName = $request->getRoutingDirective()?->target->route->getName() ?? false;

        foreach ($items as &$item)
        {
            $childIsActive = false;

            // Recursion: Check children first
            if (!empty($item['children']))
            {
                $childIsActive = $this->processActiveState($item['children'], $request);
            }

            // Logic: Am I the current page?
            // Check if current route name matches any of the item's route names
            $isCurrentPage = false;
            if ($currentRouteName && isset($item['route_names'])) {
                $isCurrentPage = in_array($currentRouteName, $item['route_names'], true);
            }

            // Final Decision: Active if self OR child is active
            if ($isCurrentPage || $childIsActive)
            {
                $item['isCurrent'] = true;
                $branchHasActive  = true;

                // If a child is active, I must be open (for accordions)
                if ($childIsActive) {
                    $item['isActive'] = true;
                }
            }
        }

        return $branchHasActive;
    }

    /**
     * Formats the URL based on whether it is relative or absolute.
     */
    private function formatUrl(string $url, string $baseUrl): string
    {
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        // Ensure we don't double-slash (e.g., baseUrl/ + /news)
        return rtrim($baseUrl, '/') . '/' . ltrim($url, '/');
    }
}