<?php
/**
 * Plexis CMS, the Battlefield 2 private statistics frontend.
 *
 * Originally created for use by the community of BF2Statistics.com
 * before it shut down in April 2023.
 *
 * PHP Version 8.4.2 or newer is required.
 *
 * @author:       Steven Wilson
 * @copyright:    Copyright 2026, Steven Wilson, All rights reserved.
 * @license:      GNU GPL v3
 */

namespace Modules\Player\Models;

use System\Cache\CacheService;
use System\Database\DbConnection;
use System\TimeHelper;
use System\TimeSpan;

class PlayerModel
{
    /**
     * @var ?DbConnection The stats database connection
     */
    private ?DbConnection $connection = null;

    /**
     * @throws \Exception
     */
    public function getTopPlayersByScore($limit = 10, array $scores = []): array
    {
        // Load the cache service
        $cache = CacheService::Default();

        // Fetch players from cache, or regenerate cache
        return $cache->getOrRegenerateWithLock(
            'top_players_by_score',
            fn() => $this->fetchTopPlayers($limit, 'score'),
            600,
            120
        );
    }

    /**
     * Generates a cache of the top 10 players based on their scores.
     *
     * This method queries the database to fetch the top players' details, including their ID, name, rank, country, score,
     * playtime, and last online time. It formats the fetched information into an array structure,
     * which includes additional processed data such as formatted playtime and last seen information.
     *
     * @return array An array containing details of the top 10 players, with each element being an associative array
     *               including keys like 'id', 'rank_id', 'name', 'score', 'country', 'country_name', 'time',
     *               'time_played', 'last_online', and 'last_seen'.
     *
     * @throws \Exception
     */
    private function fetchTopPlayers(mixed $limit, string $byColumnName, array $columns = [])
    {
        // Load database
        $this->establishConnection();

        // return variable
        $players = [];

        // Fetch player
        $query = "SELECT id, name, rank_id, country, time, score, lastonline FROM player ORDER BY {$byColumnName} DESC LIMIT {$limit}";
        $result = $this->connection->query($query);
        while ($row = $result->fetch())
        {
            $format = ($row['time'] < 86400) ? "%y Hours, %j Mins, %w Seconds" : "%d Days, %y Hours, %j Mins";
            $players[] = [
                'id' => $row['id'],
                'rank_id' => $row['rank_id'],
                'name' => $row['name'],
                'score' => number_format($row['score']),
                'country' => strtolower($row['country']),
                'country_name' => \Locale::getDisplayRegion("-{$row['country']}", 'en'),
                'time' => $row['time'],
                'time_played' => TimeSpan::FromSeconds($row['time'])->toString($format),
                'last_online' => $row['lastonline'],
                'last_seen' => TimeHelper::FormatDifference($row['lastonline'], time())
            ];
        }

        return $players;
    }

    /**
     * Establishes a connection to the stats database.
     *
     * @return bool true if the connection was successfully established.
     *
     * @throws \Exception if the connection to the stats database fails.
     */
    private function establishConnection(): true
    {
        // Fetch database connection
        $this->connection = \Application::TryStatsDatabaseConnection();
        if ($this->connection === false)
            throw new \Exception('Unable to connect to the stats database.');

        return true;
    }
}