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

/**
 * Defines a method in which a Player can be tested against a BackendAward criteria
 *
 * @package System\BF2
 */
class AwardCriteria
{
    /**
     * @var string The table to run the query
     */
    public string $table = '';

    /**
     * @var string The field (or columns) to run the query on
     */
    public string $field = '';

    /**
     * @var callable Anonymous function to check the award criteria
     */
    protected $function;

    /**
     * @var string The where statement to use when running the query
     */
    public string $where = '';

    /**
     * AwardCriteria constructor.
     *
     * @param string $table The table to run the query
     * @param string $field The field (or columns) to run the query on
     * @param string $where The where statement when running the query
     * @param callable $function The function that determines if the criteria is met
     *  based upon the results of the query.
     */
    public function __construct(string $table, string $field, string $where, callable $function)
    {
        $this->table = $table;
        $this->field = $field;
        $this->where = $where;
        $this->function = $function;
    }

    /**
     * Determines if the player has met the criteria to earn an award
     *
     * @param string[] $row The resulting row from the database
     * @param int $awardCount The amount of times the Award has been awarded to the player
     *
     * @return bool true of the criteria for this award has been met for this award
     */
    public function checkCriteria(array $row, int $awardCount)
    {
        $function = $this->function;
        return $function($row, $awardCount);
    }
}