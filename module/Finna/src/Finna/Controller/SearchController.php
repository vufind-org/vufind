<?php
/**
 * Default Controller
 *
 * PHP version 7
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Kalle Pyykkönen <kalle.pyykkonen@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
namespace Finna\Controller;

use Finna\Search\Solr\Options;
use VuFindCode\ISBN;

/**
 * Redirects the user to the appropriate default VuFind action.
 *
 * @category VuFind
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Kalle Pyykkönen <kalle.pyykkonen@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class SearchController extends \VuFind\Controller\SearchController
{
    use FinnaSearchControllerTrait;
    use CatalogLoginTrait;

    /**
     * Handle an advanced search
     *
     * @return mixed
     */
    public function advancedAction()
    {
        $view = parent::advancedAction();

        $config = $this->getConfig();
        $ticks = [-1000, 0, 900, 1800, 1900];
        if (!empty($config->Site->advSearchYearScale)) {
            $ticks = array_map(
                'trim', explode(',', $config->Site->advSearchYearScale)
            );
        }
        $rangeEnd = date('Y', strtotime('+1 year'));

        $results = $this->getResultsManager()->get($this->searchClassId);
        $params = $results->getParams();

        $range = [
            'type' => 'date',
            'field' => $params->getDateRangeSearchField()
        ];

        if ($view->saved
            && $filter = $view->saved->getParams()->getDateRangeFilter()
        ) {
            $filter = $params->parseDateRangeFilter($filter['value']);
            if (isset($filter['from']) && isset($filter['to'])) {
                $range['values'] = [$filter['from'], $filter['to']];
                $range['rangeType'] = $filter['type'];
                if ($ticks[0] > $filter['from']) {
                    $ticks[0] = $filter['from'];
                }
                if ($rangeEnd < $filter['to']) {
                    $rangeEnd = $filter['to'];
                }
            } else {
                $range['values'] = [null, null];
            }
        }
        array_push($ticks, $rangeEnd);
        $range['ticks'] = $ticks;

        $positions = [];
        $position = 0;
        for ($i = 0; $i < count($ticks); $i++) {
            $positions[] = floor($i * 100 / (count($ticks) - 1));
        }
        $range['ticks_positions'] = $positions;

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
     * Redirection for VuFind 1 DualResults action
     *
     * @return mixed
     */
    public function dualResultsAction()
    {
        return $this->forwardTo('Combined', 'Results');
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
        $hiddenFilters = $this->getRequest()->getQuery(
            'vufind_hidden_filters',
            $this->getRequest()->getPost('vufind_hidden_filters')
        );
        $results = $this->processOpenURL($params, $hiddenFilters);

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
     * Blended action.
     *
     * @return mixed
     */
    public function blendedAction()
    {
        $saveId = $this->searchClassId;
        try {
            $this->searchClassId = 'Blender';
            $view = parent::resultsAction();
        } catch (\Exception $e) {
            $this->searchClassId = $saveId;
            throw $e;
        }
        $this->searchClassId = $saveId;
        return $view;
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
        $view->fromStreetSearch = $this->getRequest()->getQuery()
            ->get('streetsearch', false);
        return $view;
    }

    /**
     * StreetSearch action.
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function streetSearchAction()
    {
        return $this->createViewModel();
    }

    /**
     * StreetSearch action alias.
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function streetAction()
    {
        return $this->forwardTo('Search', 'StreetSearch');
    }

    /**
     * Returns a list of all items associated with one facet for the lightbox
     *
     * Parameters:
     * facet        The facet to retrieve
     * searchParams Facet search params from $results->getUrlQuery()->getParams()
     *
     * @return mixed
     */
    public function facetListAction()
    {
        $authorityHelper
            = $this->serviceLocator->get(\Finna\Search\Solr\AuthorityHelper::class);

        $view = parent::facetListAction();

        // Convert author-id facet labels to readable names
        $view->data = $authorityHelper->formatFacetList($view->facet, $view->data);
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
        $config = $this->serviceLocator->get(\VuFind\Config\PluginManager::class)
            ->get('browse');
        if (!isset($config['General'][$type]) || !$config['General'][$type]) {
            throw new \Exception("Browse action $type is disabled");
        }

        if (!isset($config[$type])) {
            throw new \Exception("Missing configuration for browse action: $type");
        }

        // Preserve last result view
        $config = $config[$type];
        $query = $this->getRequest()->getQuery();
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

        // Get rid of the hidden filters from search and url params so that they
        // they don't linger around and cause trouble with the mechanism that
        // adds hidden filters to the searchbox for search tabs.
        $view->results->getParams()->clearHiddenFilters();
        $query->set('hiddenFilters', []);

        // Update last search URL
        $view->results->getParams()->getOptions()
            ->setBrowseAction("browse-$type");
        $this->getSearchMemory()->forgetSearch();
        $this->rememberSearch($view->results);

        $view->results->getParams()->setView('condensed');
        $view->results->getParams()->getQuery()->setHandler($queryType);

        return $view;
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
                $isbn = $request['rft_isbn'] ?? '';
                if (isset($request['rft_btitle'])) {
                    $title = $request['rft_btitle'];
                } elseif (isset($request['rft_title'])) {
                    $title = $request['rft_title'];
                }
            } else {
                // Journal / Article / something
                $journal = true;
                $eissn = $request['rft_eissn'] ?? '';
                $atitle = $request['rft_atitle'] ?? '';
                if (isset($request['rft_jtitle'])) {
                    $title = $request['rft_jtitle'];
                } elseif (isset($request['rft_title'])) {
                    $title = $request['rft_title'];
                }
            }
            if (isset($request['rft_aulast'])) {
                $author = $request['rft_aulast'];
            }
            if (isset($request['rft_aufirst'])) {
                $author .= ' ' . $request['rft_aufirst'];
            } elseif (isset($request['rft_auinit'])) {
                $author .= ' ' . $request['rft_auinit'];
            }
            $issn = $request['rft_issn'] ?? '';
            $date = $request['rft_date'] ?? '';
            $volume = $request['rft_volume'] ?? '';
            $issue = $request['rft_issue'] ?? '';
            $spage = $request['rft_spage'] ?? '';
        } else {
            // OpenURL 0.1
            $issn = $request['issn'] ?? '';
            $date = $request['date'] ?? '';
            $volume = $request['volume'] ?? '';
            $issue = $request['issue'] ?? '';
            $spage = $request['spage'] ?? '';
            $isbn = $request['isbn'] ?? '';
            $atitle = $request['atitle'] ?? '';
            if (isset($request['jtitle'])) {
                $title = $request['jtitle'];
            } elseif (isset($request['btitle'])) {
                $title = $request['btitle'];
            } elseif (isset($request['title'])) {
                $title = $request['title'];
            }
            if (isset($request['aulast'])) {
                $author = $request['aulast'];
            }
            if (isset($request['aufirst'])) {
                $author .= ' ' . $request['aufirst'];
            } elseif (isset($request['auinit'])) {
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
     * @param array $params        Referent params
     * @param array $hiddenFilters Optional hidden filters
     *
     * @return object Search object
     */
    protected function processOpenURL($params, $hiddenFilters = [])
    {
        $runner = $this->serviceLocator->get(\VuFind\Search\SearchRunner::class);
        $results = false;

        // Journal first..
        if (!$params['eissn']
            || !($results = $this->trySearch(
                $runner, ['ISN' => $params['eissn']], $hiddenFilters
            ))
        ) {
            if ($params['issn']) {
                $results = $this->trySearch(
                    $runner, ['ISN' => $params['issn']], $hiddenFilters
                );
            }
        }
        if (isset($results) && $results) {
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
                if ($articles = $this->trySearch($runner, $query, $hiddenFilters)) {
                    return $articles;
                }

                // Broaden the search until we find something or run out of
                // options
                foreach (
                    ['container_start_page', 'issue', 'volume'] as $param
                ) {
                    if (isset($query[$param])) {
                        unset($query[$param]);
                        $articles = $this->trySearch(
                            $runner, $query, $hiddenFilters
                        );
                        if ($articles) {
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
                $runner, ['ISN' => $params['isbn']], $hiddenFilters
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
                $results = $this->trySearch($runner, $query, $hiddenFilters);
            }
        }

        if ($results === false) {
            $results = $this->trySearch(
                $runner, ['id' => 'notfound'], $hiddenFilters, true
            );
        }

        return $results;
    }

    /**
     * Try a search and return results if found
     *
     * @param \VuFind\Search\SearchRunner $runner             Search runner
     * @param array                       $params             Search params
     * @param array                       $hiddenFilters      Optional hidden filters
     * @param bool                        $returnEmptyResults Whether to return empty
     * results object instead of boolean false
     *
     * @return bool|\VuFind\Search\Base\Results
     */
    protected function trySearch(\VuFind\Search\SearchRunner $runner, $params,
        $hiddenFilters = [], $returnEmptyResults = false
    ) {
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
        if ($hiddenFilters) {
            $query['hiddenFilters'] = $hiddenFilters;
        }

        $results = $runner->run($query);
        if ($results->getResultTotal() > 0 || $returnEmptyResults) {
            return $results;
        }
        return false;
    }

    /**
     * Open map facet modal
     *
     * @return \VuFind\Controller\ViewModel
     */
    public function mapFacetAction()
    {
        $results = $this->getResultsManager()->get($this->searchClassId);
        $params = $results->getParams();
        $params->initFromRequest($this->getRequest()->getQuery());

        $view = $this->createViewModel(
            [
                'results' => $results,
                'geoFilters' =>
                $params->getGeographicFilters($params->getFilterList())
            ]
        );
        $view->setTemplate('Recommend/SideFacets/map-facet-modal');

        return $view;
    }
}
