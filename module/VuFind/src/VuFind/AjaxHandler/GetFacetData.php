<?php
/**
 * "Get Facet Data" AJAX handler
 *
 * PHP version 5
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

use VuFind\Search\Solr\HierarchicalFacetHelper;
use VuFind\Search\Solr\Results;
use VuFind\Session\Settings as SessionSettings;
use Zend\Mvc\Controller\Plugin\Params;
use Zend\Stdlib\Parameters;

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
     * ILS connection
     *
     * @var HierarchicalFacetHelper
     */
    protected $facetHelper;

    /**
     * Solr search results object
     *
     * @var Results
     */
    protected $results;

    /**
     * Constructor
     *
     * @param SessionSettings         $ss      Session settings
     * @param HierarchicalFacetHelper $fh      Facet helper
     * @param Results                 $results Solr results object
     */
    public function __construct(SessionSettings $ss, HierarchicalFacetHelper $fh,
        Results $results
    ) {
        $this->sessionSettings = $ss;
        $this->facetHelper = $fh;
        $this->results = $results;
    }

    /**
     * Handle a request.
     *
     * @param Params $params Parameter helper from controller
     *
     * @return array [response data, internal status code, HTTP status code]
     */
    public function handleRequest(Params $params)
    {
        $this->disableSessionWrites();  // avoid session write timing bug

        $facet = $params->fromQuery('facetName');
        $sort = $params->fromQuery('facetSort');
        $operator = $params->fromQuery('facetOperator');

        $paramsObj = $this->results->getParams();
        $paramsObj->addFacet($facet, null, $operator === 'OR');
        $paramsObj->initFromRequest(new Parameters($params->fromQuery()));

        $facets = $this->results->getFullFieldFacets([$facet], false, -1, 'count');
        if (empty($facets[$facet]['data']['list'])) {
            return $this->formatResponse([]);
        }

        $facetList = $facets[$facet]['data']['list'];

        if (!empty($sort)) {
            $this->facetHelper->sortFacetList($facetList, $sort == 'top');
        }

        return $this->formatResponse(
            $this->facetHelper->buildFacetArray(
                $facet, $facetList, $this->results->getUrlQuery()
            )
        );
    }
}
