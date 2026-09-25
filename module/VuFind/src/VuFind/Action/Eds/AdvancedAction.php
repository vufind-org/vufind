<?php

/**
 * EDS advanced search action.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Action\Eds;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Action\Search\AbstractSearchAndResultsAction;
use VuFind\Search\Base\Results;
use VuFind\Solr\Utils as SolrUtils;

use function array_key_exists;
use function in_array;

/**
 * EDS advanced search action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class AdvancedAction extends AbstractSearchAndResultsAction
{
    /**
     * Display advanced search page.
     *
     * @param ServerRequestInterface $request  Server request
     * @param ResponseInterface      $response Response
     *
     * @return ResponseInterface
     */
    public function action(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        $templateParams = [];
        return $this->renderAdvancedSearch(
            fn (array $templateParams): array => $templateParams + [
                'limiterList' => $this->processAdvancedFacets(
                    $this->getAdvancedFacets(),
                    $templateParams['saved']
                ),
                'expanderList' => $this->processAdvancedExpanders($templateParams['saved']),
                'searchModes' => $this->processAdvancedSearchModes($templateParams['saved']),
                'dateRangeLimit' => $this->processPublicationDateRange($templateParams['saved']),
            ]
        );
    }

    /**
     * Return an array containing advanced facet information. This data may come from the cache.
     *
     * @return array
     */
    protected function getAdvancedFacets(): array
    {
        // VuFind facets are what the EDS API calls limiters. Available limiters
        // are returned with a call to the EDS API Info method and are cached.
        // Since they are obtained from a separate call, there is no need to call
        // search.

        // Check if we have facet results stored in session. Build them if we don't.
        // pull them from the session cache
        $results = $this->resultsPluginManager->get('EDS');
        $options = $results->getOptions();
        return $options->getAdvancedLimiters();
    }

    /**
     * Process the facets to be used as limits on the Advanced Search screen.
     *
     * @param array         $facetList    The advanced facet values
     * @param Results|false $searchObject Saved search object (false if none)
     *
     * @return array Sorted facets, with selected values flagged.
     */
    protected function processAdvancedFacets(array $facetList, Results|false $searchObject = false): array
    {
        // Process the facets, assuming they came back
        foreach ($facetList as $facet => $list) {
            if (isset($list['LimiterValues'])) {
                foreach ($list['LimiterValues'] as $key => $value) {
                    // Build the filter string for the URL:
                    $fullFilter = $facet . ':' . $value['Value'];

                    // If we haven't already found a selected facet and the current
                    // facet has been applied to the search, we should store it as
                    // the selected facet for the current control. Cover AND and OR
                    // filter cases to be on the safe side; either might be used,
                    // but we don't currently expect both at once on the same field.
                    if ($searchObject) {
                        $limitFilt = 'LIMIT|' . $fullFilter;
                        $orLimitFilt = '~' . $limitFilt;
                        if ($searchObject->getParams()->hasFilter($limitFilt)) {
                            $facetList[$facet]['LimiterValues'][$key]['selected'] = true;
                            // Remove the filter from the search object -- we don't
                            // want it to show up in the "applied filters" sidebar
                            // since it will already be accounted for by being
                            // selected in the filter select list!
                            $searchObject->getParams()->removeFilter($limitFilt);
                        } elseif ($searchObject->getParams()->hasFilter($orLimitFilt)) {
                            $facetList[$facet]['LimiterValues'][$key]['selected'] = true;
                            $searchObject->getParams()->removeFilter($orLimitFilt);
                        }
                    } else {
                        if ('y' == $facetList[$facet]['DefaultOn']) {
                            $facetList[$facet]['selected'] = true;
                        }
                    }
                }
            }
        }
        return $facetList;
    }

    /**
     * Process the expanders to be used on the Advanced Search screen.
     *
     * @param Results|false $searchObject Saved search object (false if none)
     *
     * @return array Sorted facets, with selected values flagged.
     */
    protected function processAdvancedExpanders(Results|false $searchObject = false): array
    {
        $results = $this->resultsPluginManager->get('EDS');
        $options = $results->getOptions();
        $availableExpanders = $options->getAvailableExpanders();
        $defaultExpanders = $options->getDefaultExpanders();
        // Process the expanders, assuming they came back
        foreach ($availableExpanders as $key => $value) {
            if ($searchObject) {
                $expandFilt = 'EXPAND:' . $value['Value'];
                if ($searchObject->getParams()->hasFilter($expandFilt)) {
                    $availableExpanders[$key]['selected'] = true;
                    // Remove the filter from the search object -- we don't want
                    // it to show up in the "applied filters" sidebar since it
                    // will already be accounted for by being selected in the
                    // filter select list!
                    $searchObject->getParams()->removeFilter($expandFilt);
                }
            } else {
                if (in_array($key, $defaultExpanders)) {
                    $availableExpanders[$key]['selected'] = true;
                }
            }
        }
        return $availableExpanders;
    }

    /**
     * Process the search modes to be used on the Advanced Search screen.
     *
     * @param Results|false $searchObject Saved search object (false if none)
     *
     * @return array Search modes with selected values flagged.
     */
    protected function processAdvancedSearchModes(Results|false $searchObject = false): array
    {
        $results = $this->resultsPluginManager->get('EDS');
        $options = $results->getOptions();
        $searchModes = $options->getModeOptions();
        $useDefault = true;
        // Process the facets, assuming they came back
        if ($searchObject) {
            foreach ($searchModes as $key => $mode) {
                $modeFilter = 'SEARCHMODE:' . $mode['Value'];
                if ($searchObject->getParams()->hasFilter($modeFilter)) {
                    $searchModes[$key]['selected'] = true;
                    // Remove the filter from the search object -- we don't want
                    // it to show up in the "applied filters" sidebar since it
                    // will already be accounted for by being selected in the
                    // filter select list!
                    $searchObject->getParams()->removeFilter($modeFilter);
                    $useDefault = false;
                }
            }
        }
        if ($useDefault) {
            $key = $options->getDefaultMode();
            if (array_key_exists($key, $searchModes)) {
                $searchModes[$key]['selected'] = true;
            }
        }

        return $searchModes;
    }

    /**
     * Process the publication date range limiter widget.
     *
     * @param Results|false $searchObject Saved search object (false if none)
     *
     * @return array To and from dates
     */
    protected function processPublicationDateRange(Results|false $searchObject = false)
    {
        $from = $to = '';
        if ($searchObject) {
            $filters = $searchObject->getParams()->getFilterList();
            foreach ($filters as $key => $value) {
                if ('PublicationDate' == $key) {
                    if ($range = SolrUtils::parseRange($value[0]['value'])) {
                        $from = $range['from'] == '*' ? '11' : $range['from'];
                        $to = $range['to'] == '*' ? '12' : $range['to'];
                    }
                    $searchObject->getParams()
                        ->removeFilter($key . ':' . $value[0]['value']);
                    break;
                }
            }
        }
        return [$from, $to];
    }
}
