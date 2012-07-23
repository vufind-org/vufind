<?php
/**
 * Class for managing "next" and "previous" navigation within result sets.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010
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
 * @package  Support_Classes
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/system_classes Wiki
 */
namespace VuFind\Controller\Plugin;
use VuFind\Config\Reader as ConfigReader, VuFind\Db\Table\Search as SearchTable,
    VuFind\Search\Memory, Zend\Mvc\Controller\Plugin\AbstractPlugin,
    Zend\Session\Container as SessionContainer;

/**
 * Class for managing "next" and "previous" navigation within result sets.
 *
 * @category VuFind2
 * @package  Support_Classes
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/system_classes Wiki
 */
class ResultScroller extends AbstractPlugin
{
    protected $enabled;
    protected $data;

    /**
     * Constructor. Create a new search result scroller.
     */
    public function __construct()
    {
        // Is this functionality enabled in config.ini?
        $config = ConfigReader::getConfig();
        $this->enabled = (isset($config->Record->next_prev_navigation)
            && $config->Record->next_prev_navigation);

        // Set up session namespace for the class.
        $this->data = new SessionContainer('ResultScroller');
    }

    /**
     * Initialize this result set scroller. This should only be called
     * prior to displaying the results of a new search.
     *
     * @param VF_Search_Base_Results $searchObject The search object that was
     * used to execute the last search.
     *
     * @return bool
     */
    public function init($searchObject)
    {
        // Do nothing if disabled:
        if (!$this->enabled) {
            return;
        }

        // Save the details of this search in the session
        $this->data->searchId = $searchObject->getSearchId();
        $this->data->page = $searchObject->getPage();
        $this->data->limit = $searchObject->getLimit();
        $this->data->total = $searchObject->getResultTotal();

        // save the IDs of records on the current page to the session
        // so we can "slide" from one record to the next/previous records
        // spanning 2 consecutive pages
        $this->data->currIds = $this->fetchPage($searchObject);

        // clear the previous/next page
        unset($this->data->prevIds);
        unset($this->data->nextIds);

        return true;
    }

    /**
     * Get the previous/next record in the last search
     * result set relative to the current one, also return
     * the position of the current record in the result set.
     * Return array('previousRecord'=>previd, 'nextRecord'=>nextid,
     * 'currentPosition'=>number, 'resultTotal'=>number).
     *
     * @param string $id The ID currently being displayed
     *
     * @return array
     */
    public function getScrollData($id)
    {
        $retVal = array(
            'previousRecord'=>null,
            'nextRecord'=>null,
            'currentPosition'=>null,
            'resultTotal'=>null);

        // Do nothing if disabled:
        if (!$this->enabled) {
            return $retVal;
        }

        if (isset($this->data->currIds) && isset($this->data->searchId)) {
            // we need to restore the last search object
            // to fetch either the previous/next page of results
            $lastSearch = $this->restoreLastSearch();

            // give up if we can not restore the last search
            if (!$lastSearch) {
                return $retVal;
            }

            if (!isset($this->data->prevIds)) {
                $this->data->prevIds = null;
            }
            if (!isset($this->data->nextIds)) {
                $this->data->nextIds = null;
            }
            $retVal['resultTotal']
                = isset($this->data->total) ? $this->data->total : 0;

            // find where this record is in the current result page
            $pos = is_array($this->data->currIds)
                ? array_search($id, $this->data->currIds)
                : false;
            if ($pos !== false) {
                // OK, found this record in the current result page
                // calculate it's position relative to the result set
                $retVal['currentPosition']
                    = ($this->data->page - 1) * $this->data->limit + $pos + 1;

                // count how many records in the current result page
                $count = count($this->data->currIds);

                // if the current record is somewhere in the middle of the current
                // page, ie: not first or last, then it is easy
                if ($pos > 0 && $pos < $count - 1) {
                    $retVal['previousRecord'] = $this->data->currIds[$pos - 1];
                    $retVal['nextRecord'] = $this->data->currIds[$pos + 1];
                    // and we're done
                    return $retVal;
                }

                // if this record is first record on the current page
                if ($pos == 0) {
                    // if the current page is NOT the first page, and
                    // the previous page has not been fetched before, then
                    // fetch the previous page
                    if ($this->data->page > 1
                        && $this->data->prevIds == null
                    ) {
                        $this->data->prevIds = $this->fetchPage(
                            $lastSearch, $this->data->page - 1
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

                // if this record is last record on the current page
                if ($pos == $count - 1) {
                    // if the next page has not been fetched, then
                    // fetch the next page
                    if ($this->data->nextIds == null) {
                        $this->data->nextIds = $this->fetchPage(
                            $lastSearch, $this->data->page + 1
                        );
                    }

                    // if there is something on the next page, then the next
                    // record is the first record on the next page
                    if (!empty($this->data->nextIds)) {
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
            } else {
                // the current record is not on the current page
                // if there is something on the previous page
                if (!empty($this->data->prevIds)) {
                    // check if current record is on the previous page
                    $pos = is_array($this->data->prevIds)
                        ? array_search($id, $this->data->prevIds) : false;
                    if ($pos !== false) {
                        // decrease the page in the session because
                        // we're now sliding into the previous page
                        // (for some reason, -- doesn't work here)
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
                        $lastSearch->setPage($this->data->page);
                        $this->rememberSearch($lastSearch);

                        // and we're done
                        return $retVal;
                    }
                }

                // if there is something on the next page
                if (!empty($this->data->nextIds)) {
                    // check if current record is on the next page
                    $pos = is_array($this->data->nextIds)
                        ? array_search($id, $this->data->nextIds) : false;
                    if ($pos !== false) {
                        // increase the page in the session because
                        // we're now sliding into the next page
                        // (for some reason, ++ doesn't work here)
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
                        $lastSearch->setPage($this->data->page);
                        $this->rememberSearch($lastSearch);

                        // and we're done
                        return $retVal;
                    }
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
        if (!is_null($page)) {
            $searchObject->setPage($page);
            $searchObject->performAndProcessSearch();
        }

        $retVal = array();
        foreach ($searchObject->getResults() as $record) {
            $retVal[] = $record->getUniqueId();
        }
        return $retVal;
    }

    /**
     * Restore the last saved search.
     *
     * @return VF_Search_Base_Results
     */
    protected function restoreLastSearch()
    {
        if (isset($this->data->searchId)) {
            $searchTable = new SearchTable();
            $row = $searchTable->getRowById($this->data->searchId, false);
            if (!empty($row)) {
                $minSO = unserialize($row->search_object);
                return $minSO->deminify();
            }
        }
        return null;
    }

    /**
     * Update the remembered "last search" in the session.
     *
     * @param VF_Search_Base_Results $search Search object to remember.
     *
     * @return void
     */
    protected function rememberSearch($search)
    {
        $baseUrl = $this->getController()->url()->fromRoute(
            $search->getSearchAction()
        );
        Memory::rememberSearch(
            $baseUrl . $search->getUrl()->getParams(false)
        );
    }
}
