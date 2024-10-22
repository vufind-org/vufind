<?php

/**
 * Browse Module Controller
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2011.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.    See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */

namespace VuFind\Controller;

use Laminas\Config\Config;
use Laminas\ServiceManager\ServiceLocatorInterface;
use VuFind\Exception\Forbidden as ForbiddenException;
use VuFind\Tags\TagsService;

use function array_slice;
use function in_array;

/**
 * BrowseController Class
 *
 * Controls the alphabetical browsing feature
 *
 * @category VuFind
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class BrowseController extends AbstractBase implements
    \VuFind\I18n\HasSorterInterface
{
    use \VuFind\I18n\HasSorterTrait;

    /**
     * VuFind configuration
     *
     * @var \Laminas\Config\Config
     */
    protected $config;

    /**
     * Current browse mode
     *
     * @var string
     */
    protected $currentAction = null;

    /**
     * Browse options disabled in configuration
     *
     * @var array
     */
    protected $disabledFacets;

    /**
     * Constructor
     *
     * @param ServiceLocatorInterface $sm     Service manager
     * @param Config                  $config VuFind configuration
     */
    public function __construct(ServiceLocatorInterface $sm, Config $config)
    {
        $this->config = $config;

        $this->disabledFacets = [];
        foreach ($this->config->Browse as $key => $setting) {
            if ($setting == false) {
                $this->disabledFacets[] = $key;
            }
        }
        $this->setSorter($sm->get(\VuFind\I18n\Sorter::class));
        parent::__construct($sm);
    }

    /**
     * Set the name of the current action.
     *
     * @param string $name Name of the current action
     *
     * @return void
     */
    protected function setCurrentAction($name)
    {
        $this->currentAction = $name;
    }

    /**
     * Get the name of the current action.
     *
     * @return string
     */
    protected function getCurrentAction()
    {
        return $this->currentAction;
    }

    /**
     * Determine which browse options to display, and in which order. Returns an
     * array of browse options in the configured order.
     *
     * @return array
     */
    protected function getActiveBrowseOptions()
    {
        // Get a list of all of the options mentioned in config.ini:
        $browseConfig = $this->config->Browse->toArray();
        $configuredOptions = array_keys($browseConfig);

        // This is a list of all available browse options:
        $allOptions = [
            'tag', 'dewey', 'lcc', 'author', 'topic', 'genre', 'region', 'era',
        ];

        // By default, all options except dewey are turned on if omitted from config:
        $defaultOptions = array_diff($allOptions, ['dewey']);

        // This is a callback function for array_filter, which will filter out any
        // settings set to false in config.ini:
        $filter = function ($option) use ($browseConfig) {
            return (bool)($browseConfig[$option] ?? false);
        };

        // The active options are a list of configured settings set to true in
        // config.ini, merged with any default options that were not configured in
        // config.ini at all:
        return array_merge(
            array_filter(array_intersect($configuredOptions, $allOptions), $filter),
            array_diff($defaultOptions, $configuredOptions)
        );
    }

    /**
     * Given a list of active options, format them into details for the view.
     *
     * @return array
     */
    protected function buildBrowseOptions()
    {
        // Initialize the array of top-level browse options.
        $browseOptions = [];

        $activeOptions = $this->getActiveBrowseOptions();
        foreach ($activeOptions as $option) {
            switch ($option) {
                case 'dewey':
                    $deweyLabel = in_array('lcc', $activeOptions)
                        ? 'browse_dewey' : 'Call Number';
                    $browseOptions[] = $this
                        ->buildBrowseOption('Dewey', $deweyLabel);
                    break;
                case 'lcc':
                    $lccLabel = in_array('dewey', $activeOptions)
                        ? 'browse_lcc' : 'Call Number';
                    $browseOptions[] = $this->buildBrowseOption('LCC', $lccLabel);
                    break;
                case 'tag':
                    if ($this->tagsEnabled()) {
                        $browseOptions[] = $this->buildBrowseOption('Tag', 'Tag');
                    }
                    break;
                default:
                    $current = ucwords($option);
                    $browseOptions[] = $this->buildBrowseOption($current, $current);
                    break;
            }
        }

        return $browseOptions;
    }

    /**
     * Create a new ViewModel.
     *
     * @param array $params Parameters to pass to ViewModel constructor.
     *
     * @return \Laminas\View\Model\ViewModel
     */
    protected function createViewModel($params = null)
    {
        $view = parent::createViewModel($params);

        // Set the current action.
        $currentAction = $this->getCurrentAction();
        if (!empty($currentAction)) {
            $view->currentAction = $currentAction;
        }

        // CARRY
        if ($findby = $this->params()->fromQuery('findby')) {
            $view->findby = $findby;
        }
        if ($query = $this->params()->fromQuery('query')) {
            $view->query = $query;
        }
        if ($category = $this->params()->fromQuery('category')) {
            $view->category = $category;
        }
        $view->browseOptions = $this->buildBrowseOptions();

        return $view;
    }

    /**
     * Build an array containing options describing a top-level Browse option.
     *
     * @param string $action      The name of the Action for this option
     * @param string $description A description of this Browse option
     *
     * @return array              The Browse option array
     */
    protected function buildBrowseOption($action, $description)
    {
        return ['action' => $action, 'description' => $description];
    }

    /**
     * Gathers data for the view of the AlphaBrowser and does some initialization
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function homeAction()
    {
        $this->setCurrentAction('Home');
        return $this->createViewModel();
    }

    /**
     * Perform the search
     *
     * @param \Laminas\View\Model\ViewModel $view View model to modify
     *
     * @return \Laminas\View\Model\ViewModel
     */
    protected function performSearch($view)
    {
        // Remove disabled facets
        $facets = $view->categoryList;
        foreach ($this->disabledFacets as $facet) {
            unset($facets[$facet]);
        }
        $view->categoryList = $facets;

        // SEARCH (Tag does its own search)
        if (
            $this->params()->fromQuery('query')
            && $this->getCurrentAction() != 'Tag'
        ) {
            $results = $this->getFacetList(
                $this->params()->fromQuery('facet_field'),
                $this->params()->fromQuery('query_field'),
                'count',
                $this->params()->fromQuery('query')
            );
            $resultList = [];
            foreach ($results as $result) {
                $resultList[] = [
                    'displayText' => $result['displayText'],
                    'value' => $result['value'],
                    'count' => $result['count'],
                ];
            }
            // Don't make a second filter if it would be the same facet
            $view->paramTitle
                = ($this->params()->fromQuery('query_field') != $this->getCategory())
                ? 'filter[]=' . $this->params()->fromQuery('query_field') . ':'
                    . urlencode($this->params()->fromQuery('query')) . '&'
                : '';
            switch ($this->getCurrentAction()) {
                case 'LCC':
                    $view->paramTitle .= 'filter[]=callnumber-subject:';
                    break;
                case 'Dewey':
                    $view->paramTitle .= 'filter[]=dewey-ones:';
                    break;
                default:
                    $view->paramTitle .= 'filter[]=' . $this->getCategory() . ':';
            }
            $view->paramTitle = str_replace(
                '+AND+',
                '&filter[]=',
                $view->paramTitle
            );
            $view->resultList = $resultList;
        }

        $view->setTemplate('browse/home');
        return $view;
    }

    /**
     * Browse tags
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function tagAction()
    {
        if (!$this->tagsEnabled()) {
            throw new ForbiddenException('Tags disabled.');
        }

        $this->setCurrentAction('Tag');
        $view = $this->createViewModel();

        $view->categoryList = [
            'alphabetical' => 'By Alphabetical',
            'popularity'   => 'By Popularity',
            'recent'       => 'By Recent',
        ];

        if ($this->params()->fromQuery('findby')) {
            $params = $this->getRequest()->getQuery()->toArray();
            $tagsService = $this->getService(TagsService::class);
            // Special case -- display alphabet selection if necessary:
            if ($params['findby'] == 'alphabetical') {
                $legalLetters = $this->getAlphabetList();
                $view->secondaryList = $legalLetters;
                // Only display tag list when a valid letter is selected:
                if (isset($params['query'])) {
                    // Note -- this does not need to be escaped because
                    // $params['query'] has already been validated against
                    // the getAlphabetList() method below!
                    $tags = $tagsService->getNonListTagsFuzzilyMatchingString($params['query']);
                    $tagList = [];
                    foreach ($tags as $tag) {
                        if ($tag['cnt'] > 0) {
                            $tagList[] = [
                                'displayText' => $tag['tag'],
                                'value' => $tag['tag'],
                                'count' => $tag['cnt'],
                            ];
                        }
                    }
                    $view->resultList = array_slice(
                        $tagList,
                        0,
                        $this->config->Browse->result_limit
                    );
                }
            } else {
                // Default case: always display tag list for non-alphabetical modes:
                $tagList = $tagsService->getTagBrowseList(
                    $params['findby'],
                    (int)($this->config->Browse->result_limit ?? 100)
                );
                $resultList = [];
                foreach ($tagList as $i => $tag) {
                    $resultList[$i] = [
                        'displayText' => $tag['tag'],
                        'value' => $tag['tag'],
                        'count'    => $tag['cnt'],
                    ];
                }
                $view->resultList = $resultList;
            }
            $view->paramTitle = 'lookfor=';
            $view->searchParams = [];
        }

        return $this->performSearch($view);
    }

    /**
     * Browse LCC
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function lccAction()
    {
        $this->setCurrentAction('LCC');
        $view = $this->createViewModel();
        [$view->filter, $view->secondaryList] = $this->getSecondaryList('lcc');
        $view->secondaryParams = [
            'query_field' => 'callnumber-first',
            'facet_field' => 'callnumber-subject',
        ];
        $view->searchParams = ['sort' => 'callnumber-sort'];
        return $this->performSearch($view);
    }

    /**
     * Browse Dewey
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function deweyAction()
    {
        $this->setCurrentAction('Dewey');
        $view = $this->createViewModel();
        [$view->filter, $hundredsList] = $this->getSecondaryList('dewey');
        $categoryList = [];
        foreach ($hundredsList as $dewey) {
            $categoryList[$dewey['value']] = [
                'text' => $dewey['displayText'],
                'count' => $dewey['count'],
            ];
        }
        $view->categoryList = $categoryList;
        $view->dewey_flag = 1;
        if ($this->params()->fromQuery('findby')) {
            $secondaryList = $this->quoteValues(
                $this->getFacetList(
                    'dewey-tens',
                    'dewey-hundreds',
                    'count',
                    $this->params()->fromQuery('findby')
                )
            );
            foreach (array_keys($secondaryList) as $index) {
                $secondaryList[$index]['value'] .=
                    ' AND dewey-hundreds:'
                    . $this->params()->fromQuery('findby');
            }
            $view->secondaryList = $secondaryList;
            $view->secondaryParams = [
                'query_field' => 'dewey-tens',
                'facet_field' => 'dewey-ones',
            ];
            $view->searchParams = ['sort' => 'dewey-sort'];
        }
        return $this->performSearch($view);
    }

    /**
     * Generic action function that handles all the common parts of the below actions
     *
     * @param string $currentAction name of the current action. profound stuff.
     * @param array  $categoryList  category options
     * @param string $facetPrefix   if this is true and we're looking
     * alphabetically, add a facet_prefix to the URL
     *
     * @return \Laminas\View\Model\ViewModel
     */
    protected function performBrowse($currentAction, $categoryList, $facetPrefix)
    {
        $this->setCurrentAction($currentAction);
        $view = $this->createViewModel();
        $view->categoryList = $categoryList;

        $findby = $this->params()->fromQuery('findby');
        if ($findby) {
            $view->secondaryParams = [
                'query_field' => $this->getCategory($findby),
                'facet_field' => $this->getCategory($currentAction),
            ];
            $view->facetPrefix = $facetPrefix && $findby == 'alphabetical';
            [$view->filter, $view->secondaryList]
                = $this->getSecondaryList($findby);
        }

        return $this->performSearch($view);
    }

    /**
     * Browse Author
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function authorAction()
    {
        $categoryList = [
            'alphabetical' => 'By Alphabetical',
            'lcc'          => 'By Call Number',
            'topic'        => 'By Topic',
            'genre'        => 'By Genre',
            'region'       => 'By Region',
            'era'          => 'By Era',
        ];

        return $this->performBrowse('Author', $categoryList, true);
    }

    /**
     * Browse Topic
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function topicAction()
    {
        $categoryList = [
            'alphabetical' => 'By Alphabetical',
            'genre'        => 'By Genre',
            'region'       => 'By Region',
            'era'          => 'By Era',
        ];

        return $this->performBrowse('Topic', $categoryList, true);
    }

    /**
     * Browse Genre
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function genreAction()
    {
        $categoryList = [
            'alphabetical' => 'By Alphabetical',
            'topic'        => 'By Topic',
            'region'       => 'By Region',
            'era'          => 'By Era',
        ];

        return $this->performBrowse('Genre', $categoryList, true);
    }

    /**
     * Browse Region
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function regionAction()
    {
        $categoryList = [
            'alphabetical' => 'By Alphabetical',
            'topic'        => 'By Topic',
            'genre'        => 'By Genre',
            'era'          => 'By Era',
        ];

        return $this->performBrowse('Region', $categoryList, true);
    }

    /**
     * Browse Era
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function eraAction()
    {
        $categoryList = [
            'alphabetical' => 'By Alphabetical',
            'topic'        => 'By Topic',
            'genre'        => 'By Genre',
            'region'       => 'By Region',
        ];

        return $this->performBrowse('Era', $categoryList, true);
    }

    /**
     * Get array with two values: a filter name and a secondary list based on facets
     *
     * @param string $facet the facet we need the contents of
     *
     * @return array
     */
    protected function getSecondaryList($facet)
    {
        $category = $this->getCategory();
        switch ($facet) {
            case 'alphabetical':
                return ['', $this->getAlphabetList()];
            case 'dewey':
                return [
                        'dewey-tens', $this->quoteValues(
                            $this->getFacetList('dewey-hundreds', $category, 'index')
                        ),
                    ];
            case 'lcc':
                return [
                        'callnumber-first', $this->quoteValues(
                            $this->getFacetList(
                                'callnumber-first',
                                $category,
                                'index'
                            )
                        ),
                    ];
            case 'topic':
                return [
                        'topic_facet', $this->quoteValues(
                            $this->getFacetList('topic_facet', $category)
                        ),
                    ];
            case 'genre':
                return [
                        'genre_facet', $this->quoteValues(
                            $this->getFacetList('genre_facet', $category)
                        ),
                    ];
            case 'region':
                return [
                        'geographic_facet', $this->quoteValues(
                            $this->getFacetList('geographic_facet', $category)
                        ),
                    ];
            case 'era':
                return [
                        'era_facet', $this->quoteValues(
                            $this->getFacetList('era_facet', $category)
                        ),
                    ];
        }
        throw new \Exception('Unexpected value: ' . $facet);
    }

    /**
     * Get a list of items from a facet.
     *
     * @param string $facet    which facet we're searching in
     * @param string $category which subfacet the search applies to
     * @param string $sort     how are we ranking these? || 'index'
     * @param string $query    is there a specific query? No = wildcard
     *
     * @return array           Array indexed by value with text of displayText and
     * count
     */
    protected function getFacetList(
        $facet,
        $category = null,
        $sort = 'count',
        $query = '[* TO *]'
    ) {
        $results = $this->getService(\VuFind\Search\Results\PluginManager::class)->get('Solr');
        $params = $results->getParams();
        $params->addFacet($facet);
        if ($category != null) {
            $query = $category . ':' . $query;
        } else {
            $query = $facet . ':' . $query;
        }
        $params->setOverrideQuery($query);
        $params->getOptions()->disableHighlighting();
        $params->getOptions()->spellcheckEnabled(false);
        // Get limit from config
        $params->setFacetLimit($this->config->Browse->result_limit);
        $params->setLimit(0);
        // Facet prefix
        if ($this->params()->fromQuery('facet_prefix')) {
            $params->setFacetPrefix($this->params()->fromQuery('facet_prefix'));
        }
        $params->setFacetSort($sort);
        $result = $results->getFacetList();
        if (isset($result[$facet])) {
            // Sort facets alphabetically if configured to do so:
            if (
                isset($this->config->Browse->alphabetical_order)
                && $this->config->Browse->alphabetical_order
            ) {
                $callback = function ($a, $b) {
                    return $this->getSorter()->compare(
                        $a['displayText'],
                        $b['displayText']
                    );
                };
                usort($result[$facet]['list'], $callback);
            }
            return $result[$facet]['list'];
        } else {
            return [];
        }
    }

    /**
     * Helper class that adds quotes around the values of an array
     *
     * @param array $array Two-dimensional array where each entry has a value param
     *
     * @return array       Array indexed by value with text of displayText and count
     */
    protected function quoteValues($array)
    {
        foreach ($array as $i => $result) {
            $result['value'] = '"' . $result['value'] . '"';
            $array[$i] = $result;
        }
        return $array;
    }

    /**
     * Get the facet search term for an action
     *
     * @param string $action action to be translated
     *
     * @return string
     */
    protected function getCategory($action = null)
    {
        if ($action == null) {
            $action = $this->getCurrentAction();
        }
        switch (strtolower($action)) {
            case 'alphabetical':
                return $this->getCategory();
            case 'dewey':
                return 'dewey-hundreds';
            case 'lcc':
                return 'callnumber-first';
            case 'author':
                return 'author_facet';
            case 'topic':
                return 'topic_facet';
            case 'genre':
                return 'genre_facet';
            case 'region':
                return 'geographic_facet';
            case 'era':
                return 'era_facet';
        }
        return $action;
    }

    /**
     * Get a list of letters to display in alphabetical mode.
     *
     * @return array
     */
    protected function getAlphabetList()
    {
        // Get base alphabet:
        $chars = $this->config->Browse->alphabet_letters
            ?? 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

        // Put numbers in the front for Era since years are important:
        if ($this->getCurrentAction() == 'Era') {
            $chars = '0123456789' . $chars;
        } else {
            $chars .= '0123456789';
        }

        // ALPHABET TO ['value','displayText']
        // (value has asterisk appended for Solr, but is unmodified for tags)
        $action = $this->getCurrentAction();
        $callback = function ($letter) use ($action) {
            // Tag is a special case because it is database-backed; for everything
            // else, use a Solr query that will allow case-insensitive lookups.
            $value = ($action == 'Tag')
                ? $letter
                : '(' . strtoupper($letter) . '* OR ' . strtolower($letter) . '*)';
            return ['value' => $value, 'displayText' => $letter];
        };
        preg_match_all('/(.)/u', $chars, $matches);
        return array_map($callback, $matches[1]);
    }
}
