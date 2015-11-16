<?php
/**
 * Record Cache
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Record
 * @author   Markus Beh <markus.beh@ub.uni-freiburg.de>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFind\Record;
use VuFind\Db\Table\Record as Record,
    VuFind\RecordDriver\PluginManager as RecordFactory,
    Zend\Config\Config as Config;

/**
 * Record Cache
 *
 * @category VuFind2
 * @package  Record
 * @author   Markus Beh <markus.beh@ub.uni-freiburg.de>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Cache
{
    const CONTEXT_DEFAULT = 'Default';
    const CONTEXT_FAVORITE = 'Favorite';

    /**
     * RecordCache.ini contents
     *
     * @var Config
     */
    protected $cacheConfig;

    /**
     * Database table used by cache
     *
     * @var \VuFind\Db\Table\Record
     */
    protected $recordTable;

    /**
     * Record driver plugin manager
     *
     * @var RecordFactory
     */
    protected $recordFactoryManager;

    /**
     * Record sources which may be cached.
     *
     * @var array
     */
    protected $cachableSources = [];

    /**
     * Constructor
     *
     * @param RecordFactory $recordFactoryManager Record loader
     * @param Config        $config               VuFind main config
     * @param Record        $recordTable          Record Table
     */
    public function __construct(
        RecordFactory $recordFactoryManager,
        Config $config,
        Record $recordTable
    ) {
        $this->cacheConfig = $config;
        $this->recordTable = $recordTable;
        $this->recordFactoryManager = $recordFactoryManager;

        $this->setContext(Cache::CONTEXT_DEFAULT);
    }

    /**
     * Create a new or update an existing cache entry
     *
     * @param string $recordId Record id
     * @param string $source   Source name
     * @param string $rawData  Raw data from data source
     *
     * @return void
     */
    public function createOrUpdate($recordId, $source, $rawData)
    {
        if (isset($this->cachableSources[$source])) {
            $this->recordTable->updateRecord($recordId, $source, $rawData);
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
        $record = $this->recordTable->findRecord($id, $source);
        return $record !== false ? [$this->getVuFindRecord($record)] : [];
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

        $vufindRecords = [];
        $cachedRecords = $this->recordTable->findRecords($ids, $source);
        foreach ($cachedRecords as $cachedRecord) {
            $vufindRecords[] = $this->getVuFindRecord($cachedRecord);
        }

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
        $context = ucfirst($context);
        if (!isset($this->cacheConfig->$context)) {
            $context = Cache::CONTEXT_DEFAULT;
        }
        $this->cachableSources = $this->cacheConfig->$context->toArray();

        foreach ($this->cachableSources as &$cachableSource) {
            if (!isset($cachableSource['operatingMode'])) {
                $cachableSource['operatingMode'] = 'disabled';
            }
        }

        // Due to legacy reasons add 'VuFind' to cachable sources if
        // records from source 'Solr' are cachable.
        if (isset($this->cachableSources['Solr'])) {
            $this->cachableSources['VuFind'] = $this->cachableSources['Solr'];
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
     * Check whether a record source is cachable
     *
     * @param string $source Record source
     *
     * @return boolean
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
     * @param Record $cachedRecord Record data
     *
     * @return \VuFind\RecordDriver\AbstractBase
     */
    protected function getVuFindRecord($cachedRecord)
    {
        $source = $cachedRecord['source'];
        $doc = unserialize($cachedRecord['data']);

        // Solr records are loaded in special-case fashion:
        if ($source === 'VuFind' || $source === 'Solr') {
            $driver = $this->recordFactoryManager->getSolrRecord($doc);
        } else {
            $driver = $this->recordFactoryManager->get($source);
            $driver->setRawData($doc);
        }

        $driver->setSourceIdentifier($source);

        return $driver;
    }
}
