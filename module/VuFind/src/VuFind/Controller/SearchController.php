<?php
/**
 * Default Controller
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
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Controller;

use VuFind\Cache\Manager as CacheManager, VuFind\Db\Table\Search as SearchTable,
    VuFind\Search\Memory, VuFind\Search\Solr\Params, VuFind\Search\Solr\Results,
    VuFind\Solr\Utils as SolrUtils;

/**
 * Redirects the user to the appropriate default VuFind action.
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class SearchController extends AbstractSearch
{
    /**
     * Handle an advanced search
     *
     * @return void
     */
    public function advancedAction()
    {
        // Standard setup from base class:
        parent::advancedAction();

        /* TODO
        // Set up facet information:
        $this->view->facetList = $this->processAdvancedFacets(
            $this->getAdvancedFacets()->getFacetList(), $this->view->saved
        );
        $specialFacets = $this->view->options->getSpecialAdvancedFacets();
        if (stristr($specialFacets, 'illustrated')) {
            $this->view->illustratedLimit
                = $this->getIllustrationSettings($this->view->saved);
        }
        if (stristr($specialFacets, 'daterange')) {
            $this->view->dateRangeLimit
                = $this->getDateRangeSettings($this->view->saved);
        }
         */
    }

    /**
     * Email action - Allows the email form to appear.
     *
     * @return void
     */
    public function emailAction()
    {
        /* TODO
        // If a URL was explicitly passed in, use that; otherwise, try to
        // find the HTTP referrer.
        $this->view->url = $this->_request->getParam(
            'url', $this->_request->getServer('HTTP_REFERER')
        );

        // Fail if we can't figure out a URL to share:
        if (empty($this->view->url)) {
            throw new Exception('Cannot determine URL to share.');
        }

        // Process form submission:
        if ($this->_request->getParam('submit')) {
            // Send parameters back to view so form can be re-populated:
            $this->view->to = $this->_request->getParam('to');
            $this->view->from = $this->_request->getParam('from');
            $this->view->message = $this->_request->getParam('message');

            // Attempt to send the email and show an appropriate flash message:
            try {
                // If we got this far, we're ready to send the email:
                $mailer = new VF_Mailer();
                $mailer->sendLink(
                    $this->view->to, $this->view->from, $this->view->message,
                    $this->view->url, $this->view
                );
                $this->_helper->flashMessenger->setNamespace('info')
                    ->addMessage('email_success');
                return $this->_redirect($this->view->url);
            } catch (VF_Exception_Mail $e) {
                $this->_helper->flashMessenger->setNamespace('error')
                    ->addMessage($e->getMessage());
            }
        }
         */
    }

    /**
     * Get the possible legal values for the illustration limit radio buttons.
     *
     * @param object $savedSearch Saved search object (false if none)
     *
     * @return array              Legal options, with selected value flagged.
     */
    protected function getIllustrationSettings($savedSearch = false)
    {
        $illYes= array(
            'text' => 'Has Illustrations', 'value' => 1, 'selected' => false
        );
        $illNo = array(
            'text' => 'Not Illustrated', 'value' => 0, 'selected' => false
        );
        $illAny = array(
            'text' => 'No Preference', 'value' => -1, 'selected' => false
        );

        // Find the selected value by analyzing facets -- if we find match, remove
        // the offending facet to avoid inappropriate items appearing in the
        // "applied filters" sidebar!
        if ($savedSearch && $savedSearch->hasFilter('illustrated:Illustrated')) {
            $illYes['selected'] = true;
            $savedSearch->removeFilter('illustrated:Illustrated');
        } else if ($savedSearch
            && $savedSearch->hasFilter('illustrated:"Not Illustrated"')
        ) {
            $illNo['selected'] = true;
            $savedSearch->removeFilter('illustrated:"Not Illustrated"');
        } else {
            $illAny['selected'] = true;
        }
        return array($illYes, $illNo, $illAny);
    }

    /**
     * Get the current settings for the date range facet, if it is set:
     *
     * @param object $savedSearch Saved search object (false if none)
     *
     * @return array              Date range: Key 0 = from, Key 1 = to.
     */
    protected function getDateRangeSettings($savedSearch = false)
    {
        // Default to blank strings:
        $from = $to = '';

        // Check to see if there is an existing range in the search object:
        if ($savedSearch) {
            $filters = $savedSearch->getFilters();
            if (isset($filters['publishDate'])) {
                foreach ($filters['publishDate'] as $current) {
                    if ($range = SolrUtils::parseRange($current)) {
                        $from = $range['from'] == '*' ? '' : $range['from'];
                        $to = $range['to'] == '*' ? '' : $range['to'];
                        $savedSearch->removeFilter('publishDate:' . $current);
                        break;
                    }
                }
            }
        }

        // Send back the settings:
        return array($from, $to);
    }

    /**
     * Process the facets to be used as limits on the Advanced Search screen.
     *
     * @param array  $facetList    The advanced facet values
     * @param object $searchObject Saved search object (false if none)
     *
     * @return array               Sorted facets, with selected values flagged.
     */
    protected function processAdvancedFacets($facetList, $searchObject = false)
    {
        // Process the facets, assuming they came back
        foreach ($facetList as $facet => $list) {
            foreach ($list['list'] as $key => $value) {
                // Build the filter string for the URL:
                $fullFilter = $facet.':"'.$value['value'].'"';

                // If we haven't already found a selected facet and the current
                // facet has been applied to the search, we should store it as
                // the selected facet for the current control.
                if ($searchObject && $searchObject->hasFilter($fullFilter)) {
                    $facetList[$facet]['list'][$key]['selected'] = true;
                    // Remove the filter from the search object -- we don't want
                    // it to show up in the "applied filters" sidebar since it
                    // will already be accounted for by being selected in the
                    // filter select list!
                    $searchObject->removeFilter($fullFilter);
                }
            }
        }
        return $facetList;
    }

    /**
     * Handle search history display && purge
     *
     * @return void
     */
    public function historyAction()
    {
        // Force login if necessary
        $user = $this->getUser();
        if ($this->params()->fromQuery('require_login', 'no') !== 'no' && !$user) {
            return $this->forceLogin();
        }

        // Retrieve search history
        $search = new SearchTable();
        $searchHistory = $search->getSearches(
            $this->getServiceLocator()->get('SessionManager')->getId(),
            is_object($user) ? $user->id : null
        );

        // Build arrays of history entries
        $saved = $unsaved = array();

        // Loop through the history
        foreach ($searchHistory as $current) {
            $minSO = unserialize($current->search_object);

            // Saved searches
            if ($current->saved == 1) {
                $saved[] = $minSO->deminify();
            } else {
                // All the others...

                // If this was a purge request we don't need this
                if ($this->params()->fromQuery('purge') == 'true') {
                    $current->delete();

                    // We don't want to remember the last search after a purge:
                    Memory::forgetSearch();
                } else {
                    // Otherwise add to the list
                    $unsaved[] = $minSO->deminify();
                }
            }
        }

        return $this->createViewModel(
            array('saved' => $saved, 'unsaved' => $unsaved)
        );
    }

    /**
     * Home action
     *
     * @return void
     */
    public function homeAction()
    {
        return $this->createViewModel(
            array('results' => $this->getAdvancedFacets())
        );
    }

    /**
     * New item search form
     *
     * @return void
     */
    public function newitemAction()
    {
        /* TODO
        // Search parameters set?  Process results.
        if ($this->_request->getParam('range') !== null) {
            return $this->_forward('NewItemResults');
        }

        $catalog = VF_Connection_Manager::connectToCatalog();
        $this->view->fundList = $catalog->getFunds();

        // Find out if there are user configured range options; if not,
        // default to the standard 1/5/30 days:
        $this->view->ranges = array();
        $searchSettings = VF_Config_Reader::getConfig('searches');
        if (isset($searchSettings->NewItem->ranges)) {
            $tmp = explode(',', $searchSettings->NewItem->ranges);
            foreach ($tmp as $range) {
                $range = intval($range);
                if ($range > 0) {
                    $this->view->ranges[] = $range;
                }
            }
        }
        if (empty($this->view->ranges)) {
            $this->view->ranges = array(1, 5, 30);
        }
         */
    }

    /**
     * New item result list
     *
     * @return void
     */
    public function newitemresultsAction()
    {
        /* TODO
        // Retrieve new item list:
        $range = $this->_request->getParam('range');
        $dept = $this->_request->getParam('department');

        $searchSettings = VF_Config_Reader::getConfig('searches');

        // The code always pulls in enough catalog results to get a fixed number
        // of pages worth of Solr results.  Note that if the Solr index is out of
        // sync with the ILS, we may see fewer results than expected.
        $params = new VF_Search_Solr_Params();
        if (isset($searchSettings->NewItem->result_pages)) {
            $resultPages = intval($searchSettings->NewItem->result_pages);
            if ($resultPages < 1) {
                $resultPages = 10;
            }
        } else {
            $resultPages = 10;
        }
        $catalog = VF_Connection_Manager::connectToCatalog();
        $perPage = $params->getLimit();
        $newItems = $catalog->getNewItems(1, $perPage * $resultPages, $range, $dept);

        // Build a list of unique IDs
        $bibIDs = array();
        for ($i=0; $i<count($newItems['results']); $i++) {
            $bibIDs[] = $newItems['results'][$i]['id'];
        }

        // Truncate the list if it is too long:
        $limit = $params->getQueryIDLimit();
        if (count($bibIDs) > $limit) {
            $bibIDs = array_slice($bibIDs, 0, $limit);
            $this->_helper->flashMessenger->setNamespace('info')
                ->addMessage('too_many_new_items');
        }

        // Use standard search action with override parameter to show results:
        $this->_request->setParam('overrideIds', $bibIDs);

        // Are there "new item" filter queries specified in the config file?
        // If so, we should apply them as hidden filters so they do not show
        // up in the user-selected facet list.
        if (isset($searchSettings->NewItem->filter)) {
            if (is_string($searchSettings->NewItem->filter)) {
                $hiddenFilters = array($searchSettings->NewItem->filter);
            } else {
                $hiddenFilters = array();
                foreach ($searchSettings->NewItem->filter as $current) {
                    $hiddenFilters[] = $current;
                }
            }
            $this->_request->setParam('hiddenFilters', $hiddenFilters);
        }

        // Call rather than forward, so we can use custom template
        $this->resultsAction();

        // Customize the URL helper to make sure it builds proper reserves URLs:
        $url = $this->view->results->getUrl();
        $url->setDefaultParameter('range', $range);
        $url->setDefaultParameter('department', $dept);
         */
    }

    /**
     * Course reserves
     *
     * @return void
     */
    public function reservesAction()
    {
        /* TODO
        // Search parameters set?  Process results.
        if ($this->_request->getParam('inst') !== null
            || $this->_request->getParam('course') !== null
            || $this->_request->getParam('dept') !== null
        ) {
            return $this->_forward('ReservesResults');
        }
        
        // No params?  Show appropriate form (varies depending on whether we're
        // using driver-based or Solr-based reserves searching).
        if ($this->_helper->reserves()->useIndex()) {
            return $this->_forward('ReservesSearch');
        }

        // If we got this far, we're using driver-based searching and need to
        // send options to the view:
        $catalog = VF_Connection_Manager::connectToCatalog();
        $this->view->deptList = $catalog->getDepartments();
        $this->view->instList = $catalog->getInstructors();
        $this->view->courseList =  $catalog->getCourses();
         */
    }

    /**
     * Show search form for Solr-driven reserves.
     *
     * @return void
     */
    public function reservessearchAction()
    {
        /* TODO
        $params = new VF_Search_SolrReserves_Params();
        $params->initFromRequest($this->_request);
        $this->view->results = new VF_Search_SolrReserves_Results($params);
         */
    }

    /**
     * Show results of reserves search.
     *
     * @return void
     */
    public function reservesresultsAction()
    {
        /* TODO
        // Retrieve course reserves item list:
        $course = $this->_request->getParam('course');
        $inst = $this->_request->getParam('inst');
        $dept = $this->_request->getParam('dept');
        $result = $this->_helper->reserves()->findReserves($course, $inst, $dept);

        // Pass some key values to the view, if found:
        if (isset($result[0]['instructor']) && !empty($result[0]['instructor'])) {
            $this->view->instructor = $result[0]['instructor'];
        }
        if (isset($result[0]['course']) && !empty($result[0]['course'])) {
            $this->view->course = $result[0]['course'];
        }

        // Build a list of unique IDs
        $bibIDs = array();
        foreach ($result as $record) {
            // Avoid duplicate IDs (necessary for Voyager ILS driver):
            if (!in_array($record['BIB_ID'], $bibIDs)) {
                $bibIDs[] = $record['BIB_ID'];
            }
        }

        // Truncate the list if it is too long:
        $params = new VF_Search_Solr_Params();
        $limit = $params->getQueryIDLimit();
        if (count($bibIDs) > $limit) {
            $bibIDs = array_slice($bibIDs, 0, $limit);
            $this->_helper->flashMessenger->setNamespace('info')
                ->addMessage('too_many_reserves');
        }

        // Use standard search action with override parameter to show results:
        $this->_request->setParam('overrideIds', $bibIDs);

        // Call rather than forward, so we can use custom template
        $this->resultsAction();

        // Customize the URL helper to make sure it builds proper reserves URLs:
        $url = $this->view->results->getUrl();
        $url->setDefaultParameter('course', $course);
        $url->setDefaultParameter('inst', $inst);
        $url->setDefaultParameter('dept', $dept);
         */
    }

    /**
     * Given a saved search ID, redirect the user to the appropriate place.
     *
     * @param int $id ID from search history
     *
     * @return void
     */
    protected function redirectToSavedSearch($id)
    {
        /* TODO
        $table = new SearchTable();
        $search = $table->getRowById($id);

        // Found, make sure the user has the rights to view this search
        $sessId = $this->getServiceLocator()->get('SessionManager')->getId();
        if ($search->session_id == $sessId || $search->user_id == $this->user->id) {
            // They do, deminify it to a new object.
            $minSO = unserialize($search->search_object);
            $savedSearch = $minSO->deminify();

            // Now redirect to the URL associated with the saved search; this
            // simplifies problems caused by mixing different classes of search
            // object, and it also prevents the user from ever landing on a
            // "?saved=xxxx" URL, which may not persist beyond the current session.
            // (We want all searches to be persistent and bookmarkable).
            $details = $savedSearch->getSearchAction();
            $url = '/' . $details['controller'] . '/' . $details['action'];
            $url .= $savedSearch->getUrl()->getParams(false);
            return $this->_redirect($url);
        } else {
            // They don't
            // TODO : Error handling -
            //    User is trying to view a saved search from another session
            //    (deliberate or expired) or associated with another user.
            throw new Exception("Attempt to access invalid search ID");
        }
         */
    }

    /**
     * Return a Search Results object containing advanced facet information.  This
     * data may come from the cache, and it is currently shared between the Home
     * page and the Advanced search screen.
     *
     * @return VF_Search_Solr_Results
     */
    protected function getAdvancedFacets()
    {
        // Check if we have facet results cached, and build them if we don't.
        $cache = CacheManager::getInstance()->getCache('object');
        if (!($results = $cache->getItem('solrSearchHomeFacets'))) {
            // Use advanced facet settings to get summary facets on the front page;
            // we may want to make this more flexible later.  Also keep in mind that
            // the template is currently looking for certain hard-coded fields; this
            // should also be made smarter.
            $params = new Params();
            $params->initAdvancedFacets();

            // We only care about facet lists, so don't get any results (this helps
            // prevent problems with serialized File_MARC objects in the cache):
            $params->setLimit(0);

            $results = new Results($params);
            /* TODO: fix caching:
            $cache->setItem($results, 'solrSearchHomeFacets');
             */
        }
        return $results;
    }

    /**
     * Handle OpenSearch.
     *
     * @return void
     */
    public function opensearchAction()
    {
        /* TODO
        $this->getResponse()->setHeader('Content-type', 'text/xml');
        $this->_helper->layout->disableLayout();
        switch ($this->_request->getParam('method')) {
        case 'describe':
            $config = VF_Config_Reader::getConfig();
            $this->view->site = $config->Site;
            $this->render('search/opensearch-describe', null, true);
            return;
        default:
            $this->render('search/opensearch-error', null, true);
        }
         */
    }

    /**
     * Provide OpenSearch suggestions as specified here:
     *
     * http://www.opensearch.org/Specifications/OpenSearch/Extensions/Suggestions/1.0
     *
     * @return void
     */
    public function suggestAction()
    {
        /* TODO
        // Set up JSON response format -- we don't want to render a normal page here:
        $this->_helper->viewRenderer->setNoRender();
        $this->_helper->layout->disableLayout();

        // Always use 'AllFields' as our autosuggest type:
        $this->_request->setParam('type', 'AllFields');

        // Get suggestions and make sure they are an array (we don't want to JSON
        // encode them into an object):
        $rawSuggestions = VF_Autocomplete_Factory::getSuggestions(
            $this->_request, 'type', 'lookfor'
        );
        $suggestions = array();
        foreach ($rawSuggestions as $c) {
            $suggestions[] = $c;
        }

        // Send the JSON response:
        $this->getResponse()->setHeader('Content-type', 'application/javascript');
        $this->getResponse()->appendBody(
            json_encode(
                array(
                    $this->_request->getParam('lookfor', ''), $suggestions,
                )
            )
        );
         */
    }
}
