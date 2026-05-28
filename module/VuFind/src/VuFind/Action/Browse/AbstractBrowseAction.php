<?php

/**
 * Abstract base class for browse actions.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2011.
 * Copyright (C) The National Library of Finland 2026.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Action
 * @author   Chris Hallberg <challber@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Action\Browse;

use Psr\Http\Message\ResponseInterface;
use VuFind\Action\AbstractTemplateRenderingAction;
use VuFind\Config\AccountCapabilities;
use VuFind\I18n\HasSorterInterface;
use VuFind\I18n\HasSorterTrait;
use VuFind\Search\Results\PluginManager as ResultsPluginManager;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\Tags\TagsService;

use function in_array;

/**
 * Abstract base class for browse actions.
 *
 * @category VuFind
 * @package  Action
 * @author   Chris Hallberg <challber@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
abstract class AbstractBrowseAction extends AbstractTemplateRenderingAction implements HasSorterInterface
{
    use HasSorterTrait;

    /**
     * Constructor.
     *
     * @param array                $config               VuFind configuration
     * @param AccountCapabilities  $accountCapabilities  Account capabilities
     * @param TagsService          $tagsService          Tags service
     * @param ResultsPluginManager $resultsPluginManager Search results plugin manager
     */
    public function __construct(
        #[Autowire(config: 'config')]
        protected array $config,
        protected AccountCapabilities $accountCapabilities,
        protected TagsService $tagsService,
        protected ResultsPluginManager $resultsPluginManager,
    ) {
    }

    /**
     * Determine which browse options to display, and in which order. Returns an
     * array of browse options in the configured order.
     *
     * @return array
     */
    protected function getActiveBrowseOptions(): array
    {
        // Get a list of all of the options mentioned in config.ini:
        $browseConfig = (array)($this->config['Browse'] ?? []);
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
    protected function buildBrowseOptions(): array
    {
        // Initialize the array of top-level browse options.
        $browseOptions = [];

        $activeOptions = $this->getActiveBrowseOptions();
        foreach ($activeOptions as $option) {
            switch ($option) {
                case 'dewey':
                    $deweyLabel = in_array('lcc', $activeOptions)
                        ? 'browse_dewey' : 'Call Number';
                    $browseOptions[] = $this->buildBrowseOption('Dewey', $deweyLabel);
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
     * Build an array containing options describing a top-level Browse option.
     *
     * @param string $action      The name of the Action for this option
     * @param string $description A description of this Browse option
     *
     * @return array              The Browse option array
     */
    protected function buildBrowseOption(string $action, string $description): array
    {
        return compact('action', 'description');
    }

    /**
     * Create an array of template parameters.
     *
     * @param string $action Current action
     * @param array  $params Parameters to pass to template renderer.
     *
     * @return array
     */
    protected function createTemplateParams(string $action, array $params = []): array
    {
        $params['currentAction'] = $action;
        $params['browseOptions'] = $this->buildBrowseOptions();

        // Include query params:
        foreach (['findby', 'query', 'category'] as $param) {
            $params[$param] = $this->getQueryParam($param);
        }
        return $params;
    }

    /**
     * Perform the search.
     *
     * @param string $action         Current action
     * @param array  $templateParams Template params
     *
     * @return ResponseInterface
     */
    protected function performSearch(string $action, array $templateParams): ResponseInterface
    {
        // Remove disabled facets
        foreach ($this->getDisabledFacets() as $facet) {
            unset($templateParams['categoryList'][$facet]);
        }

        // SEARCH (Tag does its own search)
        if (
            'Tag' !== $action
            && ($query = $this->getQueryParam('query'))
        ) {
            $queryField = $this->getQueryParam('query_field');
            $results = $this->getFacetList(
                $this->getQueryParam('facet_field'),
                $queryField,
                'count',
                $query
            );
            $templateParams['resultList'] = [];
            foreach ($results as $result) {
                $templateParams['resultList'][] = [
                    'displayText' => $result['displayText'],
                    'value' => $result['value'],
                    'count' => $result['count'],
                ];
            }
            // Don't make a second filter if it would be the same facet
            $filterField = urlencode('filter[]');
            $templateParams['paramTitle'] = $queryField != $this->getCategory($action)
                ? $filterField . '=' . $queryField . ':'
                    . urlencode($query) . '&'
                : '';
            switch ($action) {
                case 'LCC':
                    $templateParams['paramTitle'] .= $filterField . '=callnumber-subject:';
                    break;
                case 'Dewey':
                    $templateParams['paramTitle'] .= $filterField . '=dewey-ones:';
                    break;
                default:
                    $templateParams['paramTitle'] .= $filterField . '=' . $this->getCategory($action) . ':';
            }
            $templateParams['paramTitle'] = str_replace(
                '+AND+',
                '&' . $filterField . '=',
                $templateParams['paramTitle']
            );
        }

        return $this->renderTemplate($this->request, $this->response, $templateParams, 'browse/home');
    }

    /**
     * Generic action function that handles all the common parts of the below actions.
     *
     * @param string $currentAction name of the current action. profound stuff.
     * @param array  $categoryList  category options
     * @param string $facetPrefix   if this is true and we're looking
     * alphabetically, add a facet_prefix to the URL
     *
     * @return ResponseInterface
     */
    protected function performBrowse(string $currentAction, array $categoryList, string $facetPrefix): ResponseInterface
    {
        $templateParams = $this->createTemplateParams($currentAction, compact('categoryList'));

        if ($findby = $this->getQueryParam('findby')) {
            $templateParams['secondaryParams'] = [
                'query_field' => $this->getCategory($currentAction, $findby),
                'facet_field' => $this->getCategory($currentAction),
            ];
            $templateParams['facetPrefix'] = $facetPrefix && $findby === 'alphabetical';
            [$templateParams['filter'], $templateParams['secondaryList']]
                = $this->getSecondaryList($currentAction, $findby);
        }

        return $this->performSearch($currentAction, $templateParams);
    }

    /**
     * Get array with two values: a filter name and a secondary list based on facets.
     *
     * @param string $action Action
     * @param string $facet  the facet we need the contents of
     *
     * @return array
     */
    protected function getSecondaryList(string $action, string $facet): array
    {
        $category = $this->getCategory($action);
        switch ($facet) {
            case 'alphabetical':
                return ['', $this->getAlphabetList($action)];
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
     * @param string  $facet    which facet we're searching in
     * @param ?string $category which subfacet the search applies to
     * @param string  $sort     how are we ranking these? || 'index'
     * @param string  $query    is there a specific query? No = wildcard
     *
     * @return array           Array indexed by value with text of displayText and count
     */
    protected function getFacetList(
        string $facet,
        ?string $category = null,
        $sort = 'count',
        $query = '[* TO *]'
    ): array {
        $results = $this->resultsPluginManager->get('Solr');
        $params = $results->getParams();
        $params->addFacet($facet);
        $query = ($category ?? $facet) . ':' . $query;
        $params->setOverrideQuery($query);
        $params->getOptions()->disableHighlighting();
        $params->getOptions()->spellcheckEnabled(false);
        // Get limit from config
        $params->setFacetLimit($this->config['Browse']['result_limit'] ?? 100);
        $params->setLimit(0);
        // Facet prefix
        if ($facetPrefix = $this->getQueryParam('facet_prefix')) {
            $params->setFacetPrefix($facetPrefix);
        }
        $params->setFacetSort($sort);
        $result = $results->getFacetList();
        if (isset($result[$facet])) {
            // Sort facets alphabetically if configured to do so:
            if ($this->config['Browse']['alphabetical_order'] ?? false) {
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
     * Helper method that adds quotes around the values of an array.
     *
     * @param array $array Two-dimensional array where each entry has a value param
     *
     * @return array
     */
    protected function quoteValues(array $array): array
    {
        return array_map(
            function ($result) {
                $result['value'] = '"' . $result['value'] . '"';
                return $result;
            },
            $array
        );
    }

    /**
     * Get the facet search term for an action.
     *
     * @param string  $action Action to be translated
     * @param ?string $findby Field to find by
     *
     * @return string
     */
    protected function getCategory(string $action, ?string $findby = null): string
    {
        switch (strtolower($findby ?? $action)) {
            case 'alphabetical':
                return $this->getCategory($action);
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
     * @param string $action Current action
     *
     * @return array
     */
    protected function getAlphabetList(string $action): array
    {
        // Get base alphabet:
        $chars = $this->config->Browse->alphabet_letters
            ?? 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

        // Put numbers in the front for Era since years are important:
        if ('Era' === $action) {
            $chars = '0123456789' . $chars;
        } else {
            $chars .= '0123456789';
        }

        // ALPHABET TO ['value','displayText']
        // (value has asterisk appended for Solr, but is unmodified for tags)
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

    /**
     * Get disabled facets.
     *
     * @return array
     */
    protected function getDisabledFacets(): array
    {
        $result = [];
        foreach ($this->config['Browse'] ?? [] as $key => $setting) {
            if (!$setting) {
                $result[] = $key;
            }
        }
        return $result;
    }

    /**
     * Are tags enabled?
     *
     * @return bool
     */
    protected function tagsEnabled(): bool
    {
        return $this->accountCapabilities->getTagSetting() !== 'disabled';
    }
}
