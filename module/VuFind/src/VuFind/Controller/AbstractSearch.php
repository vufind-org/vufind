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
use VuFind\Search\RecommendListener, VuFind\Solr\Utils as SolrUtils;
use Zend\Stdlib\Parameters;

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
    /**
     * Search class family to use.
     *
     * @var string
     */
    protected $searchClassId = 'Solr';

    /**
     * Should we save searches to history?
     *
     * @var bool
     */
    protected $saveToHistory = true;

    /**
     * Should we log search statistics?
     *
     * @var bool
     */
    protected $logStatistics = true;

    /**
     * Should we remember the search for breadcrumb purposes?
     *
     * @var bool
     */
    protected $rememberSearch = true;

    /**
     * Constructor
     */
    public function __construct()
    {
        // Placeholder so child classes can call parent::__construct() in case
        // of future global behavior.
    }

    /**
     * Create a new ViewModel.
     *
     * @param array $params Parameters to pass to ViewModel constructor.
     *
     * @return ViewModel
     */
    protected function createViewModel($params = null)
    {
        $view = parent::createViewModel($params);
        $view->searchClassId = $this->searchClassId;
        return $view;
    }

    /**
     * Handle an advanced search
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function advancedAction()
    {
        $view = $this->createViewModel();
        $view->options = $this->getServiceLocator()
            ->get('VuFind\SearchOptionsPluginManager')->get($this->searchClassId);
        if ($view->options->getAdvancedSearchAction() === false) {
            throw new \Exception('Advanced search not supported.');
        }

        // Handle request to edit existing saved search:
        $view->saved = false;
        $searchId = $this->params()->fromQuery('edit', false);
        if ($searchId !== false) {
            $view->saved = $this->restoreAdvancedSearch($searchId);
        }

        // If we have default filters, set them up as a fake "saved" search
        // to properly populate special controls on the advanced screen.
        if (!$view->saved && count($view->options->getDefaultFilters()) > 0) {
            $view->saved = $this->getServiceLocator()
                ->get('VuFind\SearchResultsPluginManager')
                ->get($this->searchClassId);
            $view->saved->getParams()->initFromRequest(
                new \Zend\StdLib\Parameters([])
            );
        }

        return $view;
    }

    /**
     * Given a saved search ID, redirect the user to the appropriate place.
     *
     * @param int $id ID from search history
     *
     * @return mixed
     */
    protected function redirectToSavedSearch($id)
    {
        $table = $this->getTable('Search');
        $search = $table->getRowById($id);

        // Found, make sure the user has the rights to view this search
        $sessId = $this->getServiceLocator()->get('VuFind\SessionManager')->getId();
        $user = $this->getUser();
        $userId = $user ? $user->id : false;
        if ($search->session_id == $sessId || $search->user_id === $userId) {
            // They do, deminify it to a new object.
            $minSO = $search->getSearchObject();
            $savedSearch = $minSO->deminify($this->getResultsManager());

            // Now redirect to the URL associated with the saved search; this
            // simplifies problems caused by mixing different classes of search
            // object, and it also prevents the user from ever landing on a
            // "?saved=xxxx" URL, which may not persist beyond the current session.
            // (We want all searches to be persistent and bookmarkable).
            $details = $savedSearch->getOptions()->getSearchAction();
            $url = $this->url()->fromRoute($details);
            $url .= $savedSearch->getUrlQuery()->getParams(false);
            return $this->redirect()->toUrl($url);
        } else {
            // They don't
            // TODO : Error handling -
            //    User is trying to view a saved search from another session
            //    (deliberate or expired) or associated with another user.
            throw new \Exception("Attempt to access invalid search ID");
        }
    }

    /**
     * Is the result scroller active?
     *
     * @return bool
     */
    protected function resultScrollerActive()
    {
        // Disabled by default:
        return false;
    }

    /**
     * Store the URL of the provided search (if appropriate).
     *
     * @param \VuFind\Search\Base\Results $results Search results object
     *
     * @return void
     */
    protected function rememberSearch($results)
    {
        if ($this->rememberSearch) {
            $searchUrl = $this->url()->fromRoute(
                $results->getOptions()->getSearchAction()
            ) . $results->getUrlQuery()->getParams(false);
            $this->getSearchMemory()->rememberSearch($searchUrl);
        }
    }

    /**
     * Get active recommendation module settings
     *
     * @return array
     */
    protected function getActiveRecommendationSettings()
    {
        // Enable recommendations unless explicitly told to disable them:
        $all = ['top', 'side', 'noresults', 'bottom'];
        $noRecommend = $this->params()->fromQuery('noRecommend', false);
        if ($noRecommend === 1 || $noRecommend === '1'
            || $noRecommend === 'true' || $noRecommend === true
        ) {
            return [];
        } else if ($noRecommend === 0 || $noRecommend === '0'
            || $noRecommend === 'false' || $noRecommend === false
        ) {
            return $all;
        }
        return array_diff(
            $all, array_map('trim', explode(',', strtolower($noRecommend)))
        );
    }

    /**
     * Get a callback for setting up a search (or null if callback is unnecessary).
     *
     * @return mixed
     */
    protected function getSearchSetupCallback()
    {
        // Setup callback to attach listener if appropriate:
        $activeRecs = $this->getActiveRecommendationSettings();
        if (empty($activeRecs)) {
            return null;
        }

        $rManager = $this->getServiceLocator()->get('VuFind\RecommendPluginManager');

        // Special case: override recommend settings through parameter (used by
        // combined search)
        if ($override = $this->params()->fromQuery('recommendOverride')) {
            return function ($runner, $p, $searchId) use ($rManager, $override) {
                $listener = new RecommendListener($rManager, $searchId);
                $listener->setConfig($override);
                $listener->attach($runner->getEventManager()->getSharedManager());
            };
        }

        // Standard case: retrieve recommend settings from params object:
        return function ($runner, $params, $searchId) use ($rManager, $activeRecs) {
            $listener = new RecommendListener($rManager, $searchId);
            $config = [];
            $rawConfig = $params->getOptions()
                ->getRecommendationSettings($params->getSearchHandler());
            foreach ($rawConfig as $key => $value) {
                if (in_array($key, $activeRecs)) {
                    $config[$key] = $value;
                }
            }
            $listener->setConfig($config);
            $listener->attach($runner->getEventManager()->getSharedManager());
        };
    }

    /**
     * Send search results to results view
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function resultsAction()
    {
        $view = $this->createViewModel();

        // Handle saved search requests:
        $savedId = $this->params()->fromQuery('saved', false);
        if ($savedId !== false) {
            return $this->redirectToSavedSearch($savedId);
        }

        $runner = $this->getServiceLocator()->get('VuFind\SearchRunner');

        // Send both GET and POST variables to search class:
        $request = $this->getRequest()->getQuery()->toArray()
            + $this->getRequest()->getPost()->toArray();

        $view->results = $results = $runner->run(
            $request, $this->searchClassId, $this->getSearchSetupCallback()
        );
        $view->params = $results->getParams();

        // If we received an EmptySet back, that indicates that the real search
        // failed due to some kind of syntax error, and we should display a
        // warning to the user; otherwise, we should proceed with normal post-search
        // processing.
        if ($results instanceof \VuFind\Search\EmptySet\Results) {
            $view->parseError = true;
        } else {
            // If a "jumpto" parameter is set, deal with that now:
            if ($jump = $this->processJumpTo($results)) {
                return $jump;
            }

            // Remember the current URL as the last search.
            $this->rememberSearch($results);

            // Add to search history:
            if ($this->saveToHistory) {
                $user = $this->getUser();
                $sessId = $this->getServiceLocator()->get('VuFind\SessionManager')
                    ->getId();
                $history = $this->getTable('Search');
                $history->saveSearch(
                    $this->getResultsManager(), $results, $sessId,
                    $history->getSearches(
                        $sessId, isset($user->id) ? $user->id : null
                    )
                );
            }

            // Set up results scroller:
            if ($this->resultScrollerActive()) {
                $this->resultScroller()->init($results);
            }
        }

        // Save statistics:
        if ($this->logStatistics) {
            $this->getServiceLocator()->get('VuFind\SearchStats')
                ->log($results, $this->getRequest());
        }

        // Special case: If we're in RSS view, we need to render differently:
        if (isset($view->params) && $view->params->getView() == 'rss') {
            $response = $this->getResponse();
            $response->getHeaders()->addHeaderLine('Content-type', 'text/xml');
            $feed = $this->getViewRenderer()->plugin('resultfeed');
            $response->setContent($feed($view->results)->export('rss'));
            return $response;
        }

        // Search toolbar
        $config = $this->getServiceLocator()->get('VuFind\Config')->get('config');
        $view->showBulkOptions = isset($config->Site->showBulkOptions)
          && $config->Site->showBulkOptions;

        return $view;
    }

    /**
     * Process the jumpto parameter -- either redirect to a specific record and
     * return view model, or ignore the parameter and return false.
     *
     * @param \VuFind\Search\Base\Results $results Search results object.
     *
     * @return bool|\Zend\View\Model\ViewModel
     */
    protected function processJumpTo($results)
    {
        // Missing/invalid parameter?  Ignore it:
        $jumpto = $this->params()->fromQuery('jumpto');
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
        $details = $this->getRecordRouter()
            ->getTabRouteDetails($recordList[$jumpto - 1]);
        return $this->redirect()->toRoute($details['route'], $details['params']);
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
        // Look up search in database and fail if it is not found:
        $searchTable = $this->getTable('Search');
        $search = $searchTable->select(['id' => $searchId])->current();
        if (empty($search)) {
            $this->flashMessenger()->addMessage('advSearchError_notFound', 'error');
            return false;
        }

        // Fail if user has no permission to view this search:
        $user = $this->getUser();
        $sessId = $this->getServiceLocator()->get('VuFind\SessionManager')->getId();
        if ($search->session_id != $sessId && $search->user_id != $user->id) {
            $this->flashMessenger()->addMessage('advSearchError_noRights', 'error');
            return false;
        }

        // Restore the full search object:
        $minSO = $search->getSearchObject();
        $savedSearch = $minSO->deminify($this->getResultsManager());

        // Fail if this is not the right type of search:
        if ($savedSearch->getParams()->getSearchType() != 'advanced') {
            try {
                $savedSearch->getParams()->convertToAdvancedSearch();
            } catch (\Exception $ex) {
                $this->flashMessenger()
                    ->addMessage('advSearchError_notAdvanced', 'error');
                return false;
            }
        }

        // Activate facets so we get appropriate descriptions in the filter list:
        $savedSearch->getParams()->activateAllFacets('Advanced');

        // Make the object available to the view:
        return $savedSearch;
    }

    /**
     * Convenience method for accessing results
     *
     * @return \VuFind\Search\Results\PluginManager
     */
    protected function getResultsManager()
    {
        return $this->getServiceLocator()->get('VuFind\SearchResultsPluginManager');
    }

    /**
     * Get the current settings for the specified range facet, if it is set:
     *
     * @param array  $fields      Fields to check
     * @param string $type        Type of range to include in return value
     * @param object $savedSearch Saved search object (false if none)
     *
     * @return array
     */
    protected function getRangeSettings($fields, $type, $savedSearch = false)
    {
        $parts = [];

        foreach ($fields as $field) {
            // Default to blank strings:
            $from = $to = '';

            // Check to see if there is an existing range in the search object:
            if ($savedSearch) {
                $filters = $savedSearch->getParams()->getFilters();
                if (isset($filters[$field])) {
                    foreach ($filters[$field] as $current) {
                        if ($range = SolrUtils::parseRange($current)) {
                            $from = $range['from'] == '*' ? '' : $range['from'];
                            $to = $range['to'] == '*' ? '' : $range['to'];
                            $savedSearch->getParams()
                                ->removeFilter($field . ':' . $current);
                            break;
                        }
                    }
                }
            }

            // Send back the settings:
            $parts[] = [
                'field' => $field,
                'type' => $type,
                'values' => [$from, $to]
            ];
        }

        return $parts;
    }

    /**
     * Get the range facet configurations from the specified config section and
     * filter them appropriately.
     *
     * @param string $config  Name of config file
     * @param string $section Configuration section to check
     * @param array  $filter  Whitelist of fields to include (if empty, all
     * fields will be returned)
     *
     * @return array
     */
    protected function getRangeFieldList($config, $section, $filter)
    {
        $config = $this->getServiceLocator()->get('VuFind\Config')->get($config);
        $fields = isset($config->SpecialFacets->$section)
            ? $config->SpecialFacets->$section->toArray() : [];

        if (!empty($filter)) {
            $fields = array_intersect($fields, $filter);
        }

        return $fields;
    }

    /**
     * Get the current settings for the date range facets, if set:
     *
     * @param object $savedSearch Saved search object (false if none)
     * @param string $config      Name of config file
     * @param array  $filter      Whitelist of fields to include (if empty, all
     * fields will be returned)
     *
     * @return array
     */
    protected function getDateRangeSettings($savedSearch = false, $config = 'facets',
        $filter = []
    ) {
        $fields = $this->getRangeFieldList($config, 'dateRange', $filter);
        return $this->getRangeSettings($fields, 'date', $savedSearch);
    }

    /**
     * Get the current settings for the full date range facets, if set:
     *
     * @param object $savedSearch Saved search object (false if none)
     * @param string $config      Name of config file
     * @param array  $filter      Whitelist of fields to include (if empty, all
     * fields will be returned)
     *
     * @return array
     */
    protected function getFullDateRangeSettings($savedSearch = false,
        $config = 'facets', $filter = []
    ) {
        $fields = $this->getRangeFieldList($config, 'fullDateRange', $filter);
        return $this->getRangeSettings($fields, 'fulldate', $savedSearch);
    }

    /**
     * Get the current settings for the generic range facets, if set:
     *
     * @param object $savedSearch Saved search object (false if none)
     * @param string $config      Name of config file
     * @param array  $filter      Whitelist of fields to include (if empty, all
     * fields will be returned)
     *
     * @return array
     */
    protected function getGenericRangeSettings($savedSearch = false,
        $config = 'facets', $filter = []
    ) {
        $fields = $this->getRangeFieldList($config, 'genericRange', $filter);
        return $this->getRangeSettings($fields, 'generic', $savedSearch);
    }

    /**
     * Get the current settings for the numeric range facets, if set:
     *
     * @param object $savedSearch Saved search object (false if none)
     * @param string $config      Name of config file
     * @param array  $filter      Whitelist of fields to include (if empty, all
     * fields will be returned)
     *
     * @return array
     */
    protected function getNumericRangeSettings($savedSearch = false,
        $config = 'facets', $filter = []
    ) {
        $fields = $this->getRangeFieldList($config, 'numericRange', $filter);
        return $this->getRangeSettings($fields, 'numeric', $savedSearch);
    }

    /**
     * Get all active range facets:
     *
     * @param array  $specialFacets Special facet setting (in parsed format)
     * @param object $savedSearch   Saved search object (false if none)
     * @param string $config        Name of config file
     *
     * @return array
     */
    protected function getAllRangeSettings($specialFacets, $savedSearch = false,
        $config = 'facets'
    ) {
        $result = [];
        if (isset($specialFacets['daterange'])) {
            $dates = $this->getDateRangeSettings(
                $savedSearch, $config, $specialFacets['daterange']
            );
            $result = array_merge($result, $dates);
        }
        if (isset($specialFacets['fulldaterange'])) {
            $fulldates = $this->getFullDateRangeSettings(
                $savedSearch, $config, $specialFacets['fulldaterange']
            );
            $result = array_merge($result, $fulldates);
        }
        if (isset($specialFacets['genericrange'])) {
            $generic = $this->getGenericRangeSettings(
                $savedSearch, $config, $specialFacets['genericrange']
            );
            $result = array_merge($result, $generic);
        }
        if (isset($specialFacets['numericrange'])) {
            $numeric = $this->getNumericRangeSettings(
                $savedSearch, $config, $specialFacets['numericrange']
            );
            $result = array_merge($result, $numeric);
        }
        return $result;
    }

    /**
     * Parse the "special facets" setting.
     *
     * @param string $specialFacets Unparsed string
     *
     * @return array
     */
    protected function parseSpecialFacetsSetting($specialFacets)
    {
        // Parse the special facets into a more useful format:
        $parsed = [];
        foreach (explode(',', $specialFacets) as $current) {
            $parts = explode(':', $current);
            $key = array_shift($parts);
            $parsed[$key] = $parts;
        }
        return $parsed;
    }

    /**
     * Process the checkbox setting from special facets.
     *
     * @param array  $params      Parameters to the checkbox setting
     * @param object $savedSearch Saved search object (false if none)
     *
     * @return array
     */
    protected function processAdvancedCheckboxes($params, $savedSearch = false)
    {
        // Set defaults for missing parameters:
        $config = isset($params[0]) ? $params[0] : 'facets';
        $section = isset($params[1]) ? $params[1] : 'CheckboxFacets';

        // Load config file:
        $config = $this->getServiceLocator()->get('VuFind\Config')->get($config);

        // Process checkbox settings in config:
        if (substr($section, 0, 1) == '~') {        // reverse flag
            $section = substr($section, 1);
            $flipCheckboxes = true;
        }
        $checkboxFacets = ($section && isset($config->$section))
            ? $config->$section->toArray() : [];
        if (isset($flipCheckboxes) && $flipCheckboxes) {
            $checkboxFacets = array_flip($checkboxFacets);
        }

        // Reformat for convenience:
        $formatted = [];
        foreach ($checkboxFacets as $filter => $desc) {
            $current = compact('desc', 'filter');
            $current['selected']
                = $savedSearch && $savedSearch->getParams()->hasFilter($filter);
            // We don't want to double-display checkboxes on advanced search, so
            // if they are checked, we should remove them from the object to
            // prevent display in the "other filters" area.
            if ($current['selected']) {
                $savedSearch->getParams()->removeFilter($filter);
            }
            $formatted[] = $current;
        }

        return $formatted;
    }
}