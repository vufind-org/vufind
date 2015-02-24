<?php

/**
 * Record Cache.
 *
 * PHP version 5
 *
 * Copyright (C) 2014 University of Freiburg.
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFind\Record;
use VuFind\RecordDriver\PluginManager as RecordFactory,
    VuFind\Db\Table\PluginManager as DbTableManager,
    Zend\Config\Config as Config;

/**
 * Record cache
 *
 * @category VuFind2
 * @package  Record
 * @author   Markus Beh <markus.beh@ub.uni-freiburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Cache implements \Zend\Log\LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;
    
    const FAVORITE             = 'favorite';

    protected $cachePolicy = 0;
    protected $DISABLED          = 0b00000; //  0
    protected $PRIMARY           = 0b00001; //  1
    protected $FALLBACK          = 0b00010; //  2
    protected $INCLUDE_RECORD_ID = 0b00100; //  4
    protected $INCLUDE_SOURCE    = 0b01000; //  8
    protected $INCLUDE_USER_ID   = 0b10000; // 16

    protected $recordTable = null;
    protected $recordFactoryManager = null;

    protected $cachableSources = null;
    protected $cachePolicies = [];
    
    /**
     * Constructor
     *
     * @param RecordFactory  $recordFactoryManager Record loader
     * @param Config         $config               VuFind main config
     * @param DbTableManager $dbTableManager       Database Table Manager
     */
    public function __construct(
        RecordFactory $recordFactoryManager,
        Config $config,
        DbTableManager $dbTableManager
    ) {
        if (isset($config->RecordCache)) {
            if (isset($config->RecordCache->cachableSources)) {
                $this->cachableSources
                    = preg_split("/[\s,]+/", $config->RecordCache->cachableSources);
            } else {
                $this->cachableSources = [];
            }

            if (isset($config->RecordCache->cachePolicy)) {
                $this->cachePolicies = $config->RecordCache->cachePolicy;
            } else {
                $this->cachePolicies = [];
            }
        }
        
        // due to legacy resasons add 'VuFind' to cachable sources if 
        // record from source 'Solr' are cacheable.
        if ( in_array('Solr', $this->cachableSources) ) {
            $this->cachableSources[] = 'VuFind';
        }
        
        $this->recordTable = $dbTableManager->get('record');
        $this->recordFactoryManager = $recordFactoryManager;
    }

    /**
     * Create a new or update an existing cache entry
     *
     * @param string $recordId   RecordId
     * @param int    $userId     UserId
     * @param string $source     Source name
     * @param string $rawData    Raw Data from data source
     * @param string $sessionId  PHP Session Id
     * @param int    $resourceId ResourceId from resource table
     *
     * @return null
     */
    public function createOrUpdate($recordId, $userId, $source,
        $rawData, $sessionId, $resourceId
    ) {
        if (in_array($source, $this->cachableSources)) {
            $cId = $this->getCacheId($recordId, $source, $userId);
            $this->debug(
                'createOrUpdate cache for record: ' . $recordId .
                ' , userid: ' . $userId .
                ' , source: ' . $source .
                ' , cId: ' . $cId
            );
            $this->recordTable->updateRecord(
                $cId, $source, $rawData, $recordId, $userId, $sessionId, $resourceId
            );
        }
    }

    /**
     * Cleanup orphaned cache entries for the given UserId
     *
     * @param int $userId UserId
     *
     * @return null
     */
    public function cleanup($userId)
    {
        $this->recordTable->cleanup($userId);
    }

    /**
     * Given an array of associative arrays with id and source keys (or pipe-
     * separated source|id strings)
     *
     * @param array  $ids    Array of associative arrays with id/source keys or
     * strings in source|id format.  In associative array formats, there is
     * also an optional "extra_fields" key which can be used to pass in data
     * @param string $source source
     *
     * @return array     Array of record drivers
     */
    public function lookup($ids, $source = null)
    {
        if ($this->cachePolicy === $this->DISABLED) {
            return [];
        }

        if (isset($source)) {
            foreach ($ids as $id) {
                $tmp[] = "$source|$id";
            }
            $ids = $tmp;
        }

        $cacheIds = [];
        foreach ($ids as $i => $details) {
            if (!is_array($details)) {
                $parts = explode('|', $details, 2);
                $details = ['source' => $parts[0],'id' => $parts[1]];
            }

            if (in_array($details['source'], $this->cachableSources)) {
                $userId = isset($_SESSION['Account'])
                    ? $_SESSION['Account']->userId : null;
    
                $cacheId = $this->getCacheId(
                    $details['id'], $details['source'], $userId
                );
                $cacheIds[] = $cacheId;

                $this->debug(
                    "lookup cache for id: " . $details['id'] .
                    ", source: " .  $details['source'] .
                    ", userId: " . $userId .
                    ", calculated cId: " .  $cacheId
                );
            }
        }
        
        $cachedRecords = $this->recordTable->findRecord($cacheIds);

        $this->debug('records found: ' . count($cachedRecords));
        
        $vufindRecords = [];
        foreach ($cachedRecords as $cachedRecord) {
            $vufindRecords[] = $this->getVuFindRecord($cachedRecord);
        }

        return $vufindRecords;
    }

    /**
     * Set policy for controling cache behaviour
     *
     * @param string $cachePolicy Cache policy
     *
     * @return null
     */
    public function setPolicy($cachePolicy)
    {
        if (isset($this->cachePolicies[$cachePolicy])) {
            $this->cachePolicy = $this->cachePolicies[$cachePolicy];
        }
    }

    /**
     * Convenience method for checking if cache is used as primary data data source
     *
     * @return bool
     */
    public function isPrimary()
    {
        return $this->hasPolicy($this->PRIMARY);
    }

    /**
     * Convenience method for checking if cache is used as fallback data source
     *
     * @return bool
     */
    public function isFallback()
    {
        return $this->hasPolicy($this->FALLBACK);
    }

    /**
     * Convenience method checking policies
     *
     * @param int $policy cache policy
     *
     * @return bool
     */
    protected function hasPolicy($policy)
    {
        return (($this->cachePolicy & $policy) === $policy);
    }

    /**
     * Helper method to calcualte and ensure consistend cacheIds
     *
     * @param string $recordId RecordId
     * @param string $source   Source name
     * @param int    $userId   UserId userId
     *
     * @return string
     */
    protected function getCacheId($recordId, $source = null, $userId = null)
    {
        $cIdHelper = [];
        if ($this->hasPolicy($this->INCLUDE_RECORD_ID)) {
            $cIdHelper['recordId'] = $recordId;
        }

        if ($this->hasPolicy($this->INCLUDE_SOURCE)) {
            $source = ($source == 'Solr') ? 'VuFind' : $source;
            $cIdHelper['source']   = $source;
        }

        if ($this->hasPolicy($this->INCLUDE_USER_ID)) {
            $cIdHelper['userId']   = $userId;
        }
        $md5 = md5(json_encode($cIdHelper));

        return $md5;
    }
    /**
     * Helper function to get vufind records form cached index specific record data
     *
     * @param string $cachedRecord json encoded representation of index specific
     *                             record data
     *
     * @return \VuFind\RecordDriver
     */
    protected function getVuFindRecord($cachedRecord)
    {
        $source = $cachedRecord['source'];
        $doc = json_decode($cachedRecord['data'], true);
    
        if ($source === 'VuFind' || $source === 'Solr') {
            if (isset($doc['recordtype'])) {
                $key = 'Solr' . ucwords($doc['recordtype']);
                $recordType = $this->recordFactoryManager->has($key)
                ? $key
                : 'SolrDefault';
            } else {
                $recordType = 'SolrDefault';
            }
        } else {
            $recordType = $source;
        }
    
        $driver = $this->recordFactoryManager->get($recordType);
        $driver->setRawData($doc);
        $driver->setSourceIdentifier($source);
         
        return $driver;
    }
}