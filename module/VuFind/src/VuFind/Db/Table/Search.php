<?php

/**
 * Table Definition for search
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2016-2017.
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
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Db\Table;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Adapter\ParameterContainer;
use Laminas\Db\TableGateway\Feature;
use VuFind\Db\Row\RowGateway;
use VuFind\Db\Service\DbServiceAwareInterface;
use VuFind\Db\Service\DbServiceAwareTrait;
use VuFind\Db\Service\SearchServiceInterface;
use VuFind\Search\NormalizedSearch;
use VuFind\Search\SearchNormalizer;

use function count;
use function is_object;

/**
 * Table Definition for search
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Search extends Gateway implements DbServiceAwareInterface
{
    use DbServiceAwareTrait;
    use ExpirationTrait;

    /**
     * Constructor
     *
     * @param Adapter       $adapter Database adapter
     * @param PluginManager $tm      Table manager
     * @param array         $cfg     Laminas configuration
     * @param RowGateway    $rowObj  Row prototype object (null for default)
     * @param string        $table   Name of database table to interface with
     */
    public function __construct(
        Adapter $adapter,
        PluginManager $tm,
        $cfg,
        ?RowGateway $rowObj = null,
        $table = 'search'
    ) {
        parent::__construct($adapter, $tm, $cfg, $rowObj, $table);
    }

    /**
     * Initialize features
     *
     * @param array $cfg Laminas configuration
     *
     * @return void
     */
    public function initializeFeatures($cfg)
    {
        // Special case for PostgreSQL inserts -- we need to provide an extra
        // clue so that the database knows how to write bytea data correctly:
        if ($this->adapter->getDriver()->getDatabasePlatformName() == 'Postgresql') {
            if (!is_object($this->featureSet)) {
                $this->featureSet = new Feature\FeatureSet();
            }
            $eventFeature = new Feature\EventFeature();
            $eventFeature->getEventManager()->attach(
                Feature\EventFeature::EVENT_PRE_INITIALIZE,
                [$this, 'onPreInit']
            );
            $this->featureSet->addFeature($eventFeature);
        }

        parent::initializeFeatures($cfg);
    }

    /**
     * Customize the database object to include extra metadata about the
     * search_object field so that it will be written correctly. This is
     * triggered only when we're interacting with PostgreSQL; MySQL works fine
     * without the extra hint.
     *
     * @param object $event Event object
     *
     * @return void
     */
    public function onPreInit($event)
    {
        $driver = $event->getTarget()->getAdapter()->getDriver();
        $statement = $driver->createStatement();
        $params = new ParameterContainer();
        $params->offsetSetErrata('search_object', ParameterContainer::TYPE_LOB);
        $statement->setParameterContainer($params);
        $driver->registerStatementPrototype($statement);
    }

    /**
     * Destroy unsaved searches belonging to the specified session/user.
     *
     * @param string $sid Session ID of current user.
     * @param int    $uid User ID of current user (optional).
     *
     * @return void
     *
     * @deprecated Use SearchServiceInterface::destroySession()
     */
    public function destroySession($sid, $uid = null)
    {
        $this->getDbService(SearchServiceInterface::class)->destroySession($sid, $uid);
    }

    /**
     * Get an array of rows for the specified user.
     *
     * @param string $sid Session ID of current user.
     * @param int    $uid User ID of current user (optional).
     *
     * @return array      Matching SearchEntry objects.
     *
     * @deprecated Use SearchServiceInterface::getSearches()
     */
    public function getSearches($sid, $uid = null)
    {
        return $this->getDbService(SearchServiceInterface::class)->getSearches($sid, $uid);
    }

    /**
     * Get a single row matching a primary key value.
     *
     * @param int  $id                 Primary key value
     * @param bool $exceptionIfMissing Should we throw an exception if the row is
     * missing?
     *
     * @throws \Exception
     * @return ?\VuFind\Db\Row\Search
     *
     * @deprecated
     */
    public function getRowById($id, $exceptionIfMissing = true)
    {
        $row = $this->select(['id' => $id])->current();
        if (empty($row) && $exceptionIfMissing) {
            throw new \Exception('Cannot find id ' . $id);
        }
        return $row;
    }

    /**
     * Get a single row, enforcing user ownership. Returns row if found, null
     * otherwise.
     *
     * @param int    $id     Primary key value
     * @param string $sessId Current user session ID
     * @param int    $userId Current logged-in user ID (or null if none)
     *
     * @return ?\VuFind\Db\Row\Search
     *
     * @deprecated Use SearchServiceInterface::getSearchByIdAndOwner()
     */
    public function getOwnedRowById($id, $sessId, $userId)
    {
        return $this->getDbService(SearchServiceInterface::class)->getSearchByIdAndOwner($id, $sessId, $userId);
    }

    /**
     * Get scheduled searches.
     *
     * @return array Array of VuFind\Db\Row\Search objects.
     *
     * @deprecated Use SearchServiceInterface::getScheduledSearches()
     */
    public function getScheduledSearches()
    {
        return $this->getDbService(SearchServiceInterface::class)->getScheduledSearches();
    }

    /**
     * Return existing search table rows matching the provided normalized search.
     *
     * @param NormalizedSearch $normalized Normalized search to match against
     * @param string           $sessionId  Current session ID
     * @param int|null         $userId     Current user ID
     * @param int              $limit      Max rows to retrieve
     * (default = no limit)
     *
     * @return \VuFind\Db\Row\Search[]
     *
     * @deprecated Use SearchNormalizer::getSearchesMatchingNormalizedSearch()
     */
    public function getSearchRowsMatchingNormalizedSearch(
        NormalizedSearch $normalized,
        string $sessionId,
        ?int $userId,
        int $limit = PHP_INT_MAX
    ) {
        // Fetch all rows with the same CRC32 and try to match with the URL
        $checksum = $normalized->getChecksum();
        $callback = function ($select) use ($checksum, $sessionId, $userId) {
            $nest = $select->where
                ->equalTo('checksum', $checksum)
                ->and
                ->nest
                ->equalTo('session_id', $sessionId)->and->equalTo('saved', 0);
            if (!empty($userId)) {
                $nest->or->equalTo('user_id', $userId);
            }
        };
        $results = [];
        foreach ($this->select($callback) as $match) {
            $minified = $match->getSearchObjectOrThrowException();
            if ($normalized->isEquivalentToMinifiedSearch($minified)) {
                $results[] = $match;
                if (count($results) >= $limit) {
                    break;
                }
            }
        }
        return $results;
    }

    /**
     * Add a search into the search table (history)
     *
     * @param SearchNormalizer            $normalizer Search manager
     * @param \VuFind\Search\Base\Results $results    Search to save
     * @param string                      $sessionId  Current session ID
     * @param int|null                    $userId     Current user ID
     *
     * @return \VuFind\Db\Row\Search
     *
     * @deprecated Use SearchNormalizer::saveNormalizedSearch()
     */
    public function saveSearch(
        SearchNormalizer $normalizer,
        $results,
        $sessionId,
        $userId
    ) {
        return $normalizer->saveNormalizedSearch($results, $sessionId, $userId);
    }

    /**
     * Update the select statement to find records to delete.
     *
     * @param Select $select    Select clause
     * @param string $dateLimit Date threshold of an "expired" record in format
     * 'Y-m-d H:i:s'.
     *
     * @return void
     */
    protected function expirationCallback($select, $dateLimit)
    {
        $select->where->lessThan('created', $dateLimit)->equalTo('saved', 0);
    }
}
