<?php
/**
 * Table Definition for search
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015-2016.
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
 * @package  Db_Table
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace Finna\Db\Table;
use fminSO;

/**
 * Table Definition for search
 *
 * @category VuFind2
 * @package  Db_Table
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Search extends \VuFind\Db\Table\Search
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->rowClass = 'Finna\Db\Row\Search';
    }

    /**
     * Get distinct view URLs with scheduled alerts.
     *
     * @return array URLs
     */
    public function getScheduleBaseUrls()
    {
        $sql
            = "SELECT distinct finna_schedule_base_url as url FROM {$this->table}"
            . " WHERE finna_schedule_base_url != '';";

        $result = $this->getAdapter()->query(
            $sql,
            \Zend\Db\Adapter\Adapter::QUERY_MODE_EXECUTE
        );
        $urls = [];
        foreach ($result as $res) {
            $urls[] = $res['url'];
        }
        return $urls;
    }

    /**
     * Get scheduled searches.
     *
     * @param string $baseUrl View URL
     *
     * @return array Array of Finna\Db\Row\Search objects.
     */
    public function getScheduledSearches($baseUrl)
    {
        $callback = function ($select) use ($baseUrl) {
            $select->columns(['*']);
            $select->where->equalTo('saved', 1);
            $select->where('finna_schedule > 0');
            $select->where->equalTo('finna_schedule_base_url', $baseUrl);
            $select->order('user_id');
        };

        return $this->select($callback);
    }

    /**
     * Get saved searches.
     *
     * @param int $uid User ID
     *
     * @return \Zend\Db\ResultSet\ResultSet
     */
    public function getSavedSearches($uid)
    {
        $callback = function ($select) use ($uid) {
            $select->where->equalTo('user_id', $uid);
            $select->order('id');
        };
        return $this->select($callback);
    }

    /**
     * Return filters for a saved search.
     *
     * @param string        $searchHash Search hash
     *
     * @return mixed \Finna\Db\Row\Search or false if the row doesn't exist
     */
    public function getRowByHash($searchHash)
    {
        $search = $this->select(['finna_search_id' => $searchHash])->current();
        if (empty($search)) {
            return false;
        }
        return $search;
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
        // Resolve search hash of current search
        $hash = null;

        // Duplicate elimination
        foreach ($searchHistory as $oldSearch) {
            // Deminify the old search (note that if we have a resource, we need
            // to grab the contents -- this is necessary for PostgreSQL compatibility
            // although MySQL returns a plain string).
            $minSO = $oldSearch->getSearchObject();
            $dupSearch = $minSO->deminify($manager);
            // See if the classes and urls match
            $oldUrl = $dupSearch->getUrlQuery()->getParams();
            $newUrl = $newSearch->getUrlQuery()->getParams();
            if (get_class($dupSearch) == get_class($newSearch)
                && $oldUrl == $newUrl
            ) {
                $hash = $oldSearch->finna_search_id;
                break;
            }
        }

        parent::saveSearch($manager, $newSearch, $sessionId, $searchHistory);

        // Augment row updated by parent with search hash
        $row = $this->select(['id' => $newSearch->getSearchId()])->current();
        if (empty($row)) {
            return false;
        }
        $row['finna_search_object'] = serialize(new fminSO($newSearch));
        if (!$hash) {
            $hash = md5(md5($row->search_object) . md5($row->finna_search_object));
        }
        $row->finna_search_id = $hash;
        $row->save();

        $newSearch->setSearchHash($row->finna_search_id);
    }
}
