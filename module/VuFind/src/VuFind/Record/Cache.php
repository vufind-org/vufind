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
use VuFind\Auth\Manager as AuthManager,
    VuFind\Db\Table\PluginManager as DbTableManager,
    VuFind\RecordDriver\PluginManager as RecordFactory,
    Zend\Config\Config as Config;
use VuFind\Db\Row\Record;

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
class Cache implements \Zend\Log\LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;

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
     * Authentication Manager
     *
     * @var AuthManager
     */
    protected $authManager;

    /**
     * Record sources which may be cached.
     *
     * @var array
     */
    protected $cachableSources = [];

    /**
     * Constructor
     *
     * @param RecordFactory  $recordFactoryManager Record loader
     * @param Config         $config               VuFind main config
     * @param DbTableManager $dbTableManager       Database Table Manager
     * @param AuthManager    $authManager          Authentication Manager
     */
    public function __construct(
        RecordFactory $recordFactoryManager,
        Config $config,
        DbTableManager $dbTableManager,
        AuthManager $authManager
    ) {
        $this->cacheConfig = $config;
        $this->recordTable = $dbTableManager->get('record');
        $this->recordFactoryManager = $recordFactoryManager;
        $this->authManager = $authManager;

        $this->setContext(Cache::CONTEXT_DEFAULT);
    }

    /**
     * Create a new or update an existing cache entry
     *
     * @param string $recordId   Record id
     * @param int    $userId     User id
     * @param string $source     Source name
     * @param string $rawData    Raw data from data source
     * @param int    $resourceId Resource id from resource table
     *
     * @return void
     */
    public function createOrUpdate($recordId, $userId, $source, $rawData, $resourceId
    ) {
        if (isset($this->cachableSources[$source])) {
            $cacheId = $this->getCacheId($recordId, $source, $userId);
            $this->debug(
                'createOrUpdate cache for record: ' . $recordId .
                ', userId: ' . $userId .
                ', source: ' . $source .
                ', cacheId: ' . $cacheId
            );
            $this->recordTable->updateRecord(
                $cacheId, $source, $rawData, $recordId, $userId, $resourceId
            );
        }
    }

    /**
     * Clean up orphaned cache entries for the given UserId
     *
     * @param int $userId User id
     *
     * @return void
     */
    public function cleanup($userId)
    {
        $this->recordTable->cleanup($userId);
    }

    /**
     * Fetch records using an array of associative arrays with id and source keys
     * (or pipe-separated source|id strings)
     *
     * @param array  $ids    Array of associative arrays with id/source keys or
     * strings in source|id format
     * @param string $source Source if $ids is an array of strings without source
     *
     * @return array Array of record drivers
     */
    public function lookup($ids, $source = null)
    {
        if (isset($source)) {
            foreach ($ids as &$id) {
                $id = [
                    'source' => $source,
                    'id' => $id
                ];
            }
        }

        $user = $this->authManager->isLoggedIn();
        $userId = $user === false ? null : $user->id;

        $cacheIds = [];
        foreach ($ids as $details) {
            if (!is_array($details)) {
                $parts = explode('|', $details, 2);
                $details = ['source' => $parts[0], 'id' => $parts[1]];
            }

            if (isset($this->cachableSources[$details['source']])) {
                $cacheId = $this->getCacheId(
                    $details['id'], $details['source'], $userId
                );
                $cacheIds[] = $cacheId;

                $this->debug(
                    'Lookup cache for id: ' . $details['id'] .
                    ', source: ' .  $details['source'] .
                    ', user: ' . $userId .
                    ', calculated cacheId: ' . $cacheId
                );
            }
        }

        $cachedRecords = $this->recordTable->findRecords($cacheIds);

        $this->debug(count($cachedRecords) . ' cached records found');

        $vufindRecords = [];
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

            if (isset($cachableSource['cacheIdComponents'])) {
                $cachableSource['cacheIdComponents']
                    = array_flip(
                        preg_split('/[\s,]+/', $cachableSource['cacheIdComponents'])
                    );
            } else {
                $cachableSource['cacheIdComponents'] = [];
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
     * Helper method to calculate and ensure consistent cacheIds
     *
     * @param string $recordId Record id
     * @param string $source   Source name
     * @param string $userId   User id
     *
     * @return string
     */
    protected function getCacheId($recordId, $source, $userId)
    {
        $source = $source == 'VuFind' ? 'Solr' : $source;

        $key = "$source|$recordId";

        if (isset($this->cachableSources[$source]['cacheIdComponents']['userId'])) {
            $key .= '|' . $userId;
        }

        return md5($key);
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
