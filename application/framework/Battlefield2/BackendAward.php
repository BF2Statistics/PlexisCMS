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
use Application\Battlefield2\AwardCriteria;
use PDO;

/**
 * This class represents a Backend Award with a series of criteria that
 * can be tested against a player
 *
 * @package System\BF2
 */
class BackendAward
{
    /**
     * @var int
     */
    public int $awardId = 0;

    /**
     * @var AwardCriteria[]
     */
    protected array $awardCriteria = array();

    /**
     * BackendAward constructor.
     *
     * @param int $awardId
     * @param string[] $criteria
     */
    public function __construct(int $awardId, array $criteria)
    {
        $this->awardId = $awardId;
        $this->awardCriteria = $criteria;
    }

    /**
     * Determines whether or not a player has met all of the required criteria to
     * earn this backend award.
     *
     * This method does properly allow multiple awarding of backend medals
     *
     * @param Player $player The player to run the criteria against
     * @param PDO $connection Stats database connection
     * @param int $awardCount [Reference Variable] Returns the amount of times the Award has
     *  been awarded to the player.
     *
     * @return bool true if the player has met the criteria to earn this award, or false
     */
    public function criteriaMet(Player $player, PDO $connection, int &$awardCount)
    {
        // Get the award count (or level for badges) for this award
        $query = sprintf("SELECT COUNT(player_id) FROM player_award WHERE player_id=%d AND award_id=%d", $player->id, $this->awardId);
        $awardCount = (int)$connection->query($query)->fetchColumn(0);
        $isRibbon = ($this->awardId > 3000000);

        // Can only receive ribbons once in a lifetime, so return false if we have it already
        if ($isRibbon && $awardCount > 0)
            return false;

        // Loop through each criteria and see if we have met the criteria
        foreach ($this->awardCriteria as $criteria)
        {
            // Build the where statement for backend medals
            $where = str_replace('###', $awardCount, $criteria->where);

            /** @noinspection SqlResolve */
            $query = vsprintf("SELECT %s FROM `%s` WHERE player_id=%d AND %s LIMIT 1", [
                $criteria->field,
                $criteria->table,
                $player->id,
                $where
            ]);

            $row = $connection->query($query)->fetch();
            if (empty($row) || !$criteria->checkCriteria($row, $awardCount))
                return false;
        }

        return true;
    }
}