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

use VuFind\Cache\Manager as CacheManager, VuFind\Config\Reader as ConfigReader,
    VuFind\Connection\Manager as ConnectionManager,
    VuFind\Db\Table\Search as SearchTable, VuFind\Exception\Mail as MailException,
    VuFind\Mailer, VuFind\Search\Memory, VuFind\Solr\Utils as SolrUtils;

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
     * @return mixed
     */
    public function advancedAction()
    {
        // Standard setup from base class:
        $view = parent::advancedAction();

        // Set up facet information:
        $view->facetList = $this->processAdvancedFacets(
            $this->getAdvancedFacets()->getFacetList(), $view->saved
        );
        $specialFacets = $view->options->getSpecialAdvancedFacets();
        if (stristr($specialFacets, 'illustrated')) {
            $view->illustratedLimit
                = $this->getIllustrationSettings($view->saved);
        }
        if (stristr($specialFacets, 'daterange')) {
            $view->dateRangeLimit
                = $this->getDateRangeSettings($view->saved);
        }
        return $view;
    }

    /**
     * Email action - Allows the email form to appear.
     *
     * @return mixed
     */
    public function emailAction()
    {
        // If a URL was explicitly passed in, use that; otherwise, try to
        // find the HTTP referrer.
        $view = $this->createViewModel();
        $view->url = $this->params()->fromPost(
            'url', $this->params()->fromQuery(
                'url', $this->getRequest()->getServer()->get('HTTP_REFERER')
            )
        );

        // Fail if we can't figure out a URL to share:
        if (empty($view->url)) {
            throw new \Exception('Cannot determine URL to share.');
        }

        // Process form submission:
        if ($this->params()->fromPost('submit')) {
            // Send parameters back to view so form can be re-populated:
            $view->to = $this->params()->fromPost('to');
            $view->from = $this->params()->fromPost('from');
            $view->message = $this->params()->fromPost('message');

            // Attempt to send the email and show an appropriate flash message:
            try {
                // If we got this far, we're ready to send the email:
                $mailer = new Mailer();
                $mailer->sendLink(
                    $view->to, $view->from, $view->message,
                    $view->url, $this->getViewRenderer()
                );
                $this->flashMessenger()->setNamespace('info')
                    ->addMessage('email_success');
                return $this->redirect()->toUrl($view->url);
            } catch (MailException $e) {
                $this->flashMessenger()->setNamespace('error')
                    ->addMessage($e->getMessage());
            }
        }
        return $view;
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
        if ($savedSearch
            && $savedSearch->getParams()->hasFilter('illustrated:Illustrated')
        ) {
            $illYes['selected'] = true;
            $savedSearch->getParams()->removeFilter('illustrated:Illustrated');
        } else if ($savedSearch
            && $savedSearch->getParams()->hasFilter('illustrated:"Not Illustrated"')
        ) {
            $illNo['selected'] = true;
            $savedSearch->getParams()->removeFilter('illustrated:"Not Illustrated"');
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
            $filters = $savedSearch->getParams()->getFilters();
            if (isset($filters['publishDate'])) {
                foreach ($filters['publishDate'] as $current) {
                    if ($range = SolrUtils::parseRange($current)) {
                        $from = $range['from'] == '*' ? '' : $range['from'];
                        $to = $range['to'] == '*' ? '' : $range['to'];
                        $savedSearch->getParams()
                            ->removeFilter('publishDate:' . $current);
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
                if ($searchObject
                    && $searchObject->getParams()->hasFilter($fullFilter)
                ) {
                    $facetList[$facet]['list'][$key]['selected'] = true;
                    // Remove the filter from the search object -- we don't want
                    // it to show up in the "applied filters" sidebar since it
                    // will already be accounted for by being selected in the
                    // filter select list!
                    $searchObject->getParams()->removeFilter($fullFilter);
                }
            }
        }
        return $facetList;
    }

    /**
     * Handle search history display && purge
     *
     * @return mixed
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
                $saved[] = $minSO->deminify($this->getSearchManager());
            } else {
                // All the others...

                // If this was a purge request we don't need this
                if ($this->params()->fromQuery('purge') == 'true') {
                    $current->delete();

                    // We don't want to remember the last search after a purge:
                    Memory::forgetSearch();
                } else {
                    // Otherwise add to the list
                    $unsaved[] = $minSO->deminify($this->getSearchManager());
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
     * @return mixed
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
     * @return mixed
     */
    public function newitemAction()
    {
        // Search parameters set?  Process results.
        if ($this->params()->fromQuery('range') !== null) {
            return $this->forwardTo('Search', 'NewItemResults');
        }

        // Find out if there are user configured range options; if not,
        // default to the standard 1/5/30 days:
        $ranges = array();
        $searchSettings = ConfigReader::getConfig('searches');
        if (isset($searchSettings->NewItem->ranges)) {
            $tmp = explode(',', $searchSettings->NewItem->ranges);
            foreach ($tmp as $range) {
                $range = intval($range);
                if ($range > 0) {
                    $ranges[] = $range;
                }
            }
        }
        if (empty($ranges)) {
            $ranges = array(1, 5, 30);
        }

        $catalog = ConnectionManager::connectToCatalog();
        return $this->createViewModel(
            array('fundList' => $catalog->getFunds(), 'ranges' => $ranges)
        );
    }

    /**
     * New item result list
     *
     * @return mixed
     */
    public function newitemresultsAction()
    {
        // Retrieve new item list:
        $range = $this->params()->fromQuery('range');
        $dept = $this->params()->fromQuery('department');

        $searchSettings = ConfigReader::getConfig('searches');

        // The code always pulls in enough catalog results to get a fixed number
        // of pages worth of Solr results.  Note that if the Solr index is out of
        // sync with the ILS, we may see fewer results than expected.
        if (isset($searchSettings->NewItem->result_pages)) {
            $resultPages = intval($searchSettings->NewItem->result_pages);
            if ($resultPages < 1) {
                $resultPages = 10;
            }
        } else {
            $resultPages = 10;
        }
        $catalog = ConnectionManager::connectToCatalog();
        $sm = $this->getSearchManager()->setSearchClassId('Solr');
        $params = $sm->getParams();
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
            $this->flashMessenger()->setNamespace('info')
                ->addMessage('too_many_new_items');
        }

        // Use standard search action with override parameter to show results:
        $this->getRequest()->getQuery()->set('overrideIds', $bibIDs);

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
            $this->getRequest()->getQuery()->set('hiddenFilters', $hiddenFilters);
        }

        // Call rather than forward, so we can use custom template
        $view = $this->resultsAction();

        // Customize the URL helper to make sure it builds proper reserves URLs:
        $url = $view->results->getUrlQuery();
        $url->setDefaultParameter('range', $range);
        $url->setDefaultParameter('department', $dept);

        return $view;
    }

    /**
     * Course reserves
     *
     * @return mixed
     */
    public function reservesAction()
    {
        // Search parameters set?  Process results.
        if ($this->params()->fromQuery('inst') !== null
            || $this->params()->fromQuery('course') !== null
            || $this->params()->fromQuery('dept') !== null
        ) {
            return $this->forwardTo('Search', 'ReservesResults');
        }
        
        // No params?  Show appropriate form (varies depending on whether we're
        // using driver-based or Solr-based reserves searching).
        if ($this->reserves()->useIndex()) {
            return $this->forwardTo('Search', 'ReservesSearch');
        }

        // If we got this far, we're using driver-based searching and need to
        // send options to the view:
        $catalog = ConnectionManager::connectToCatalog();
        return $this->createViewModel(
            array(
                'deptList' => $catalog->getDepartments(),
                'instList' => $catalog->getInstructors(),
                'courseList' =>  $catalog->getCourses()
            )
        );
    }

    /**
     * Show search form for Solr-driven reserves.
     *
     * @return mixed
     */
    public function reservessearchAction()
    {
        $sm = $this->getSearchManager()->setSearchClassId('SolrReserves');
        $params = $sm->getParams();
        $params->initFromRequest($this->getRequest()->getQuery());
        $results = $sm->getResults($params);
        return $this->createViewModel(array('results' => $results));
    }

    /**
     * Show results of reserves search.
     *
     * @return mixed
     */
    public function reservesresultsAction()
    {
        // Retrieve course reserves item list:
        $course = $this->params()->fromQuery('course');
        $inst = $this->params()->fromQuery('inst');
        $dept = $this->params()->fromQuery('dept');
        $result = $this->reserves()->findReserves($course, $inst, $dept);

        // Build a list of unique IDs
        $callback = function($i) {
            return $i['BIB_ID'];
        };
        $bibIDs = array_unique(array_map($callback, $result));

        // Truncate the list if it is too long:
        $sm = $this->getSearchManager()->setSearchClassId('Solr');
        $params = $sm->getParams();
        $limit = $params->getQueryIDLimit();
        if (count($bibIDs) > $limit) {
            $bibIDs = array_slice($bibIDs, 0, $limit);
            $this->flashMessenger()->setNamespace('info')
                ->addMessage('too_many_reserves');
        }

        // Use standard search action with override parameter to show results:
        $this->getRequest()->getQuery()->set('overrideIds', $bibIDs);

        // Call rather than forward, so we can use custom template
        $view = $this->resultsAction();

        // Pass some key values to the view, if found:
        if (isset($result[0]['instructor']) && !empty($result[0]['instructor'])) {
            $view->instructor = $result[0]['instructor'];
        }
        if (isset($result[0]['course']) && !empty($result[0]['course'])) {
            $view->course = $result[0]['course'];
        }

        // Customize the URL helper to make sure it builds proper reserves URLs:
        $url = $view->results->getUrlQuery();
        $url->setDefaultParameter('course', $course);
        $url->setDefaultParameter('inst', $inst);
        $url->setDefaultParameter('dept', $dept);
        return $view;
    }

    /**
     * Return a Search Results object containing advanced facet information.  This
     * data may come from the cache, and it is currently shared between the Home
     * page and the Advanced search screen.
     *
     * @return \VuFind\Search\Solr\Results
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
            $sm = $this->getSearchManager()->setSearchClassId('Solr');
            $params = $sm->getParams();
            $params->initAdvancedFacets();

            // We only care about facet lists, so don't get any results (this helps
            // prevent problems with serialized File_MARC objects in the cache):
            $params->setLimit(0);

            $results = $sm->getResults($params);
            $results->getResults();                     // force processing for cache

            // Temporarily remove the service manager so we can cache the
            // results (otherwise we'll get errors about serializing closures):
            $results->unsetServiceLocator();
            $cache->setItem('solrSearchHomeFacets', $results);
        }

        // Restore the real service locator to the object:
        $results->restoreServiceLocator($this->getServiceLocator());
        return $results;
    }

    /**
     * Handle OpenSearch.
     *
     * @return \Zend\Http\Response
     */
    public function opensearchAction()
    {
        switch ($this->params()->fromQuery('method')) {
        case 'describe':
            $config = ConfigReader::getConfig();
            $xml = $this->getViewRenderer()->render(
                'search/opensearch-describe.phtml', array('site' => $config->Site)
            );
            break;
        default:
            $xml = $this->getViewRenderer()->render('search/opensearch-error.phtml');
            break;
        }

        $response = $this->getResponse();
        $headers = $response->getHeaders();
        $headers->addHeaderLine('Content-type', 'text/xml');
        $response->setContent($xml);
        return $response;
    }

    /**
     * Provide OpenSearch suggestions as specified here:
     *
     * http://www.opensearch.org/Specifications/OpenSearch/Extensions/Suggestions/1.0
     *
     * @return \Zend\Http\Response
     */
    public function suggestAction()
    {
        // Always use 'AllFields' as our autosuggest type:
        $query = $this->getRequest()->getQuery();
        $query->set('type', 'AllFields');

        // Get suggestions and make sure they are an array (we don't want to JSON
        // encode them into an object):
        $autocompleteManager = $this->getServiceLocator()
            ->get('AutocompleteHandlerManager');
        $suggestions = $autocompleteManager->getSuggestions(
            $query, 'type', 'lookfor'
        );

        // Send the JSON response:
        $response = $this->getResponse();
        $headers = $response->getHeaders();
        $headers->addHeaderLine('Content-type', 'application/javascript');
        $response->setContent(
            json_encode(array($query->get('lookfor', ''), $suggestions))
        );
        return $response;
    }
}
