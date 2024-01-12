<?php

/**
 * "Get Visualization Data" AJAX handler
 *
 * PHP version 8
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
 * @author   Chris Hallberg <crhallberg@gmail.com>
 * @author   Till Kinstler <kinstler@gbv.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\AjaxHandler;

use Laminas\Mvc\Controller\Plugin\Params;
use Laminas\Stdlib\Parameters;
use VuFind\Search\Solr\Results;
use VuFind\Session\Settings as SessionSettings;

/**
 * "Get Visualization Data" AJAX handler
 *
 * AJAX for timeline feature (PubDateVisAjax)
 *
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Chris Hallberg <crhallberg@gmail.com>
 * @author   Till Kinstler <kinstler@gbv.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GetVisData extends AbstractBase
{
    /**
     * Solr search results object
     *
     * @var Results
     */
    protected $results;

    /**
     * Constructor
     *
     * @param SessionSettings $ss      Session settings
     * @param Results         $results Solr search results object
     */
    public function __construct(SessionSettings $ss, Results $results)
    {
        $this->sessionSettings = $ss;
        $this->results = $results;
    }

    /**
     * Extract details from applied filters.
     *
     * @param array $filters    Current filter list
     * @param array $dateFacets Objects containing the date ranges
     *
     * @return array
     */
    protected function processDateFacets($filters, $dateFacets)
    {
        $result = [];
        foreach ($dateFacets as $current) {
            $from = $to = '';
            if (isset($filters[$current])) {
                foreach ($filters[$current] as $filter) {
                    if (preg_match('/\[[\d\*]+ TO [\d\*]+\]/', $filter)) {
                        $range = explode(' TO ', trim($filter, '[]'));
                        $from = $range[0] == '*' ? '' : $range[0];
                        $to = $range[1] == '*' ? '' : $range[1];
                        break;
                    }
                }
            }
            $result[$current] = [$from, $to];
            $result[$current]['label']
                = $this->results->getParams()->getFacetLabel($current);
        }
        return $result;
    }

    /**
     * Filter bad values from facet lists and add useful data fields.
     *
     * @param array $filters Current filter list
     * @param array $fields  Processed date information from processDateFacets
     *
     * @return array
     */
    protected function processFacetValues($filters, $fields)
    {
        $facets = $this->results->getFullFieldFacets(array_keys($fields));
        $retVal = [];
        foreach ($facets as $field => $values) {
            $filter = $filters[$field][0] ?? null;
            $newValues = [
                'data' => [],
                'min' => $fields[$field][0] > 0 ? $fields[$field][0] : 0,
                'max' => $fields[$field][1] > 0 ? $fields[$field][1] : 0,
                'removalURL' => $this->results->getUrlQuery()
                    ->removeFacet($field, $filter)->getParams(false),
            ];
            foreach ($values['data']['list'] as $current) {
                // Only retain numeric values!
                if (preg_match('/^[0-9]+$/', $current['value'])) {
                    $newValues['data'][]
                        = [$current['value'], $current['count']];
                }
            }
            $retVal[$field] = $newValues;
        }
        return $retVal;
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
        $paramsObj = $this->results->getParams();
        $paramsObj->initFromRequest(new Parameters($params->fromQuery()));
        foreach ($params->fromQuery('hf', []) as $hf) {
            $paramsObj->addHiddenFilter($hf);
        }
        $paramsObj->getOptions()->disableHighlighting();
        $paramsObj->getOptions()->spellcheckEnabled(false);
        $filters = $paramsObj->getRawFilters();
        $rawDateFacets = $params->fromQuery('facetFields');
        $dateFacets = empty($rawDateFacets) ? [] : explode(':', $rawDateFacets);
        $fields = $this->processDateFacets($filters, $dateFacets);
        $facets = $this->processFacetValues($filters, $fields);
        return $this->formatResponse(compact('facets'));
    }
}
