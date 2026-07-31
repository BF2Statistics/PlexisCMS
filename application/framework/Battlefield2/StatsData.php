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
 * Class StatsData
 * @package System
 */
class StatsData
{
    /**
     * @var int The number of army types in the database
     */
    public static int $NumArmies = 0;

    /**
     * @var int The number of kit types in the database
     */
    public static int $NumKits = 0;

    /**
     * @var int The number of vehicle types in the database
     */
    public static int $NumVehicles = 0;

    /**
     * @var int The number of weapon types in the database
     */
    public static int $NumWeapons = 0;

    /**
     * @var int The number of gamemodes in the database
     */
    public static int $NumGamemodes = 0;

    /**
     * @var int The number of ranks in the database
     */
    public static int $NumRanks = 0;

    /**
     * @var string[] An array of ArmyId => Army String Name
     */
    public static array $ArmyNames = [];

    /**
     * @var string[] An array of KitId => Kit String Name
     */
    public static array $KitNames = [];

    /**
     * @var string[] An array of VehicleId => Vehicle String Name
     */
    public static array $VehicleNames = [];

    /**
     * @var string[] An array of WeaponId => Weapon String Name
     */
    public static array $WeaponNames = [];

    /**
     * @var string[] An array of GamemodeId => Game Mode String Name
     */
    public static array $GameModes = [];

    /**
     * @var string[] An array of RankId => Rank String Name
     */
    public static array $RankNames = [];

    /**
     * Loads the stats data from the database if it has not been previously
     * called
     */
    public static function Load(): void
    {
        // Only load data if it has not been loaded yet
        if (self::$NumArmies == 0)
        {
            $pdo = DbFactory::GetConnection('stats');

            // Load armies
            $result = $pdo->query("SELECT name FROM army ORDER BY id ASC");
            while ($row = $result->fetch())
            {
                self::$ArmyNames[] = $row['name'];
                self::$NumArmies++;
            }

            // Load kits
            $result = $pdo->query("SELECT name FROM kit ORDER BY id ASC");
            while ($row = $result->fetch())
            {
                self::$KitNames[] = $row['name'];
                self::$NumKits++;
            }

            // Load vehicles
            $result = $pdo->query("SELECT name FROM vehicle ORDER BY id ASC");
            while ($row = $result->fetch())
            {
                self::$VehicleNames[] = $row['name'];
                self::$NumVehicles++;
            }

            // Load weapons
            $result = $pdo->query("SELECT name FROM weapon ORDER BY id ASC");
            while ($row = $result->fetch())
            {
                self::$WeaponNames[] = $row['name'];
                self::$NumWeapons++;
            }

            // Load Game Modes
            $result = $pdo->query("SELECT name FROM game_mode ORDER BY id ASC");
            while ($row = $result->fetch())
            {
                self::$GameModes[] = $row['name'];
                self::$NumGamemodes++;
            }

            // Load Ranks
            $result = $pdo->query("SELECT name FROM `rank` ORDER BY id ASC");
            while ($row = $result->fetch())
            {
                self::$RankNames[] = $row['name'];
                self::$NumRanks++;
            }
        }
    }

    /**
     * Fetches the army name by Id, or false if it does not exist
     *
     * @param int $id The item id
     *
     * @return bool|string The name of the army, or false if it does not exist
     */
    public static function GetArmyNameById(int $id): false|string
    {
        // Only load data if it has not been loaded yet
        if (self::$NumArmies == 0) self::Load();

        // Get max index, to prevent an index out of bounds error
        $maxIndex = self::$NumArmies - 1;
        return ($id > $maxIndex) ? false : self::$ArmyNames[$id];
    }

    /**
     * Fetches the kit name by Id, or false if it does not exist
     *
     * @param int $id The item id
     *
     * @return bool|string The name of the kit, or false if it does not exist
     */
    public static function GetKitNameById(int $id): false|string
    {
        // Only load data if it has not been loaded yet
        if (self::$NumArmies == 0) self::Load();

        // Get max index, to prevent an index out of bounds error
        $maxIndex = self::$NumKits - 1;
        return ($id > $maxIndex) ? false : self::$KitNames[$id];
    }

    /**
     * Fetches the weapon name by Id, or false if it does not exist
     *
     * @param int $id The item id
     *
     * @return bool|string The name of the weapon, or false if it does not exist
     */
    public static function GetWeaponNameById(int $id): false|string
    {
        // Only load data if it has not been loaded yet
        if (self::$NumArmies == 0) self::Load();

        // Get max index, to prevent an index out of bounds error
        $maxIndex = self::$NumWeapons - 1;
        return ($id > $maxIndex) ? false : self::$WeaponNames[$id];
    }

    /**
     * Fetches the vehicle name by Id, or false if it does not exist
     *
     * @param int $id The item id
     *
     * @return bool|string The name of the vehicle, or false if it does not exist
     */
    public static function GetVehicleNameById(int $id): false|string
    {
        // Only load data if it has not been loaded yet
        if (self::$NumVehicles == 0) self::Load();

        // Get max index, to prevent an index out of bounds error
        $maxIndex = self::$NumVehicles - 1;
        return ($id > $maxIndex) ? false : self::$VehicleNames[$id];
    }
}