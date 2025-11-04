<?php

/**
 * Ratings service
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Ratings
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace Finna\Ratings;

use VuFind\RecordDriver\AbstractBase as RecordDriver;

/**
 * Ratings service
 *
 * @category VuFind
 * @package  Ratings
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class RatingsService extends \VuFind\Ratings\RatingsService
{
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
        parent::saveRating($driver, $userId, $rating);

        // Also update ratings of any duplicates:
        $mergedData = $driver->trymethod('getMergedRecordData', default: []);
        $source = $driver->getSourceIdentifier();
        foreach ($mergedData['records'] ?? [] as $record) {
            $resource = $this->resourcePopulator->getOrCreateResourceForRecordId($record['id'], $source);
            $this->dbService->addOrUpdateRating($resource, $userId, $rating);
        }
    }
}
