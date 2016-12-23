<?php
/**
 * Record loader
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2015.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace VuFind\Record;
use VuFind\Exception\RecordMissing as RecordMissingException,
    VuFind\RecordDriver\PluginManager as RecordFactory,
    VuFindSearch\Service as SearchService,
    VuFind\Record\Cache;

/**
 * Record loader
 *
 * @category VuFind
 * @package  Record
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Loader
{
    /**
     * Record factory
     *
     * @var RecordFactory
     */
    protected $recordFactory;

    /**
     * Search service
     *
     * @var SearchService
     */
    protected $searchService;

    /**
     * Record cache
     *
     * @var Cache
     */
    protected $recordCache;

    /**
     * Constructor
     *
     * @param SearchService $searchService Search service
     * @param RecordFactory $recordFactory Record loader
     * @param Cache         $recordCache   Record Cache
     */
    public function __construct(SearchService $searchService,
        RecordFactory $recordFactory, Cache $recordCache = null
    ) {
        $this->searchService = $searchService;
        $this->recordFactory = $recordFactory;
        $this->recordCache = $recordCache;
    }

    /**
     * Given an ID and record source, load the requested record object.
     *
     * @param string $id              Record ID
     * @param string $source          Record source
     * @param bool   $tolerateMissing Should we load a "Missing" placeholder
     * instead of throwing an exception if the record cannot be found?
     *
     * @throws \Exception
     * @return \VuFind\RecordDriver\AbstractBase
     */
    public function load($id, $source = DEFAULT_SEARCH_BACKEND,
        $tolerateMissing = false
    ) {
        if (null !== $id && '' !== $id) {
            $results = [];
            if (null !== $this->recordCache
                && $this->recordCache->isPrimary($source)
            ) {
                $results = $this->recordCache->lookup($id, $source);
            }
            if (empty($results)) {
                $results = $this->searchService->retrieve($source, $id)
                    ->getRecords();
            }
            if (empty($results) && null !== $this->recordCache
                && $this->recordCache->isFallback($source)
            ) {
                $results = $this->recordCache->lookup($id, $source);
            }

            if (!empty($results)) {
                return $results[0];
            }
        }
        if ($tolerateMissing) {
            $record = $this->recordFactory->get('Missing');
            $record->setRawData(['id' => $id]);
            $record->setSourceIdentifier($source);
            return $record;
        }
        throw new RecordMissingException(
            'Record ' . $source . ':' . $id . ' does not exist.'
        );
    }

    /**
     * Given an array of IDs and a record source, load a batch of records for
     * that source.
     *
     * @param array  $ids    Record IDs
     * @param string $source Record source
     *
     * @throws \Exception
     * @return array
     */
    public function loadBatchForSource($ids, $source = DEFAULT_SEARCH_BACKEND)
    {
        $cachedRecords = [];
        if (null !== $this->recordCache && $this->recordCache->isPrimary($source)) {
            // Try to load records from cache if source is cachable
            $cachedRecords = $this->recordCache->lookupBatch($ids, $source);
            // Check which records could not be loaded from the record cache
            foreach ($cachedRecords as $cachedRecord) {
                $key = array_search($cachedRecord->getUniqueId(), $ids);
                if ($key !== false) {
                    unset($ids[$key]);
                }
            }
        }

        // Try to load the uncached records from the original $source
        $genuineRecords = [];
        if (!empty($ids)) {
            $genuineRecords = $this->searchService->retrieveBatch($source, $ids)
                ->getRecords();

            foreach ($genuineRecords as $genuineRecord) {
                $key = array_search($genuineRecord->getUniqueId(), $ids);
                if ($key !== false) {
                    unset($ids[$key]);
                }
            }
        }

        if (!empty($ids) && null !== $this->recordCache
            && $this->recordCache->isFallback($source)
        ) {
            // Try to load missing records from cache if source is cachable
            $cachedRecords = $this->recordCache->lookupBatch($ids, $source);
        }

        // Merge records found in cache and records loaded from original $source
        $retVal = $genuineRecords;
        foreach ($cachedRecords as $cachedRecord) {
            $retVal[] = $cachedRecord;
        }

        return $retVal;
    }

    /**
     * Given an array of associative arrays with id and source keys (or pipe-
     * separated source|id strings), load all of the requested records in the
     * requested order.
     *
     * @param array $ids Array of associative arrays with id/source keys or
     * strings in source|id format.  In associative array formats, there is
     * also an optional "extra_fields" key which can be used to pass in data
     * formatted as if it belongs to the Solr schema; this is used to create
     * a mock driver object if the real data source is unavailable.
     *
     * @throws \Exception
     * @return array     Array of record drivers
     */
    public function loadBatch($ids)
    {
        // Sort the IDs by source -- we'll create an associative array indexed by
        // source and record ID which points to the desired position of the indexed
        // record in the final return array:
        $idBySource = [];
        foreach ($ids as $i => $details) {
            // Convert source|id string to array if necessary:
            if (!is_array($details)) {
                $parts = explode('|', $details, 2);
                $ids[$i] = $details = [
                    'source' => $parts[0], 'id' => $parts[1]
                ];
            }
            $idBySource[$details['source']][$details['id']] = $i;
        }

        // Retrieve the records and put them back in order:
        $retVal = [];
        foreach ($idBySource as $source => $details) {
            $records = $this->loadBatchForSource(array_keys($details), $source);
            foreach ($records as $current) {
                $id = $current->getUniqueId();
                // In theory, we should be able to assume that $details[$id] is
                // set... but in practice, we can't make that assumption. In some
                // cases, Summon IDs will change, and requests for an old ID value
                // will return a record with a different ID.
                if (isset($details[$id])) {
                    $retVal[$details[$id]] = $current;
                }
            }
        }

        // Check for missing records and fill gaps with \VuFind\RecordDriver\Missing
        // objects:
        foreach ($ids as $i => $details) {
            if (!isset($retVal[$i]) || !is_object($retVal[$i])) {
                $fields = isset($details['extra_fields'])
                    ? $details['extra_fields'] : [];
                $fields['id'] = $details['id'];
                $retVal[$i] = $this->recordFactory->get('Missing');
                $retVal[$i]->setRawData($fields);
                $retVal[$i]->setSourceIdentifier($details['source']);
            }
        }

        // Send back the final array, with the keys in proper order:
        ksort($retVal);
        return $retVal;
    }

    /**
     * Set the context to control cache behavior
     *
     * @param string $context Cache context
     *
     * @return void
     */
    public function setCacheContext($context)
    {
        if (null !== $this->recordCache) {
            $this->recordCache->setContext($context);
        }
    }
}
