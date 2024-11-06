<?php

/**
 * Record Cache
 *
 * PHP version 8
 *
 * Copyright (C) University of Freiburg 2014.
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
 * @author   Markus Beh <markus.beh@ub.uni-freiburg.de>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Record;

use Laminas\Config\Config as Config;
use VuFind\Db\Entity\RecordEntityInterface;
use VuFind\Db\Service\RecordServiceInterface;
use VuFind\RecordDriver\PluginManager as RecordFactory;

/**
 * Record Cache
 *
 * @category VuFind
 * @package  Record
 * @author   Markus Beh <markus.beh@ub.uni-freiburg.de>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Cache implements \Laminas\Log\LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;

    public const CONTEXT_DISABLED = '';
    public const CONTEXT_DEFAULT = 'Default';
    public const CONTEXT_FAVORITE = 'Favorite';

    /**
     * Record sources which may be cached.
     *
     * @var array
     */
    protected $cachableSources = [];

    /**
     * Constructor
     *
     * @param RecordFactory          $recordFactoryManager Record driver plugin manager
     * @param Config                 $cacheConfig          RecordCache.ini contents
     * @param RecordServiceInterface $recordService        Record database service
     */
    public function __construct(
        protected RecordFactory $recordFactoryManager,
        protected Config $cacheConfig,
        protected RecordServiceInterface $recordService
    ) {
        $this->setContext(Cache::CONTEXT_DEFAULT);
    }

    /**
     * Create a new or update an existing cache entry
     *
     * @param string $recordId Record id
     * @param string $source   Source name
     * @param mixed  $rawData  Raw data from source (must be serializable)
     *
     * @return void
     */
    public function createOrUpdate($recordId, $source, $rawData)
    {
        if (isset($this->cachableSources[$source])) {
            $this->debug("Updating {$source}|{$recordId}");
            $this->recordService->updateRecord($recordId, $source, $rawData);
        }
    }

    /**
     * Given a record ID, look up a record for that source.
     *
     * @param string $id     Record ID
     * @param string $source Record source
     *
     * @return array Array of \VuFind\RecordDriver\AbstractBase
     */
    public function lookup($id, $source)
    {
        $this->debug("Checking {$source}|{$id}");
        $record = $this->recordService->getRecord($id, $source);
        $this->debug(
            "Cached record {$source}|{$id} "
            . ($record ? 'found' : 'not found')
        );
        try {
            return $record ? [$this->getVuFindRecord($record)] : [];
        } catch (\Exception $e) {
            $this->logError(
                'Could not load record {$source}|{$id} from the record cache: '
                . $e->getMessage()
            );
        }
        return [];
    }

    /**
     * Given an array of IDs and a record source, look up a batch of records for
     * that source.
     *
     * @param array  $ids    Record IDs
     * @param string $source Record source
     *
     * @return array Array of \VuFind\RecordDriver\AbstractBase
     */
    public function lookupBatch($ids, $source)
    {
        if (empty($ids)) {
            return [];
        }

        $this->debug("Checking $source batch: " . implode(', ', $ids));
        $vufindRecords = [];
        $cachedRecords = $this->recordService->getRecords($ids, $source);
        foreach ($cachedRecords as $cachedRecord) {
            try {
                $vufindRecords[] = $this->getVuFindRecord($cachedRecord);
            } catch (\Exception $e) {
                $this->logError(
                    'Could not load record ' . $cachedRecord->getSource() . '|'
                    . $cachedRecord->getRecordId() . ' from the record cache: '
                    . $e->getMessage()
                );
            }
        }

        $extractIdCallback = function ($record) {
            return $record->getUniqueID();
        };
        $foundIds = array_map($extractIdCallback, $vufindRecords);
        $this->debug(
            "Cached records for $source "
            . ($foundIds ? 'found: ' . implode(', ', $foundIds) : 'not found')
        );

        return $vufindRecords;
    }

    /**
     * Set the context for controlling cache behaviour
     *
     * @param string $context Cache context
     *
     * @return void
     */
    public function setContext($context)
    {
        $this->debug("Setting context to '$context'");
        if (empty($context)) {
            $this->cachableSources = [];
            return;
        }
        $context = ucfirst($context);
        if (!isset($this->cacheConfig->$context)) {
            $context = Cache::CONTEXT_DEFAULT;
        }
        $this->cachableSources = isset($this->cacheConfig->$context)
            ? $this->cacheConfig->$context->toArray() : [];
        if (
            $context != Cache::CONTEXT_DEFAULT
            && isset($this->cacheConfig->{Cache::CONTEXT_DEFAULT})
        ) {
            // Inherit settings from Default section
            $this->cachableSources = array_merge(
                $this->cacheConfig->{Cache::CONTEXT_DEFAULT}->toArray(),
                $this->cachableSources
            );
        }

        foreach ($this->cachableSources as &$cachableSource) {
            if (!isset($cachableSource['operatingMode'])) {
                $cachableSource['operatingMode'] = 'disabled';
            }
        }
    }

    /**
     * Convenience method for checking if cache is used as primary data data source
     *
     * @param string $source Record source
     *
     * @return bool
     */
    public function isPrimary($source)
    {
        return isset($this->cachableSources[$source]['operatingMode'])
            ? $this->cachableSources[$source]['operatingMode'] === 'primary'
            : false;
    }

    /**
     * Convenience method for checking if cache is used as fallback data source
     *
     * @param string $source Record source
     *
     * @return bool
     */
    public function isFallback($source)
    {
        return isset($this->cachableSources[$source]['operatingMode'])
            ? $this->cachableSources[$source]['operatingMode'] === 'fallback'
            : false;
    }

    /**
     * Check whether a record source is cacheable
     *
     * @param string $source Record source
     *
     * @return bool
     */
    public function isCachable($source)
    {
        return isset($this->cachableSources[$source]['operatingMode'])
            ? $this->cachableSources[$source]['operatingMode'] !== 'disabled'
            : false;
    }

    /**
     * Helper function to get records from cached source-specific record data
     *
     * @param RecordEntityInterface $cachedRecord Record data
     *
     * @return \VuFind\RecordDriver\AbstractBase
     */
    protected function getVuFindRecord(RecordEntityInterface $cachedRecord)
    {
        $source = $cachedRecord->getSource();
        $doc = unserialize($cachedRecord->getData());

        // Solr records are loaded in special-case fashion:
        if ($source === 'VuFind' || $source === 'Solr') {
            $driver = $this->recordFactoryManager->getSolrRecord($doc);
        } else {
            $driver = $this->recordFactoryManager->get($source);
            $driver->setRawData($doc);
        }

        $driver->setSourceIdentifiers($source);

        return $driver;
    }
}
