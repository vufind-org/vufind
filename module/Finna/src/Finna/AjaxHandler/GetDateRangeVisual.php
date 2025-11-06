<?php

/**
 * "Get Date Range Visual" AJAX handler
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2018.
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
 * @package  AJAX
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\AjaxHandler;

use Laminas\Mvc\Controller\Plugin\Params;
use Laminas\Stdlib\Parameters;
use VuFind\Config\ConfigManagerInterface;
use VuFind\Search\Results\PluginManager as ResultsManager;
use VuFind\Session\Settings as SessionSettings;

/**
 * "Get Date Range Visual" AJAX handler
 *
 * Get Date Range Visual
 *
 * @category VuFind
 * @package  AJAX
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GetDateRangeVisual extends \VuFind\AjaxHandler\AbstractBase
{
    /**
     * Constructor
     *
     * @param SessionSettings        $sessionSettings Session settings
     * @param ConfigManagerInterface $configManager   Config manager
     * @param ResultsManager         $resultsManager  Results manager
     */
    public function __construct(
        SessionSettings $sessionSettings,
        protected ConfigManagerInterface $configManager,
        protected ResultsManager $resultsManager
    ) {
        $this->sessionSettings = $sessionSettings;
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

        $backend = ucfirst($params->fromQuery('backend', 'Solr') ?: 'Solr');
        $results = $this->resultsManager->get($backend);
        $searchParams = $results->getParams();
        $config = $this->configManager->getConfigArray($searchParams->getOptions()->getFacetsIni());
        if (null === ($dateRangeVis = $config['SpecialFacets']['dateRangeVis'] ?? null)) {
            return $this->formatResponse([], self::STATUS_HTTP_ERROR);
        }

        [, $facet] = explode(':', $dateRangeVis);

        $searchParams->initFromRequest(new Parameters($params->fromQuery()));
        if (method_exists($results, 'getPartialFieldFacets')) {
            $facets = $results->getFullFieldFacets(
                [$facet],
                false,
                -1,
                'count'
            );
            $facetList = $facets[$facet]['data']['list'];
        } else {
            $results->getOptions()->disableHighlighting();
            $searchParams->setLimit(0);
            $searchParams->addFacet($facet);
            $results->performAndProcessSearch();
            $facets = $results->getFacetlist([$facet => $facet]);
            $facetList = $facets[$facet]['list'] ?? [];
        }

        if (empty($facetList)) {
            return $this->formatResponse([]);
        }

        $res = [];
        $min = PHP_INT_MAX;
        $max = -$min;

        foreach ($facetList as $f) {
            $count = $f['count'];
            $val = $f['displayText'];
            // Only retain numeric values
            if (!preg_match('/^-?[0-9]+$/', $val)) {
                continue;
            }
            $min = min($min, (int)$val);
            $max = max($max, (int)$val);
            $res[] = [$val, $count];
        }
        $res = [$facet => ['data' => $res, 'min' => $min, 'max' => $max]];
        return $this->formatResponse($res);
    }
}
