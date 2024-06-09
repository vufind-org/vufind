<?php

/**
 * Class for managing "next" and "previous" navigation within result sets.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010
 * Copyright (C) The National Library of Finland 2023
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
 * @package  Controller_Plugins
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Controller\Plugin;

use Exception;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Laminas\Session\Container as SessionContainer;
use VuFind\Db\Service\SearchServiceInterface;
use VuFind\RecordDriver\AbstractBase as BaseRecord;
use VuFind\Search\Base\Results;
use VuFind\Search\Memory as SearchMemory;
use VuFind\Search\Results\PluginManager as ResultsManager;

use function count;
use function is_array;

/**
 * Class for managing "next" and "previous" navigation within result sets.
 *
 * @category VuFind
 * @package  Controller_Plugins
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ResultScroller extends AbstractPlugin
{
    /**
     * Maximum number of last searches to track
     *
     * @var int
     */
    public const LAST_SEARCH_LIMIT = 10;

    /**
     * Is scroller enabled?
     *
     * @var bool
     */
    protected $enabled;

    /**
     * Session data used by scroller
     *
     * @var SessionContainer
     */
    protected $session;

    /**
     * Results manager
     *
     * @var ResultsManager
     */
    protected $resultsManager;

    /**
     * Search memory
     *
     * @var SearchMemory
     */
    protected $searchMemory;

    /**
     * Currently active scroll data
     *
     * @var \stdClass
     */
    protected $data = null;

    /**
     * Constructor. Create a new search result scroller.
     *
     * @param SessionContainer $session Session container
     * @param ResultsManager   $rm      Results manager
     * @param SearchMemory     $sm      Search memory
     * @param bool             $enabled Is the scroller enabled?
     */
    public function __construct(
        SessionContainer $session,
        ResultsManager $rm,
        SearchMemory $sm,
        $enabled = true
    ) {
        $this->enabled = $enabled;
        $this->session = $session;
        $this->resultsManager = $rm;
        $this->searchMemory = $sm;
    }

    /**
     * Initialize this result set scroller. This should only be called
     * prior to displaying the results of a new search.
     *
     * @param Results $searchObject The search object that was used to execute the
     * last search.
     *
     * @return bool True if enabled and initialized with results, false otherwise.
     */
    public function init($searchObject)
    {
        // Do nothing if disabled or search is empty:
        if (!$this->enabled || $searchObject->getResultTotal() <= 0) {
            return false;
        }

        // Save the details of this search in the session:
        $this->addData($searchObject);
        return (bool)$this->session->s[$searchObject->getSearchId()]->currIds;
    }

    /**
     * Add data to session for a search
     *
     * @param Results $searchObject Search object
     *
     * @return void
     */
    protected function addData(Results $searchObject): void
    {
        $data = new \stdClass();
        $data->page = $searchObject->getParams()->getPage();
        $data->limit = $searchObject->getParams()->getLimit();
        $data->sort = $searchObject->getParams()->getSort();
        $data->total = $searchObject->getResultTotal();
        $data->firstlast = $searchObject->getOptions()->recordFirstLastNavigationEnabled();

        // save the IDs of records on the current page to the session
        // so we can "slide" from one record to the next/previous records
        // spanning 2 consecutive pages
        $data->currIds = $this->fetchPage($searchObject);

        // Store last access time for eviction
        $data->lastAccessTime = time();

        if (!isset($this->session->s)) {
            $this->session->s = [];
        }

        $this->ensureRoomInSessionStorage();
        $this->session->s[$searchObject->getSearchId()] = $data;
    }

    /**
     * Make room for a new entry in the session storage as necessary
     *
     * @return void
     */
    protected function ensureRoomInSessionStorage(): void
    {
        // Evict oldest entry if storage is full:
        while (count($this->session->s) >= static::LAST_SEARCH_LIMIT) {
            $oldest = null;
            $oldestTime = null;
            foreach ($this->session->s as $id => $search) {
                if (null === $oldest || $search->lastAccessTime < $oldestTime) {
                    $oldest = $id;
                    $oldestTime = $search->lastAccessTime;
                }
            }
            unset($this->session->s[$oldest]);
        }
    }

    /**
     * Return a modified results array to help scroll the user through the current
     * page of results
     *
     * @param array $retVal Return values (in progress)
     * @param int   $pos    Current position within current page
     *
     * @return array
     */
    protected function scrollOnCurrentPage($retVal, $pos)
    {
        $retVal['previousRecord'] = $this->data->currIds[$pos - 1];
        $retVal['nextRecord'] = $this->data->currIds[$pos + 1];
        // and we're done
        return $retVal;
    }

    /**
     * Return a modified results array for the case where the user is on the cusp of
     * the previous page of results
     *
     * @param array   $retVal     Return values (in progress)
     * @param Results $lastSearch Representation of last search
     * @param int     $pos        Current position within current
     * page
     * @param int     $count      Size of current page of results
     *
     * @return array
     */
    protected function fetchPreviousPage($retVal, $lastSearch, $pos, $count)
    {
        // if the current page is NOT the first page, and
        // the previous page has not been fetched before, then
        // fetch the previous page
        if ($this->data->page > 1 && $this->data->prevIds == null) {
            $this->data->prevIds = $this->fetchPage(
                $lastSearch,
                $this->data->page - 1
            );
        }

        // if there is something on the previous page, then the previous
        // record is the last record on the previous page
        if (!empty($this->data->prevIds)) {
            $retVal['previousRecord']
                = $this->data->prevIds[count($this->data->prevIds) - 1];
        }

        // if it is not the last record on the current page, then
        // we also have a next record on the current page
        if ($pos < $count - 1) {
            $retVal['nextRecord'] = $this->data->currIds[$pos + 1];
        }

        // and we're done
        return $retVal;
    }

    /**
     * Return a modified results array for the case where the user is on the cusp of
     * the next page of results
     *
     * @param array   $retVal     Return values (in progress)
     * @param Results $lastSearch Representation of last search
     * @param int     $pos        Current position within current
     * page
     *
     * @return array
     */
    protected function fetchNextPage($retVal, $lastSearch, $pos)
    {
        // if the current page is NOT the last page, and the next page has not been
        // fetched, then fetch the next page
        if (
            $this->data->page < ceil($this->data->total / $this->data->limit)
            && $this->data->nextIds == null
        ) {
            $this->data->nextIds = $this->fetchPage(
                $lastSearch,
                $this->data->page + 1
            );
        }

        // if there is something on the next page, then the next
        // record is the first record on the next page
        if (is_array($this->data->nextIds) && count($this->data->nextIds) > 0) {
            $retVal['nextRecord'] = $this->data->nextIds[0];
        }

        // if it is not the first record on the current page, then
        // we also have a previous record on the current page
        if ($pos > 0) {
            $retVal['previousRecord'] = $this->data->currIds[$pos - 1];
        }

        // and we're done
        return $retVal;
    }

    /**
     * Return a modified results array for the case where we need to retrieve data
     * from the previous page of results
     *
     * @param array   $retVal     Return values (in progress)
     * @param Results $lastSearch Representation of last search
     * @param int     $pos        Current position within
     * previous page
     *
     * @return array
     */
    protected function scrollToPreviousPage($retVal, $lastSearch, $pos)
    {
        // decrease the page in the session because
        // we're now sliding into the previous page
        // (-- doesn't work on ArrayObjects)
        $this->data->page = $this->data->page - 1;

        // shift pages to the right
        $tmp = $this->data->currIds;
        $this->data->currIds = $this->data->prevIds;
        $this->data->nextIds = $tmp;
        $this->data->prevIds = null;

        // now we can set the previous/next record
        if ($pos > 0) {
            $retVal['previousRecord']
                = $this->data->currIds[$pos - 1];
        }
        $retVal['nextRecord'] = $this->data->nextIds[0];

        // recalculate the current position
        $retVal['currentPosition']
            = ($this->data->page - 1)
            * $this->data->limit + $pos + 1;

        // update the search URL in the session
        $lastSearch->getParams()->setPage($this->data->page);
        $this->rememberSearch($lastSearch);

        // and we're done
        return $retVal;
    }

    /**
     * Return a modified results array for the case where we need to retrieve data
     * from the next page of results
     *
     * @param array   $retVal     Return values (in progress)
     * @param Results $lastSearch Representation of last search
     * @param int     $pos        Current position within next
     * page
     *
     * @return array
     */
    protected function scrollToNextPage($retVal, $lastSearch, $pos)
    {
        // increase the page in the session because
        // we're now sliding into the next page
        // (++ doesn't work on ArrayObjects)
        $this->data->page = $this->data->page + 1;

        // shift pages to the left
        $tmp = $this->data->currIds;
        $this->data->currIds = $this->data->nextIds;
        $this->data->prevIds = $tmp;
        $this->data->nextIds = null;

        // now we can set the previous/next record
        $retVal['previousRecord']
            = $this->data->prevIds[count($this->data->prevIds) - 1];
        if ($pos < count($this->data->currIds) - 1) {
            $retVal['nextRecord'] = $this->data->currIds[$pos + 1];
        }

        // recalculate the current position
        $retVal['currentPosition']
            = ($this->data->page - 1)
            * $this->data->limit + $pos + 1;

        // update the search URL in the session
        $lastSearch->getParams()->setPage($this->data->page);
        $this->rememberSearch($lastSearch);

        // and we're done
        return $retVal;
    }

    /**
     * Return a modified results array for the case where we need to retrieve data
     * from the the first page of results
     *
     * @param array   $retVal     Return values (in progress)
     * @param Results $lastSearch Representation of last search
     *
     * @return array
     */
    protected function scrollToFirstRecord($retVal, $lastSearch)
    {
        // Set page in session to First Page
        $this->data->page = 1;
        // update the search URL in the session
        $lastSearch->getParams()->setPage($this->data->page);
        $this->rememberSearch($lastSearch);

        // update current, next and prev Ids
        $this->data->currIds = $this->fetchPage($lastSearch, $this->data->page);
        $this->data->nextIds = $this->fetchPage($lastSearch, $this->data->page + 1);
        $this->data->prevIds = null;

        // now we can set the previous/next record
        $retVal['previousRecord'] = null;
        $retVal['nextRecord'] = $this->data->currIds[1] ?? null;
        // cover extremely unlikely edge case -- page size of 1:
        if (null === $retVal['nextRecord'] && isset($this->data->nextIds[0])) {
            $retVal['nextRecord'] = $this->data->nextIds[0];
        }

        // recalculate the current position
        $retVal['currentPosition'] = 1;

        // and we're done
        return $retVal;
    }

    /**
     * Return a modified results array for the case where we need to retrieve data
     * from the the last page of results
     *
     * @param array   $retVal     Return values (in progress)
     * @param Results $lastSearch Representation of last search
     *
     * @return array
     */
    protected function scrollToLastRecord($retVal, $lastSearch)
    {
        // Set page in session to Last Page
        $this->data->page = $this->getLastPageNumber();
        // update the search URL in the session
        $lastSearch->getParams()->setPage($this->data->page);
        $this->rememberSearch($lastSearch);

        // update current, next and prev Ids
        $this->data->currIds = $this->fetchPage($lastSearch, $this->data->page);
        $this->data->prevIds = $this->fetchPage($lastSearch, $this->data->page - 1);
        $this->data->nextIds = null;

        // recalculate the current position
        $retVal['currentPosition'] = $this->data->total;

        // now we can set the previous/next record
        $retVal['nextRecord'] = null;
        if (count($this->data->currIds) > 1) {
            $pos = count($this->data->currIds) - 2;
            $retVal['previousRecord'] = $this->data->currIds[$pos];
        } elseif (count($this->data->prevIds) > 0) {
            $prevPos = count($this->data->prevIds) - 1;
            $retVal['previousRecord'] = $this->data->prevIds[$prevPos];
        }

        // and we're done
        return $retVal;
    }

    /**
     * Get the ID of the first record in the result set.
     *
     * @param Results $lastSearch Representation of last search
     *
     * @return string
     */
    protected function getFirstRecordId($lastSearch)
    {
        if (!isset($this->data->firstId)) {
            $firstPage = $this->fetchPage($lastSearch, 1);
            $this->data->firstId = $firstPage[0];
        }
        return $this->data->firstId;
    }

    /**
     * Calculate the last page number in the result set.
     *
     * @return int
     */
    protected function getLastPageNumber()
    {
        return ceil($this->data->total / $this->data->limit);
    }

    /**
     * Get the ID of the last record in the result set.
     *
     * @param Results $lastSearch Representation of last search
     *
     * @return string
     */
    protected function getLastRecordId($lastSearch)
    {
        if (!isset($this->data->lastId)) {
            $results = $this->fetchPage($lastSearch, $this->getLastPageNumber());
            $this->data->lastId = array_pop($results);
        }
        return $this->data->lastId;
    }

    /**
     * Get the previous/next record in the last search
     * result set relative to the current one, also return
     * the position of the current record in the result set.
     * Return array('previousRecord'=>previd, 'nextRecord'=>nextid,
     * 'currentPosition'=>number, 'resultTotal'=>number).
     *
     * @param BaseRecord $driver Driver for the record currently being displayed
     *
     * @return array
     */
    public function getScrollData($driver)
    {
        $retVal = [
            'firstRecord' => null, 'lastRecord' => null,
            'previousRecord' => null, 'nextRecord' => null,
            'currentPosition' => null, 'resultTotal' => null,
        ];

        $searchId = $this->searchMemory->getLastSearchId();
        // Process scroll data only if enabled and data exists:
        if (
            !$this->enabled || !$searchId || !isset($this->session->s[$searchId])
            || !($lastSearch = $this->restoreSearch($searchId))
        ) {
            return $retVal;
        }
        $this->data = $this->session->s[$searchId];
        // Get results:
        $result = $this->buildScrollDataArray($retVal, $driver, $lastSearch);
        // Touch and update session with any changes:
        $this->data->lastAccessTime = time();
        $this->session->s[$searchId] = $this->data;

        return $result;
    }

    /**
     * Build and return the scroll data array
     *
     * @param array      $retVal     Return values (in progress)
     * @param BaseRecord $driver     Driver for the record currently being displayed
     * @param Results    $lastSearch Representation of last search
     *
     * @return array
     */
    protected function buildScrollDataArray(
        array $retVal,
        BaseRecord $driver,
        Results $lastSearch
    ): array {
        // Make sure expected data elements are populated:
        if (!isset($this->data->prevIds)) {
            $this->data->prevIds = null;
        }
        if (!isset($this->data->nextIds)) {
            $this->data->nextIds = null;
        }

        // Store total result set size:
        $retVal['resultTotal'] = $this->data->total ?? 0;

        // Set first and last record IDs
        if ($this->data->firstlast) {
            $retVal['firstRecord'] = $this->getFirstRecordId($lastSearch);
            $retVal['lastRecord'] = $this->getLastRecordId($lastSearch);
        }

        // build a full ID string using the driver:
        $id = $driver->getSourceIdentifier() . '|' . $driver->getUniqueId();

        // find where this record is in the current result page
        $pos = is_array($this->data->currIds)
            ? array_search($id, $this->data->currIds)
            : false;
        if ($pos !== false) {
            // OK, found this record in the current result page
            // calculate its position relative to the result set
            $retVal['currentPosition']
                = ($this->data->page - 1) * $this->data->limit + $pos + 1;

            // count how many records in the current result page
            $count = count($this->data->currIds);
            if ($pos > 0 && $pos < $count - 1) {
                // the current record is somewhere in the middle of the current
                // page, ie: not first or last
                return $this->scrollOnCurrentPage($retVal, $pos);
            } elseif ($pos == 0) {
                // this record is first record on the current page
                return $this
                    ->fetchPreviousPage($retVal, $lastSearch, $pos, $count);
            } elseif ($pos == $count - 1) {
                // this record is last record on the current page
                return $this->fetchNextPage($retVal, $lastSearch, $pos);
            }
        } else {
            // the current record is not on the current page
            // if there is something on the previous page
            if (!empty($this->data->prevIds)) {
                // check if current record is on the previous page
                $pos = is_array($this->data->prevIds)
                    ? array_search($id, $this->data->prevIds) : false;
                if ($pos !== false) {
                    return $this
                        ->scrollToPreviousPage($retVal, $lastSearch, $pos);
                }
            }
            // if there is something on the next page
            if (!empty($this->data->nextIds)) {
                // check if current record is on the next page
                $pos = is_array($this->data->nextIds)
                    ? array_search($id, $this->data->nextIds) : false;
                if ($pos !== false) {
                    return $this->scrollToNextPage($retVal, $lastSearch, $pos);
                }
            }
            if ($this->data->firstlast) {
                if ($id == $retVal['firstRecord']) {
                    return $this->scrollToFirstRecord($retVal, $lastSearch);
                }
                if ($id == $retVal['lastRecord']) {
                    return $this->scrollToLastRecord($retVal, $lastSearch);
                }
            }
        }

        return $retVal;
    }

    /**
     * Fetch the given page of results from the given search object and
     * return the IDs of the records in an array.
     *
     * @param object $searchObject The search object to use to execute the search
     * @param int    $page         The page number to fetch (null for current)
     *
     * @return array
     */
    protected function fetchPage($searchObject, $page = null)
    {
        if (null !== $page) {
            $searchObject->getParams()->setPage($page);
            $searchObject->performAndProcessSearch();
        }

        $retVal = [];
        foreach ($searchObject->getResults() as $record) {
            if (!($record instanceof BaseRecord)) {
                return false;
            }
            $retVal[]
                = $record->getSourceIdentifier() . '|' . $record->getUniqueId();
        }
        return $retVal;
    }

    /**
     * Restore a saved search.
     *
     * @param int $searchId Search ID
     *
     * @return ?Results
     */
    protected function restoreSearch(int $searchId): ?Results
    {
        $searchService = $this->getController()->getDbService(SearchServiceInterface::class);
        $row = $searchService->getSearchByIdAndOwner(
            $searchId,
            $this->session->getManager()->getId(),
            null
        );
        if (!empty($row)) {
            $search = $row->getSearchObject()?->deminify($this->resultsManager);
            if (!$search) {
                throw new Exception("Problem getting search object from search {$row->getId()}.");
            }
            // The saved search does not remember its original limit or sort;
            // we should reapply them from the session data:
            $search->getParams()->setLimit(
                $this->session->s[$searchId]->limit ?? null
            );
            $search->getParams()->setSort(
                $this->session->s[$searchId]->sort ?? null
            );
            return $search;
        }
        return null;
    }

    /**
     * Update the remembered "last search" in the session.
     *
     * @param Results $search Search object to remember.
     *
     * @return void
     */
    protected function rememberSearch($search)
    {
        $baseUrl = $this->getController()->url()->fromRoute(
            $search->getOptions()->getSearchAction()
        );
        $this->searchMemory->rememberSearch(
            $baseUrl . $search->getUrlQuery()->getParams(false),
            $search->getSearchId()
        );
    }
}
