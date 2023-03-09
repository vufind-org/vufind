<?php
/**
 * "Get Facet Data" AJAX handler
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2018.
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
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\AjaxHandler;

use Laminas\Mvc\Controller\Plugin\Params;
use Laminas\Stdlib\Parameters;
use VuFind\Search\Results\PluginManager as ResultsManager;
use VuFind\Search\Solr\HierarchicalFacetHelper;
use VuFind\Session\Settings as SessionSettings;

/**
 * "Get Facet Data" AJAX handler
 *
 * Get hierarchical facet data for jsTree
 *
 * Parameters:
 * facetName  The facet to retrieve
 * facetSort  By default all facets are sorted by count. Two values are available
 * for alternative sorting:
 *   top = sort the top level alphabetically, rest by count
 *   all = sort all levels alphabetically
 *
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GetFacetData extends AbstractBase
{
    /**
     * Hierarchical facet helper
     *
     * @var HierarchicalFacetHelper
     */
    protected $facetHelper;

    /**
     * Solr search results manager
     *
     * @var ResultsManager
     */
    protected $resultsManager;

    /**
     * Constructor
     *
     * @param SessionSettings         $ss Session settings
     * @param HierarchicalFacetHelper $fh Facet helper
     * @param ResultsManager          $rm Search results manager
     */
    public function __construct(
        SessionSettings $ss,
        HierarchicalFacetHelper $fh,
        ResultsManager $rm
    ) {
        $this->sessionSettings = $ss;
        $this->facetHelper = $fh;
        $this->resultsManager = $rm;
    }

    /**
     * Handle a request.
     *
     * @param Params $params Parameter helper from controller
     *
     * @return array [response data, HTTP status code]
     */
    public function handleRequest(Params $params)
    {
        $this->disableSessionWrites();  // avoid session write timing bug

        // Allow both GET and POST variables:
        $request = $params->fromQuery() + $params->fromPost();

        $facet = $request['facetName'] ?? null;
        $sort = $request['facetSort'] ?? null;
        $operator = $request['facetOperator'] ?? null;
        $backend = $request['source'] ?? DEFAULT_SEARCH_BACKEND;

        $results = $this->resultsManager->get($backend);
        $paramsObj = $results->getParams();
        $paramsObj->addFacet($facet, null, $operator === 'OR');
        $paramsObj->initFromRequest(new Parameters($request));

        $facets = $results->getFullFieldFacets([$facet], false, -1, 'count');
        if (empty($facets[$facet]['data']['list'])) {
            $facets = [];
        } else {
            // Set appropriate query suppression / extra field behavior:
            $queryHelper = $results->getUrlQuery();
            $queryHelper->setSuppressQuery(
                (bool)($request['querySuppressed'] ?? false)
            );
            $extraFields = array_filter(explode(',', $request['extraFields'] ?? ''));
            foreach ($extraFields as $field) {
                if (isset($request[$field])) {
                    $queryHelper->setDefaultParameter($field, $request[$field]);
                }
            }

            $facetList = $facets[$facet]['data']['list'];
            $this->facetHelper->sortFacetList($facetList, $sort);
            $facets = $this->facetHelper
                ->buildFacetArray($facet, $facetList, $queryHelper, false);
        }
        return $this->formatResponse(compact('facets'));
    }
}
