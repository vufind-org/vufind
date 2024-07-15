<?php

/**
 * Ratings service
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Ratings
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Ratings;

use VuFind\Db\Service\RatingsServiceInterface;
use VuFind\Record\ResourcePopulator;
use VuFind\RecordDriver\AbstractBase as RecordDriver;

/**
 * Ratings service
 *
 * @category VuFind
 * @package  Ratings
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class RatingsService
{
    /**
     * Cache for rating data
     *
     * @var array
     */
    protected $ratingCache = [];

    /**
     * Constructor
     *
     * @param RatingsServiceInterface $dbService         Ratings database service
     * @param ResourcePopulator       $resourcePopulator Resource populator
     */
    public function __construct(
        protected RatingsServiceInterface $dbService,
        protected ResourcePopulator $resourcePopulator
    ) {
    }

    /**
     * Get rating information for the provided record.
     *
     * Returns an array with the following keys:
     *
     * rating - average rating (0-100)
     * count  - count of ratings
     *
     * @param RecordDriver $driver Record to look up
     * @param ?int         $userId User ID, or null for all users
     *
     * @return array
     */
    public function getRatingData(RecordDriver $driver, ?int $userId = null)
    {
        // Cache data since comments list may ask for same information repeatedly:
        $recordId = $driver->getUniqueId();
        $source = $driver->getSourceIdentifier();
        $cacheKey = $recordId . '-' . $source . '-' . ($userId ?? '');
        if (!isset($this->ratingCache[$cacheKey])) {
            $this->ratingCache[$cacheKey] = $this->dbService->getRecordRatings($recordId, $source, $userId);
        }
        return $this->ratingCache[$cacheKey];
    }

    /**
     * Get rating breakdown for the provided record.
     *
     * Returns an array with the following keys:
     *
     * rating - average rating (0-100)
     * count  - count of ratings
     * groups - grouped counts
     *
     * @param RecordDriver $driver Record to look up
     * @param array        $groups Group definition (key => [min, max])
     *
     * @return array
     */
    public function getRatingBreakdown(RecordDriver $driver, array $groups)
    {
        return $this->dbService->getCountsForRecord(
            $driver->getUniqueId(),
            $driver->getSourceIdentifier(),
            $groups
        );
    }

    /**
     * Add or update user's rating for the record.
     *
     * @param RecordDriver $driver Record associated with rating
     * @param int          $userId ID of the user posting the rating
     * @param ?int         $rating The user-provided rating, or null to clear any existing
     * rating
     *
     * @return void
     */
    public function saveRating(RecordDriver $driver, int $userId, ?int $rating): void
    {
        // Clear rating cache:
        $this->ratingCache = [];
        $resource = $this->resourcePopulator->getOrCreateResourceForDriver($driver);
        $this->dbService->addOrUpdateRating($resource, $userId, $rating);
    }
}
