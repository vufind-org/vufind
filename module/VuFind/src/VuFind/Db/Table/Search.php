<?php
/**
 * Table Definition for search
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * @category VuFind
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace VuFind\Db\Table;
use minSO;
use Zend\Db\Adapter\ParameterContainer;
use Zend\Db\TableGateway\Feature;

/**
 * Table Definition for search
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Search extends Gateway
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct('search', 'VuFind\Db\Row\Search');
    }

    /**
     * Initialize
     *
     * @return void
     */
    public function initialize()
    {
        if ($this->isInitialized) {
            return;
        }

        // Special case for PostgreSQL inserts -- we need to provide an extra
        // clue so that the database knows how to write bytea data correctly:
        if ($this->adapter->getDriver()->getDatabasePlatformName() == "Postgresql") {
            if (!is_object($this->featureSet)) {
                $this->featureSet = new Feature\FeatureSet();
            }
            $eventFeature = new Feature\EventFeature();
            $eventFeature->getEventManager()->attach(
                Feature\EventFeature::EVENT_PRE_INSERT, [$this, 'onPreInsert']
            );
            $this->featureSet->addFeature($eventFeature);
        }

        parent::initialize();
    }

    /**
     * Customize the Insert object to include extra metadata about the
     * search_object field so that it will be written correctly. This is
     * triggered only when we're interacting with PostgreSQL; MySQL works fine
     * without the extra hint.
     *
     * @param object $event Event object
     *
     * @return void
     */
    public function onPreInsert($event)
    {
        $driver = $event->getTarget()->getAdapter()->getDriver();
        $statement = $driver->createStatement();
        $params = new ParameterContainer();
        $params->offsetSetErrata('search_object', ParameterContainer::TYPE_LOB);
        $statement->setParameterContainer($params);
        $driver->registerStatementPrototype($statement);
    }

    /**
     * Delete unsaved searches for a particular session.
     *
     * @param string $sid Session ID of current user.
     *
     * @return void
     */
    public function destroySession($sid)
    {
        $this->delete(['session_id' => $sid, 'saved' => 0]);
    }

    /**
     * Get an array of rows for the specified user.
     *
     * @param string $sid Session ID of current user.
     * @param int    $uid User ID of current user (optional).
     *
     * @return array      Matching SearchEntry objects.
     */
    public function getSearches($sid, $uid = null)
    {
        $callback = function ($select) use ($sid, $uid) {
            $select->where->equalTo('session_id', $sid);
            if ($uid != null) {
                $select->where->OR->equalTo('user_id', $uid);
            }
            $select->order('id');
        };
        return $this->select($callback);
    }

    /**
     * Get a query representing expired searches (this can be passed
     * to select() or delete() for further processing).
     *
     * @param int $daysOld Age in days of an "expired" search.
     *
     * @return function
     */
    public function getExpiredQuery($daysOld = 2)
    {
        // Determine the expiration date:
        $expireDate = date('Y-m-d', time() - $daysOld * 24 * 60 * 60);
        $callback = function ($select) use ($expireDate) {
            $select->where->lessThan('created', $expireDate)
                ->equalTo('saved', 0);
        };
        return $callback;
    }

    /**
     * Get a single row matching a primary key value.
     *
     * @param int  $id                 Primary key value
     * @param bool $exceptionIfMissing Should we throw an exception if the row is
     * missing?
     *
     * @throws \Exception
     * @return \VuFind\Db\Row\Search
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
     * @return \VuFind\Db\Row\Search
     */
    public function getOwnedRowById($id, $sessId, $userId)
    {
        $callback = function ($select) use ($id, $sessId, $userId) {
            $nest = $select->where
                ->equalTo('id', $id)
                ->and
                ->nest
                ->equalTo('session_id', $sessId);
            if (!empty($userId)) {
                $nest->or->equalTo('user_id', $userId);
            }
        };
        return $this->select($callback)->current();
    }

    /**
     * Add a search into the search table (history)
     *
     * @param \VuFind\Search\Results\PluginManager $manager       Search manager
     * @param \VuFind\Search\Base\Results          $newSearch     Search to save
     * @param string                               $sessionId     Current session ID
     * @param array                                $searchHistory Existing saved
     * searches (for deduplication purposes)
     *
     * @return void
     */
    public function saveSearch(\VuFind\Search\Results\PluginManager $manager,
        $newSearch, $sessionId, $searchHistory = []
    ) {
        // Duplicate elimination
        $newUrl = $newSearch->getUrlQuery()->getParams();
        foreach ($searchHistory as $oldSearch) {
            // Deminify the old search:
            $dupSearch = $oldSearch->getSearchObject()->deminify($manager);
            // See if the classes and urls match
            $oldUrl = $dupSearch->getUrlQuery()->getParams();
            if (get_class($dupSearch) == get_class($newSearch)
                && $oldUrl == $newUrl
            ) {
                // Is the older search saved?
                if ($oldSearch->saved) {
                    // Return existing saved row instead of creating a new one:
                    $newSearch->updateSaveStatus($oldSearch);
                    return;
                } else {
                    // Delete the old search since we'll be creating a new, more
                    // current version below:
                    $oldSearch->delete();
                }
            }
        }

        // If we got this far, we didn't find a saved duplicate, so we should
        // save the new search:
        $this->insert(['created' => date('Y-m-d')]);
        $row = $this->getRowById($this->getLastInsertValue());

        // Chicken and egg... We didn't know the id before insert
        $newSearch->updateSaveStatus($row);

        // Don't set session ID until this stage, because we don't want to risk
        // ever having a row that's associated with a session but which has no
        // search object data attached to it; this could cause problems!
        $row->session_id = $sessionId;
        $row->search_object = serialize(new minSO($newSearch));
        $row->save();
    }
}