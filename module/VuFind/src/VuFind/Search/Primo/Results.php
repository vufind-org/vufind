<?php
/**
 * Primo Central Search Results
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2011.
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
 * @package  Search_Primo
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace VuFind\Search\Primo;

/**
 * Primo Central Search Parameters
 *
 * @category VuFind
 * @package  Search_Primo
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Results extends \VuFind\Search\Base\Results
{
    /**
     * Facet details:
     *
     * @var array
     */
    protected $responseFacets = null;

    /**
     * Search backend identifier.
     *
     * @var string
     */
    protected $backendId = 'Primo';

    /**
     * Support method for performAndProcessSearch -- perform a search based on the
     * parameters passed to the object.
     *
     * @return void
     */
    protected function performSearch()
    {
        $query  = $this->getParams()->getQuery();
        $limit  = $this->getParams()->getLimit();
        $offset = $this->getStartRecord() - 1;
        $params = $this->getParams()->getBackendParameters();
        $collection = $this->getSearchService()->search(
            $this->backendId,
            $query,
            $offset,
            $limit,
            $params
        );

        $this->responseFacets = $collection->getFacets();
        $this->resultTotal = $collection->getTotal();
        $this->errors = $collection->getErrors();

        // Construct record drivers for all the items in the response:
        $this->results = $collection->getRecords();
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
        // If there is no filter, we'll use all facets as the filter:
        if (null === $filter) {
            $filter = $this->getParams()->getFacetConfig();
        }

        // We want to sort the facets to match the order in the .ini file.  Let's
        // create a lookup array to determine order:
        $order = array_flip(array_keys($filter));
        // Loop through the facets returned by Primo.
        $facetResult = [];
        $translatedFacets = $this->getOptions()->getTranslatedFacets();
        if (is_array($this->responseFacets)) {
            foreach ($this->responseFacets as $field => $current) {
                $transTextDomain = '';
                if ($translate = in_array($field, $translatedFacets)) {
                    $transTextDomain = $this->getOptions()
                        ->getTextDomainForTranslatedFacet($field);
                }
                if (isset($filter[$field])) {
                    $new = [];
                    foreach ($current as $value => $count) {
                        $rawFixed = $this->getParams()->fixPrimoFacetValue($value);
                        $displayText = $translate ? $this->translate(
                            "$transTextDomain::$value",
                            [],
                            $rawFixed
                        ) : $rawFixed;
                        $new[] = [
                            'value' => $value,
                            'displayText' => $displayText,
                            'isApplied' =>
                                $this->getParams()->hasFilter("$field:" . $value),
                            'operator' =>
                                $this->getParams()->getFacetOperator($field),
                            'count' => $count
                        ];
                    }

                    $cmp = function ($x, $y) {
                        return $y['count'] - $x['count'];
                    };

                    usort($new, $cmp);

                    // Basic reformatting of the data:
                    $current = ['list' => $new];

                    // Inject label from configuration:
                    $current['label'] = $filter[$field];
                    $current['field'] = $field;

                    // Put the current facet cluster in order based on the .ini
                    // settings, then override the display name again using .ini
                    // settings.
                    $facetResult[$order[$field]] = $current;
                }
            }
        }

        ksort($facetResult);

        // Rewrite the sorted array with appropriate keys:
        $finalResult = [];
        foreach ($facetResult as $current) {
            $finalResult[$current['field']] = $current;
        }

        return $finalResult;
    }
}
