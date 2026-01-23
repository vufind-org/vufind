<?php

/**
 * EDS API Results
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2022.
 * Copyright (C) EBSCO Industries 2013
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
 * @package  EBSCO
 * @author   Michelle Milton <mmilton@epnet.com>
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Search\EDS;

use VuFind\Config\Config;
use VuFind\Record\Loader;
use VuFindSearch\Command\SearchCommand;
use VuFindSearch\ParamBag;
use VuFindSearch\Service as SearchService;

/**
 * EDS API Results
 *
 * @category VuFind
 * @package  EBSCO
 * @author   Michelle Milton <mmilton@epnet.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Results extends \VuFind\Search\Base\Results
{
    /**
     * Search backend identifier.
     *
     * @var string
     */
    protected $backendId = 'EDS';

    /**
     * Facet list
     *
     * @var array
     */
    protected $responseFacets;

    /**
     * Constructor
     *
     * @param \VuFind\Search\Base\Params $params        Object representing user
     * search parameters.
     * @param SearchService              $searchService Search service
     * @param Loader                     $recordLoader  Record loader
     * @param Config                     $config        Backend config
     */
    public function __construct(
        Params $params,
        SearchService $searchService,
        Loader $recordLoader,
        protected Config $config
    ) {
        parent::__construct($params, $searchService, $recordLoader);
    }

    /**
     * Store an empty response with an error message instead of performing a search.
     *
     * @param string|array $error Error message(s) to display to user.
     *
     * @return void
     */
    protected function storeErrorResponse(string|array $error): void
    {
        parent::storeErrorResponse($error);
        $this->responseFacets = [];
    }

    /**
     * Support method for performAndProcessSearch -- perform a search based on the
     * parameters passed to the object.
     *
     * @return void
     */
    protected function performSearch()
    {
        $query  = $this->getParams()->getQuery();
        $allTerms = $query->getAllTerms();
        $limit  = $this->getParams()->getLimit();
        $offset = $this->getStartRecord() - 1;
        $params = $this->getParams()->getBackendParameters();
        if ($allTerms === '') {
            if (!$this->config['General']['limiter_only'] ?? false) {
                $this->storeErrorResponse('empty_search_disallowed');
                return;
            } elseif (!$this->paramsIncludeLimiter($params)) {
                $this->storeErrorResponse('empty_search_no_filters_disallowed');
                return;
            }
            // Limiter-only is allowed, and there is a limiter, so continue.
        }
        $command = new SearchCommand(
            $this->backendId,
            $query,
            $offset,
            $limit,
            $params
        );
        $collection = $this->getSearchService()->invoke($command)
            ->getResult();
        if (null != $collection) {
            $this->responseFacets = $collection->getFacets();
            $this->resultTotal = $collection->getTotal();

            // Add fake date facets if flagged earlier; this is necessary in order
            // to display the date range facet control in the interface.
            $dateFacets = $this->getParams()->getDateFacetSettings();
            if (!empty($dateFacets)) {
                foreach ($dateFacets as $dateFacet) {
                    $this->responseFacets[$dateFacet] = [''];
                }
            }

            // Construct record drivers for all the items in the response:
            $this->results = $collection->getRecords();
            $this->restrictedView = $collection->isRestrictedView();
        }
    }

    /**
     * Return true if the given $params include any filters that limit the number
     * of results.  EDS "filters" can also include expanders.
     *
     * @param ParamBag $params The params
     *
     * @return bool
     */
    public function paramsIncludeLimiter(ParamBag $params): bool
    {
        return (bool)array_filter(
            $params->get('filters') ?? [],
            fn ($filter) => !str_starts_with($filter, 'EXPAND')
        );
    }

    /**
     * Returns the stored list of facets for the last search
     *
     * @param array $filter Array of field => on-screen description listing
     * all of the desired facet fields; set to null to get all configured values.
     *
     * @return array        Facets data arrays
     */
    public function getFacetList($filter = null)
    {
        if (null === $this->responseFacets) {
            $this->performAndProcessSearch();
        }
        return $this->buildFacetList($this->responseFacets, $filter);
    }

    /**
     * Get an array of the record ID mapped to its score.
     *
     * @return array
     */
    public function getScores()
    {
        $scoreMap = [];
        foreach ($this->results as $record) {
            $scoreMap[$record->getUniqueId()] = $record->getScore();
        }
        return $scoreMap;
    }

    /**
     * Getting the highest relevance of all the results
     *
     * @return ?float
     */
    public function getMaxScore()
    {
        if (
            empty($this->results) ||
            'relevance' != $this->getParams()->getSort()
        ) {
            return null;
        }
        return $this->results[0]->getScore();
    }
}
