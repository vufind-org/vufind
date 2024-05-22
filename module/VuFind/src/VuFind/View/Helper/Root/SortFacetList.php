<?php

/**
 * Sort facet list view helper
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

use Laminas\View\Helper\AbstractHelper;

/**
 * Sort facet list view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class SortFacetList extends AbstractHelper implements
    \VuFind\I18n\HasSorterInterface
{
    use \VuFind\I18n\HasSorterTrait;

    /**
     * Turns facet information into an alphabetical list.
     *
     * @param \VuFind\Search\Base\Results $results      Search result object
     * @param string                      $field        Facet field to sort
     * @param array                       $list         Facet value list extract from
     * the search result object's getFacetList method
     * @param array                       $searchRoute  Route to use to generate
     * search URLs for individual facet values
     * @param string                      $formatString String for formatting facet
     * values (e.g. with %%displayText%% and %%count%% tokens)
     *
     * @return array      Associative URL => description array sorted by description
     */
    public function __invoke($results, $field, $list, $searchRoute, $formatString = '%%displayText%%')
    {
        $facets = [];
        // avoid limit on URL
        $results->getParams()->setLimit($results->getOptions()->getDefaultLimit());
        $urlHelper = $this->getView()->plugin('url');
        foreach ($list as $value) {
            $url = $urlHelper($searchRoute) . $results->getUrlQuery()
                ->addFacet($field, $value['value'], ($value['operator'] ?? 'AND'))
                ->getParams();
            $facets[$url] = $this->facetValueToString($value, $formatString);
        }
        $this->getSorter()->natsort($facets);
        return $facets;
    }

    /**
     * Convert a facet array to a string.
     *
     * @param array  $values Facet value array
     * @param string $format Format string
     *
     * @return string
     */
    protected function facetValueToString(array $values, string $format): string
    {
        $search = $replace = [];
        foreach ($values as $key => $value) {
            $search[] = "%%$key%%";
            $replace[] = $value;
        }
        return str_replace($search, $replace, $format);
    }
}
