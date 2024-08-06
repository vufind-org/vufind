<?php

/**
 * Logic for record versions support. Depends on versionAwareInterface.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2022.
 * Copyright (C) The National Library of Finland 2020.
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
 * @package  RecordDrivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\RecordDriver\Feature;

use VuFindSearch\Command\SearchCommand;
use VuFindSearch\Query\WorkKeysQuery;

/**
 * Logic for record versions support.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
trait VersionAwareTrait
{
    /**
     * Cached result of other versions (work expressions) count
     *
     * @var int
     */
    protected $otherVersionsCount = null;

    /**
     * Cached result of other versions (work expressions)
     *
     * @var \VuFindSearch\Response\RecordCollectionInterface
     */
    protected $otherVersions;

    /**
     * Return count of other versions available
     *
     * @return int
     */
    public function getOtherVersionCount()
    {
        if (null === $this->searchService) {
            return false;
        }

        if (!isset($this->otherVersionsCount)) {
            if (!($keys = $this->tryMethod('getWorkKeys'))) {
                if (!($this instanceof VersionAwareInterface)) {
                    throw new \Exception(
                        'VersionAwareTrait requires VersionAwareInterface'
                    );
                }
                return false;
            }

            $command = new SearchCommand(
                $this->getSourceIdentifier(),
                new WorkKeysQuery($this->getUniqueID(), false, $keys),
                0,
                0
            );
            $results = $this->searchService->invoke($command)->getResult();
            $this->otherVersionsCount = $results->getTotal();
        }
        return $this->otherVersionsCount;
    }

    /**
     * Retrieve versions as a search result
     *
     * @param bool $includeSelf Whether to include this record
     * @param int  $count       Maximum number of records to display
     * @param int  $offset      Start position (0-based)
     *
     * @return \VuFindSearch\Response\RecordCollectionInterface
     */
    public function getVersions($includeSelf = false, $count = 20, $offset = 0)
    {
        if (null === $this->searchService || !($keys = $this->getWorkKeys())) {
            return false;
        }

        if (!isset($this->otherVersions)) {
            $command = new SearchCommand(
                $this->getSourceIdentifier(),
                new WorkKeysQuery($this->getUniqueID(), false, $keys),
                $offset,
                $count
            );
            $this->otherVersions = $this->searchService->invoke($command)->getResult();
        }
        return $this->otherVersions;
    }
}
