<?php
/**
 * VuFind Search Controller
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
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFind\Controller;
use VuFind\Account\Manager as AccountManager, VuFind\Search\Memory,
    VuFind\Search\Options as SearchOptions, VuFind\Search\ResultScroller,
    Zend\View\Model\ViewModel;
/**
 * VuFind Search Controller
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class AbstractSearch extends AbstractBase
{
    protected $searchClassId = 'Solr';
    protected $saveToHistory = true;
    protected $logStatistics = true;
    protected $rememberSearch = true;
    protected $useResultScroller = true;
    protected $user;

    /**
     * init
     *
     * @return void
     */
    public function init()
    {
        $this->user = AccountManager::getInstance()->isLoggedIn();
        /* TODO
        $this->view->searchClassId = $this->searchClassId;
        $this->view->flashMessenger = $this->_helper->flashMessenger;
         */
    }

    /**
     * Handle an advanced search
     *
     * @return ViewModel
     */
    public function advancedAction()
    {
        $view = new ViewModel();
        $view->options = SearchOptions::getInstance($this->searchClassId);
        if ($view->options->getAdvancedSearchAction() === false) {
            throw new \Exception('Advanced search not supported.');
        }

        // Handle request to edit existing saved search:
        $view->saved = false;
        $searchId = $this->getRequest()->query()->get('edit', false);
        if ($searchId !== false) {
            $view->saved = $this->restoreAdvancedSearch($searchId);
        }

        return $view;
    }

    /**
     * Send search results to results view
     *
     * @return ViewModel
     */
    public function resultsAction()
    {
        $view = new ViewModel();

        // Handle saved search requests:
        $savedId = $this->getRequest()->query()->get('saved', false);
        if ($savedId !== false) {
            return $this->redirectToSavedSearch($savedId);
        }

        $paramsClass = $this->getParamsClass();
        $params = new $paramsClass();
        $params->recommendationsEnabled(true);
        $params->initFromRequest($this->getRequest());
        // Attempt to perform the search; if there is a problem, inspect any Solr
        // exceptions to see if we should communicate to the user about them.
        try {
            $resultsClass = $this->getResultsClass();
            $results = new $resultsClass($params);

            // Explicitly execute search within controller -- this allows us to
            // catch exceptions more reliably:
            $results->performAndProcessSearch();

            // If a "jumpto" parameter is set, deal with that now:
            if ($jump = $this->processJumpTo($results)) {
                return $jump;
            }

            // Send results to the view and remember the current URL as the last
            // search.
            $view->results = $results;
            /* TODO
            if ($this->rememberSearch) {
                Memory::rememberSearch(
                    $this->view->url() . $results->getUrl()->getParams(false)
                );
            }
             */

            /* TODO
            // Add to search history:
            if ($this->saveToHistory) {
                $history = new VuFind_Model_Db_Search();
                $history->saveSearch(
                    $results,
                    $history->getSearches(
                        Zend_Session::getId(),
                        isset($this->user->id) ? $this->user->id : null
                    )
                );
            }
             */

            // Set up results scroller:
            if ($this->useResultScroller) {
                $scroller = new ResultScroller();
                $scroller->init($results);
            }
        } catch (\Exception $e) {
            // If it's a parse error or the user specified an invalid field, we
            // should display an appropriate message:
            if (method_exists($e, 'isParseError') && $e->isParseError()) {
                $view->parseError = true;

                // We need to create and process an "empty results" object to
                // ensure that recommendation modules and templates behave
                // properly when displaying the error message.
                $view->results = new VF_Search_Empty_Results($params);
                $view->results->performAndProcessSearch();
            } else {
                // Unexpected error -- let's throw this up to the next level.
                throw $e;
            }
        }
        /* TODO
        // Save statistics:
        if ($this->logStatistics) {
            $statController = new VF_Statistics_Search();
            $statController->log($results, $this->getRequest());
        }
         */

        /* TODO
        // Special case: If we're in RSS view, we need to render differently:
        if ($this->view->results->getView() == 'rss') {
            $this->_helper->viewRenderer->setNoRender();
            $this->_helper->layout->disableLayout();
            header('Content-type: text/xml', true);
            echo $this->view->ResultFeed($this->view->results)->export('rss');
        }
         */
        return $view;
    }

    /**
     * Process the jumpto parameter -- either redirect to a specific record and
     * return view model, or ignore the parameter and return false.
     *
     * @param \VuFind\Search\Base\Results $results Search results object.
     *
     * @return bool|ViewModel
     */
    protected function processJumpTo($results)
    {
        // Missing/invalid parameter?  Ignore it:
        $jumpto = $this->getRequest()->query()->get('jumpto');
        if (empty($jumpto) || !is_numeric($jumpto)) {
            return false;
        }

        // Parameter out of range?  Ignore it:
        $recordList = $results->getResults();
        if (!isset($recordList[$jumpto - 1])) {
            return false;
        }

        // If we got this far, we have a valid parameter so we should redirect
        // and report success:
        return $this->redirect()->toRoute(
            $recordList[$jumpto - 1]->getRecordRoute(),
            array('id' => $recordList[$jumpto - 1]->getUniqueId())
        );
    }

    /**
     * Get the name of the class used for setting search parameters.
     *
     * @return string
     */
    protected function getParamsClass()
    {
        return 'VuFind\\Search\\' . $this->searchClassId . '\\Params';
    }

    /**
     * Get the name of the class used for retrieving search results.
     *
     * @return string
     */
    protected function getResultsClass()
    {
        return 'VuFind\\Search\\' . $this->searchClassId . '\\Results';
    }

    /**
     * Either assign the requested search object to the view or display a flash
     * message indicating why the operation failed.
     *
     * @param string $searchId ID value of a saved advanced search.
     *
     * @return bool|object     Restored search object if found, false otherwise.
     */
    protected function restoreAdvancedSearch($searchId)
    {
        /* TODO
        // Look up search in database and fail if it is not found:
        $searchTable = new VuFind_Model_Db_Search();
        $rows = $searchTable->find($searchId);
        if (count($rows) < 1) {
            $this->_helper->flashMessenger->setNamespace('error')
                ->addMessage('advSearchError_notFound');
            return false;
        }
        $search = $rows->getRow(0);

        // Fail if user has no permission to view this search:
        if ($search->session_id != Zend_Session::getId()
            && $search->user_id != $this->user->id
        ) {
            $this->_helper->flashMessenger->setNamespace('error')
                ->addMessage('advSearchError_noRights');
            return false;
        }

        // Restore the full search object:
        $minSO = unserialize($search->search_object);
        $savedSearch = $minSO->deminify();

        // Fail if this is not the right type of search:
        if ($savedSearch->getSearchType() != 'advanced') {
            $this->_helper->flashMessenger->setNamespace('error')
                ->addMessage('advSearchError_notAdvanced');
            return false;
        }

        // Activate facets so we get appropriate descriptions in the filter list:
        $savedSearch->activateAllFacets('Advanced');

        // Make the object available to the view:
        return $savedSearch;
         */
    }
}