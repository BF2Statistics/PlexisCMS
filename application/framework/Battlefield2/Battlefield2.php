<?php
/**
 * Plexis CMS, the Battlefield 2 private statistics frontend.
 *
 * Originally created for use by the community of BF2Statistics.com
 * before it shut down in April 2023.
 *
 * PHP Version 8.2 or newer required.
 *
 * @author:       Steven Wilson
 * @copyright:    Copyright 2025, Steven Wilson, All rights reserved.
 * @license:      GNU GPL v3
 */
namespace Application\Battlefield2;

use System\Database\DbFactory;

/**
 * This class provides common methods for fetching Battlefield 2 related information
 *
 * @package System
 */
class Battlefield2
{
    /**
     * Defines the number of armies (Vanilla is 14)
     */
    const int NUM_ARMIES = 14;

    /**
     * Defines the number of kits (Vanilla is 7)
     */
    const int NUM_KITS = 7;

    /**
     * Defines the number of vehicle types to output (Vanilla is 7)
     */
    const int NUM_VEHICLES = 7;

    /**
     * Defines the number of weapon types (Vanilla is 15)
     *
     * For NUM_WEAPONS, don't forget that NUM 9 is skipped in the constants.py!
     * Do not include the following weapon types in the count:
     *   - WEAPON_TYPE_TARGETING
     *   - WEAPON_TYPE_GRAPPLINGHOOK
     *   - WEAPON_TYPE_ZIPLINE
     *   - WEAPON_TYPE_TACTICAL
     */
    const int NUM_WEAPONS = 15;

    /**
     * Defines the Weapon ID's of explosives in the DATABASE,
     * not the constants.py
     *
     * WEAPON_TYPE_C4, WEAPON_TYPE_CLAYMORE, WEAPON_TYPE_ATMINE
     *
     * Weapon Map in the database
     *
     * WEAPON_TYPE_ASSAULT         = 0
     * WEAPON_TYPE_ASSAULTGRN      = 1
     * WEAPON_TYPE_CARBINE         = 2
     * WEAPON_TYPE_LMG             = 3
     * WEAPON_TYPE_SNIPER          = 4
     * WEAPON_TYPE_PISTOL          = 5
     * WEAPON_TYPE_ATAA            = 6
     * WEAPON_TYPE_SMG             = 7
     * WEAPON_TYPE_SHOTGUN         = 8
     * WEAPON_TYPE_KNIFE           = 9
     * WEAPON_TYPE_SHOCKPAD        = 10
     * WEAPON_TYPE_C4              = 11
     * WEAPON_TYPE_HANDGRENADE     = 12
     * WEAPON_TYPE_CLAYMORE        = 13
     * WEAPON_TYPE_ATMINE          = 14
     * WEAPON_TYPE_GRAPPLINGHOOK   = 15
     * WEAPON_TYPE_ZIPLINE         = 16
     * WEAPON_TYPE_TACTICAL        = 17
     */
    const array EXPLOSIVE_IDS = [11, 13, 14];

    /**
     * @var array
     */
    protected static array $Ranks = [];

    /**
     * Converts a badge level to its string name
     *
     * @param int $level The badge level
     *
     * @return string
     */
    public static function GetBadgePrefix(int $level): string
    {
        if ($level == 3) return 'Gold';
        else if ($level == 2) return 'Silver';
        else return 'Bronze';
    }

    /**
     * Gets the name of a game mode by its id
     *
     * @param int $gamemode
     *
     * @return string
     */
    public static function GetGameModeString(int $gamemode): string
    {
        $pdo = DbFactory::GetConnection('stats');
        $result = $pdo->query("SELECT `name` FROM `game_mode` WHERE id=". (int)$gamemode);
        $name = $result->fetchColumn(0);
        return ($name === false) ? 'Unknown' : $name;
    }

    /**
     * Fetches the name of a rank by ID
     *
     * @param int $rank
     *
     * @return string
     */
    public static function GetRankName(int $rank): string
    {
        $pdo = DbFactory::GetConnection('stats');
        $result = $pdo->query("SELECT `name` FROM `rank` WHERE id=". (int)$rank);
        $name = $result->fetchColumn(0);
        return ($name === false) ? 'Unknown' : $name;
    }

    /**
     * Fetches the name of an award by ID
     *
     * @param int $awardId
     * @param int $level
     *
     * @return string
     */
    public static function GetAwardName(int $awardId, int $level = 0): string
    {
        $pdo = DbFactory::GetConnection('stats');
        $result = $pdo->query("SELECT `name`, `type` FROM `award` WHERE id=". (int)$awardId);
        $award = $result->fetch();
        if (empty($award))
            return "Unknown Award";

        // If award is a badge, add the level
        if ($award['type'] == 1)
        {
            $prefix = self::GetBadgePrefix($level);
            return $prefix . ' ' . $award['name'];
        }
        else
        {
            // Store award name
            return $award['name'];
        }
    }
}