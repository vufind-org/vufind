<?php
/**
 * "Get Side Facets" AJAX handler
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2018-2019.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace Finna\AjaxHandler;

use VuFind\Search\Base\Results;

/**
 * "Get Side Facets" AJAX handler
 *
 * @category VuFind
 * @package  AJAX
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GetSideFacets extends \VuFind\AjaxHandler\GetSideFacets
{
    /**
     * Get the result count for a checkbox facet
     *
     * @param string  $facet   Facet
     * @param Results $results Search results
     *
     * @return int|null
     */
    protected function getCheckboxFacetCount($facet, Results $results)
    {
        $checkboxFacets = $results->getParams()->getCheckboxFacets();
        foreach ($checkboxFacets as $checkboxFacet) {
            if ($facet !== $checkboxFacet['filter']) {
                continue;
            }
            list($field, $value) = explode(':', $facet, 2);
            $checkboxResults = $results->getFacetList(
                [$field => $value]
            );
            if (!isset($checkboxResults[$field]['list'])) {
                return 0;
            }
            $count = 0;
            $truncate = substr($value, -1) === '*';
            if ($truncate) {
                $value = substr($value, 0, -1);
            }
            foreach ($checkboxResults[$field]['list'] as $item) {
                if ($item['value'] == $value
                    || ($truncate
                    && preg_match('/^' . $value . '/', $item['value']))
                    || ($item['value'] == 'true' && $value == '1')
                    || ($item['value'] == 'false' && $value == '0')
                ) {
                    $count += $item['count'];
                }
            }
            return $count;
        }
        return 0;
    }
}
