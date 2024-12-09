<?php

/**
 * Abstract base class for fallback loaders
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2022.
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
 * @package  Record
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Record\FallbackLoader;

use VuFind\Db\Service\ResourceServiceInterface;
use VuFind\Record\RecordIdUpdater;
use VuFind\RecordDriver\AbstractBase as RecordDriver;
use VuFind\RecordDriver\Feature\PreviousUniqueIdInterface;
use VuFindSearch\Service;

/**
 * Abstract base class for fallback loaders
 *
 * @category VuFind
 * @package  Record
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
abstract class AbstractFallbackLoader implements FallbackLoaderInterface
{
    /**
     * Record source
     *
     * @var string
     */
    protected $source = DEFAULT_SEARCH_BACKEND;

    /**
     * Constructor
     *
     * @param ResourceServiceInterface $resourceService Resource database service
     * @param RecordIdUpdater          $recordIdUpdater Record ID updater service
     * @param Service                  $searchService   Search service
     */
    public function __construct(
        protected ResourceServiceInterface $resourceService,
        protected RecordIdUpdater $recordIdUpdater,
        protected Service $searchService
    ) {
    }

    /**
     * Given an array of IDs that failed to load, try to find them using a
     * fallback mechanism.
     *
     * @param array $ids IDs to load
     *
     * @return array
     */
    public function load($ids)
    {
        $retVal = [];
        foreach ($ids as $id) {
            foreach ($this->fetchSingleRecord($id) as $record) {
                $this->updateRecord($record, $id);
                $retVal[] = $record;
            }
        }
        return $retVal;
    }

    /**
     * Fetch a single record (null if not found).
     *
     * @param string $id ID to load
     *
     * @return \VuFindSearch\Response\RecordCollectionInterface
     */
    abstract protected function fetchSingleRecord($id);

    /**
     * When a record ID has changed, update the record driver and database to
     * reflect the changes.
     *
     * @param RecordDriver&PreviousUniqueIdInterface $record     Record to update
     * @param string                                 $previousId Old ID of record
     *
     * @return void
     */
    protected function updateRecord($record, $previousId)
    {
        // Update the record driver with knowledge of the previous identifier...
        $record->setPreviousUniqueId($previousId);

        // Update the database to replace the obsolete identifier...
        $this->recordIdUpdater->updateRecordId($previousId, $record->getUniqueId(), $this->source);
    }
}
