<?php
/**
 * Default Controller
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015.
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
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\Controller;

use Finna\Search\Solr\Options,
    VuFindCode\ISBN;

/**
 * Redirects the user to the appropriate default VuFind action.
 *
 * @category VuFind2
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class SearchController extends \VuFind\Controller\SearchController
{
    use SearchControllerTrait;

    /**
     * Handle an advanced search
     *
     * @return mixed
     */
    public function advancedAction()
    {
        $view = parent::advancedAction();

        $range = [
            'type' => 'date',
            'field' => \Finna\Search\Solr\Params::SPATIAL_DATERANGE_FIELD,
        ];

        if ($view->saved
            && $filter = $view->saved->getParams()->getSpatialDateRangeFilter()
        ) {
            if (isset($filter['from']) && isset($filter['to'])) {
                $range['values'] = [$filter['from'], $filter['to']];
                $range['rangeType'] = $filter['type'];
            } else {
                $range['values'] = [null, null];
            }
        }

        $view->daterange = [$range];
        return $view;
    }

    /**
     * Browse databases.
     *
     * @return mixed
     */
    public function databaseAction()
    {
        return $this->browse('Database');
    }

    /**
     * Sends search history, alert schedules for saved searches and user's
     * email address to view.
     *
     * @return mixed
     */
    public function historyAction()
    {
        $view = parent::historyAction();
        $user = $this->getUser();
        if ($user) {
            $view->alertemail = $user->email;
        }

        // Retrieve saved searches
        $search = $this->getTable('Search');
        $savedsearches
            = $search->getSavedSearches(is_object($user) ? $user->id : null);

        $schedule = [];
        foreach ($savedsearches as $current) {
            $minSO = $current->getSearchObject(true);
            // Only Solr searches allowed
            if ($minSO->cl !== 'Solr') {
                continue;
            }
            $minSO = $minSO->deminify($this->getResultsManager());
            $schedule[$minSO->getSearchId()] = $current->finna_schedule;
        }
        $view->schedule = $schedule;
        return $view;
    }

    /**
     * Browse journals.
     *
     * @return mixed
     */
    public function journalAction()
    {
        return $this->browse('Journal');
    }

    /**
     * Resolve an OpenURL.
     *
     * @return mixed
     */
    public function openUrlAction()
    {
        $params = $this->parseOpenURL();
        $results = $this->processOpenURL($params);

        // If we were asked to return just information whether something was found,
        // do it here
        if ($this->params()->fromQuery('vufind_response_type') == 'resultcount') {
            $response = $this->getResponse();
            $response->setContent($results->getResultTotal());
            return $response;
        }

        // Otherwise redirect to results
        $url = $this->url()->fromRoute($results->getOptions()->getSearchAction())
            . $results->getUrlQuery()->getParams(false);
        return $this->redirect()->toUrl($url);
    }

    /**
     * Results action.
     *
     * @return mixed
     */
    public function resultsAction()
    {
        if ($this->getRequest()->getQuery()->get('combined')) {
            $this->saveToHistory = false;
        }

        $this->initCombinedViewFilters();
        $view = parent::resultsAction();
        $view->browse = false;
        $this->initSavedTabs();
        return $view;
    }

    /**
     * Handler for database and journal browse actions.
     *
     * @param string $type Browse type
     *
     * @return mixed
     */
    protected function browse($type)
    {
        $config = $this->getServiceLocator()->get('VuFind\Config')->get('browse');
        if (!isset($config['General'][$type]) || !$config['General'][$type]) {
            throw new \Exception("Browse action $type is disabled");
        }

        if (!isset($config[$type])) {
            throw new \Exception("Missing configuration for browse action: $type");
        }

        // Preserve last result view
        $configLoader = $this->getServiceLocator()->get('VuFind\Config');
        $options = new Options($configLoader);
        $lastView = $options->getLastView();

        try {
            $config = $config[$type];
            $query = $this->getRequest()->getQuery();
            $query->set('view', 'condensed');
            if (!$query->get('limit')) {
                $query->set('limit', $config['resultLimit'] ?: 100);
            }
            if (!$query->get('sort')) {
                $query->set('sort', $config['sort'] ?: 'title');
            }
            if (!$query->get('type')) {
                $query->set('type', $config['type'] ?: 'Title');
            }
            $queryType = $query->get('type');

            $query->set('hiddenFilters', $config['filter']->toArray() ?: []);
            $query->set(
                'recommendOverride',
                ['side' => ["SideFacets:Browse{$type}:CheckboxFacets:facets-browse"]]
            );

            $view = $this->forwardTo('Search', 'Results');

            $view->overrideTitle = "browse_extended_$type";
            $type = strtolower($type);
            $view->browse = $type;
            $view->defaultBrowseHandler = $config['type'];

            $view->results->getParams()->setBrowseHandler($queryType);

            // Update last search URL
            $view->results->getParams()->getOptions()
                ->setBrowseAction("browse-$type");
            $this->getSearchMemory()->forgetSearch();
            $this->rememberSearch($view->results);

            $view->results->getParams()->getQuery()->setHandler($queryType);

            // Restore last result view
            $view->results->getOptions()->rememberLastView($lastView);

            return $view;
        } catch (\Exception $e) {
            $options->rememberLastView($lastView);
        }
    }

    /**
     * Parse OpenURL and return a keyed array
     *
     * @return array
     */
    protected function parseOpenURL()
    {
        $title = '';
        $atitle = '';
        $author = '';
        $isbn = '';
        $issn = '';
        $eissn = '';
        $date = '';
        $volume = '';
        $issue = '';
        $spage = '';
        $journal = false;

        $request = $this->getRequest()->getQuery()->toArray()
            + $this->getRequest()->getPost()->toArray();

        if (isset($request['url_ver']) && $request['url_ver'] == 'Z39.88-2004') {
            // Parse OpenURL 1.0
            if (isset($request['rft_val_fmt'])
                && $request['rft_val_fmt'] == 'info:ofi/fmt:kev:mtx:book'
            ) {
                // Book format
                $isbn = isset($request['rft_isbn']) ? $request['rft_isbn'] : '';
                if (isset($request['rft_btitle'])) {
                    $title = $request['rft_btitle'];
                } else if (isset($request['rft_title'])) {
                    $title = $request['rft_title'];
                }
            } else {
                // Journal / Article / something
                $journal = true;
                $eissn = isset($request['rft_eissn']) ? $request['rft_eissn'] : '';
                $atitle = isset($request['rft_atitle'])
                    ? $request['rft_atitle'] : '';
                if (isset($request['rft_jtitle'])) {
                    $title = $request['rft_jtitle'];
                } else if (isset($request['rft_title'])) {
                    $title = $request['rft_title'];
                }
            }
            if (isset($request['rft_aulast'])) {
                $author = $request['rft_aulast'];
            }
            if (isset($request['rft_aufirst'])) {
                $author .= ' ' . $request['rft_aufirst'];
            } else if (isset($request['rft_auinit'])) {
                $author .= ' ' . $request['rft_auinit'];
            }
            $issn = isset($request['rft_issn']) ? $request['rft_issn'] : '';
            $date = isset($request['rft_date']) ? $request['rft_date'] : '';
            $volume = isset($request['rft_volume']) ? $request['rft_volume'] : '';
            $issue = isset($request['rft_issue']) ? $request['rft_issue'] : '';
            $spage = isset($request['rft_spage']) ? $request['rft_spage'] : '';
        } else {
            // OpenURL 0.1
            $issn = isset($request['issn']) ? $request['issn'] : '';
            $date = isset($request['date']) ? $request['date'] : '';
            $volume = isset($request['volume']) ? $request['volume'] : '';
            $issue = isset($request['issue']) ? $request['issue'] : '';
            $spage = isset($request['spage']) ? $request['spage'] : '';
            $isbn = isset($request['isbn']) ? $request['isbn'] : '';
            $atitle = isset($request['atitle']) ? $request['atitle'] : '';
            if (isset($request['jtitle'])) {
                $title = $request['jtitle'];
            } else if (isset($request['btitle'])) {
                $title = $request['btitle'];
            } else if (isset($request['title'])) {
                $title = $request['title'];
            }
            if (isset($request['aulast'])) {
                $author = $request['aulast'];
            }
            if (isset($request['aufirst'])) {
                $author .= ' ' . $request['aufirst'];
            } else if (isset($request['auinit'])) {
                $author .= ' ' . $request['auinit'];
            }
        }

        if (ISBN::isValidISBN10($isbn)
            || ISBN::isValidISBN13($isbn)
        ) {
            $isbnObj = new ISBN($isbn);
            $isbn = $isbnObj->get13();
        }

        return compact(
            'journal', 'atitle', 'title', 'author', 'isbn', 'issn', 'eissn', 'date',
            'volume', 'issue', 'spage'
        );
    }

    /**
     * Process the OpenURL params and try to find record(s) with them
     *
     * @param array $params Referent params
     *
     * @return object Search object
     */
    protected function processOpenURL($params)
    {
        $runner = $this->getServiceLocator()->get('VuFind\SearchRunner');

        // Journal first..
        if (!$params['eissn']
            || !($results = $this->trySearch(
                $runner, ['ISN' => $params['eissn']]
            ))
        ) {
            if ($params['issn']) {
                $results = $this->trySearch(
                    $runner, ['ISN' => $params['issn']]
                );
            }
        }
        if ($results) {
            if ($params['date'] || $params['volume'] || $params['issue']
                || $params['spage'] || $params['atitle']
            ) {
                // Ok, we found a journal. See if we can find an article too.
                $query = [];

                $ids = [];
                foreach ($results->getResults() as $record) {
                    $doc = $record->getRawData();
                    if (isset($doc['local_ids_str_mv'])) {
                        $ids = array_merge($ids, $doc['local_ids_str_mv']);
                    }
                    $ids[] = $doc['id'];
                    // Take only first 20 IDs or so
                    if (count($ids) >= 20) {
                        break;
                    }
                }
                $query['hierarchy_parent_id'] = $ids;

                if ($params['date']) {
                    $query['publishDate'] = $params['date'];
                }
                if ($params['volume']) {
                    $query['container_volume'] = $params['volume'];
                }
                if ($params['issue']) {
                    $query['container_issue'] = $params['issue'];
                }
                if ($params['spage']) {
                    $query['container_start_page'] = $params['spage'];
                }
                if ($params['atitle']) {
                    $query['Title'] = $params['atitle'];
                }
                if ($articles = $this->trySearch($runner, $query)) {
                    return $articles;
                }

                // Broaden the search until we find something or run out of
                // options
                foreach (
                    ['container_start_page', 'issue', 'volume'] as $param
                ) {
                    if (isset($query[$param])) {
                        unset($query[$param]);
                        if ($articles = $this->trySearch($runner, $query)) {
                            return $articles;
                        }
                    }
                }
            }
            // No article, return the journal results
            return $results;
        }

        // Try to find a book or something
        if (!$params['isbn']
            || !($results = $this->trySearch(
                $runner, ['ISN' => $params['isbn']]
            ))
        ) {
            $query = [];
            if ($params['title']) {
                $query['Title'] = $params['title'];
            }
            if ($params['author']) {
                $query['Author'] = $params['author'];
            }
            if ($query) {
                $results = $this->trySearch($runner, $query);
            } else {
                $results = $this->trySearch($runner, ['id' => 'null']);
            }
        }

        return $results;
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
        $savedSearch = parent::restoreAdvancedSearch($searchId);
        if ($savedSearch) {
            if ($filter = $savedSearch->getParams()->getSpatialDateRangeFilter(true)
            ) {
                $req = new \Zend\Stdlib\Parameters();
                $req->set(
                    'filter',
                    [$filter['field'] . ':"' . $filter['value'] . '"']
                );
                if (isset($filter['type'])) {
                    $req->set('search_sdaterange_mvtype', $filter['type']);
                }
                $savedSearch->getParams()->initSpatialDateRangeFilter($req);
            }
        }
        return $savedSearch;
    }

    /**
     * Try a search and return results if found
     *
     * @param \VuFind\Search\SearchRunner $runner Search runner
     * @param array                       $params Search params
     *
     * @return bool|array Results object if records found, otherwise false
     */
    protected function trySearch(\VuFind\Search\SearchRunner $runner, $params)
    {
        $mapFunc = function ($val) {
            return addcslashes($val, '"');
        };

        $query = ['join' => 'AND'];
        $i = 0;
        foreach ($params as $key => $param) {
            $query["type$i"][] = $key;
            $query["bool$i"] = ['AND'];
            if (is_array($param)) {
                $imploded = implode('" OR "', array_map($mapFunc, $param));
                $query["lookfor$i"][] = "\"$imploded\"";
            } else {
                if (strstr($param, ' ')) {
                    $param = "($param)";
                }
                $query["lookfor$i"][] = addcslashes($param, '"');
            }
            ++$i;
        }

        $results = $runner->run($query);
        if ($results->getResultTotal() > 0) {
            return $results;
        }
        return false;
    }
}
