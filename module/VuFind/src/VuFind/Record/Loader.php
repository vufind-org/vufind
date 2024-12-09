<?php

/**
 * Record loader
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010, 2022.
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

use VuFind\Exception\RecordMissing as RecordMissingException;
use VuFind\Record\FallbackLoader\PluginManager as FallbackLoader;
use VuFind\RecordDriver\PluginManager as RecordFactory;
use VuFindSearch\Backend\Exception\BackendException;
use VuFindSearch\Command\RetrieveBatchCommand;
use VuFindSearch\Command\RetrieveCommand;
use VuFindSearch\ParamBag;
use VuFindSearch\Service as SearchService;

use function count;
use function is_object;

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
class Loader implements \Laminas\Log\LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;

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
     * Fallback record loader
     *
     * @var FallbackLoader
     */
    protected $fallbackLoader;

    /**
     * Constructor
     *
     * @param SearchService  $searchService  Search service
     * @param RecordFactory  $recordFactory  Record loader
     * @param Cache          $recordCache    Record Cache
     * @param FallbackLoader $fallbackLoader Fallback record loader
     */
    public function __construct(
        SearchService $searchService,
        RecordFactory $recordFactory,
        Cache $recordCache = null,
        FallbackLoader $fallbackLoader = null
    ) {
        $this->searchService = $searchService;
        $this->recordFactory = $recordFactory;
        $this->recordCache = $recordCache;
        $this->fallbackLoader = $fallbackLoader;
    }

    /**
     * Given an ID and record source, load the requested record object.
     *
     * @param string   $id              Record ID
     * @param string   $source          Record source
     * @param bool     $tolerateMissing Should we load a "Missing" placeholder
     * instead of throwing an exception if the record cannot be found?
     * @param ParamBag $params          Search backend parameters
     *
     * @throws \Exception
     * @return \VuFind\RecordDriver\AbstractBase
     */
    public function load(
        $id,
        $source = DEFAULT_SEARCH_BACKEND,
        $tolerateMissing = false,
        ParamBag $params = null
    ) {
        if (null !== $id && '' !== $id) {
            $results = [];
            if (
                null !== $this->recordCache
                && $this->recordCache->isPrimary($source)
            ) {
                $results = $this->recordCache->lookup($id, $source);
            }
            if (empty($results)) {
                try {
                    $command = new RetrieveCommand($source, $id, $params);
                    $results = $this->searchService->invoke($command)
                        ->getResult()->getRecords();
                } catch (BackendException $e) {
                    if (!$tolerateMissing) {
                        throw $e;
                    }
                }
            }
            if (
                empty($results) && null !== $this->recordCache
                && $this->recordCache->isFallback($source)
            ) {
                $results = $this->recordCache->lookup($id, $source);
                if (!empty($results)) {
                    $results[0]->setExtraDetail('cached_record', true);
                }
            }

            if (!empty($results)) {
                return $results[0];
            }

            if (
                $this->fallbackLoader
                && $this->fallbackLoader->has($source)
            ) {
                try {
                    $fallbackRecords = $this->fallbackLoader->get($source)
                        ->load([$id]);
                } catch (BackendException $e) {
                    if (!$tolerateMissing) {
                        throw $e;
                    }
                    $fallbackRecords = [];
                }

                if (count($fallbackRecords) == 1) {
                    return $fallbackRecords[0];
                }
            }
        }
        if ($tolerateMissing) {
            $record = $this->recordFactory->get('Missing');
            $record->setRawData(['id' => $id]);
            $record->setSourceIdentifiers($source);
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
     * @param array    $ids                       Record IDs
     * @param string   $source                    Record source
     * @param bool     $tolerateBackendExceptions Whether to tolerate backend
     * exceptions that may be caused by e.g. connection issues or changes in
     * subscriptions
     * @param ParamBag $params                    Search backend parameters
     *
     * @throws \Exception
     * @return array
     */
    public function loadBatchForSource(
        $ids,
        $source = DEFAULT_SEARCH_BACKEND,
        $tolerateBackendExceptions = false,
        ParamBag $params = null
    ) {
        $list = new Checklist($ids);
        $cachedRecords = [];
        if (null !== $this->recordCache && $this->recordCache->isPrimary($source)) {
            // Try to load records from cache if source is cacheable
            $cachedRecords = $this->recordCache->lookupBatch($ids, $source);
            // Check which records could not be loaded from the record cache
            foreach ($cachedRecords as $cachedRecord) {
                $list->check($cachedRecord->getUniqueId());
            }
        }

        // Try to load the uncached records from the original $source
        $genuineRecords = [];
        if ($list->hasUnchecked()) {
            try {
                $command = new RetrieveBatchCommand(
                    $source,
                    $list->getUnchecked(),
                    $params
                );
                $genuineRecords = $this->searchService
                    ->invoke($command)->getResult()->getRecords();
            } catch (BackendException $e) {
                if (!$tolerateBackendExceptions) {
                    throw $e;
                }
                $this->logWarning(
                    "Exception when trying to retrieve records from $source: "
                    . $e->getMessage()
                );
            }

            foreach ($genuineRecords as $genuineRecord) {
                $list->check($genuineRecord->getUniqueId());
            }
        }

        $retVal = $genuineRecords;
        if (
            $list->hasUnchecked() && $this->fallbackLoader
            && $this->fallbackLoader->has($source)
        ) {
            try {
                $fallbackRecords = $this->fallbackLoader->get($source)
                    ->load($list->getUnchecked());
            } catch (BackendException $e) {
                if (!$tolerateBackendExceptions) {
                    throw $e;
                }
                $fallbackRecords = [];
                $this->logWarning(
                    'Exception when trying to retrieve fallback records from '
                    . $source . ': ' . $e->getMessage()
                );
            }
            foreach ($fallbackRecords as $record) {
                $retVal[] = $record;
                if (!$list->check($record->getUniqueId())) {
                    $list->check($record->tryMethod('getPreviousUniqueId'));
                }
            }
        }

        if (
            $list->hasUnchecked() && null !== $this->recordCache
            && $this->recordCache->isFallback($source)
        ) {
            // Try to load missing records from cache if source is cacheable
            $cachedRecords = $this->recordCache
                ->lookupBatch($list->getUnchecked(), $source);
        }

        // Merge records found in cache and records loaded from original $source
        foreach ($cachedRecords as $cachedRecord) {
            $retVal[] = $cachedRecord;
        }

        return $retVal;
    }

    /**
     * Build a "missing record" driver.
     *
     * @param array $details Associative array of record details (from a
     * SourceAndIdList)
     *
     * @return \VuFind\RecordDriver\Missing
     */
    protected function buildMissingRecord($details)
    {
        $fields = $details['extra_fields'] ?? [];
        $fields['id'] = $details['id'];
        $record = $this->recordFactory->get('Missing');
        $record->setRawData($fields);
        $record->setSourceIdentifiers($details['source']);
        return $record;
    }

    /**
     * Given an array of associative arrays with id and source keys (or pipe-
     * separated source|id strings), load all of the requested records in the
     * requested order.
     *
     * @param array      $ids                       Array of associative arrays with
     * id/source keys or strings in source|id format. In associative array formats,
     * there is also an optional "extra_fields" key which can be used to pass in data
     * formatted as if it belongs to the Solr schema; this is used to create
     * a mock driver object if the real data source is unavailable.
     * @param bool       $tolerateBackendExceptions Whether to tolerate backend
     * exceptions that may be caused by e.g. connection issues or changes in
     * subscriptions
     * @param ParamBag[] $params                    Associative array of search
     * backend parameters keyed with source key
     *
     * @throws \Exception
     * @return array     Array of record drivers
     */
    public function loadBatch(
        $ids,
        $tolerateBackendExceptions = false,
        $params = []
    ) {
        // Create a SourceAndIdList object to help sort the IDs by source:
        $list = new SourceAndIdList($ids);

        // Retrieve the records and put them back in order:
        $retVal = [];
        foreach ($list->getIdsBySource() as $source => $currentIds) {
            $sourceParams = $params[$source] ?? null;
            $records = $this->loadBatchForSource(
                $currentIds,
                $source,
                $tolerateBackendExceptions,
                $sourceParams
            );
            foreach ($records as $current) {
                foreach ($list->getRecordPositions($current) as $i => $position) {
                    // If we have multiple positions, create a clone of the driver
                    // for positions after 0, to avoid shared-reference problems:
                    $retVal[$position] = $i == 0 ? $current : clone $current;
                }
            }
        }

        // Check for missing records and fill gaps with \VuFind\RecordDriver\Missing
        // objects:
        foreach ($list->getAll() as $i => $details) {
            if (!isset($retVal[$i]) || !is_object($retVal[$i])) {
                $retVal[$i] = $this->buildMissingRecord($details);
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
