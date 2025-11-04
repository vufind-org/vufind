<?php

/**
 * Additional functionality for Solr parameters shared with Blender.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library 2022.
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
 * @package  Search
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\Search\Solr;

use function array_slice;
use function in_array;
use function sprintf;

/**
 * Additional functionality for Solr parameters shared with Blender.
 *
 * @category VuFind
 * @package  Search
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
trait ParamsSharedTrait
{
    /**
     * Format display text for a author-id filter entry.
     *
     * @param array  $filter Filter
     * @param string $field  Filter field
     * @param string $value  Filter value
     *
     * @return array
     */
    protected function formatAuthorIdFilterListEntry($filter, $field, $value)
    {
        $displayText = $filter['displayText'];
        if ($id = $this->parseAuthorIdFilter($value)) {
            // Author id filter  (OR query with <field>:<author-id> pairs)
            $displayText = $this->authorityHelper->formatFacet($id);
        } elseif (
            in_array(
                $filter['field'],
                $this->authorityHelper->getAuthorIdFacets()
            )
        ) {
            $displayText = $this->authorityHelper->formatFacet($displayText);
        }
        $filter['displayText'] = $displayText;
        return $filter;
    }

    /**
     * Translate a hierarchical facet filter
     *
     * Translates each facet level and concatenates the result
     *
     * @param string $field    Field name
     * @param string $value    Field value
     * @param string $operator Operator (AND/OR/NOT)
     *
     * @return array
     */
    protected function translateHierarchicalFacetFilter($field, $value, $operator)
    {
        $domain = $this->getOptions()->getTextDomainForTranslatedFacet($field);
        $parts = explode('/', $value);
        if (isset($parts[1]) && ctype_digit($parts[0])) {
            $result = [];
            for ($i = 0; $i <= $parts[0]; $i++) {
                $part = array_slice($parts, 1, $i + 1);
                $key = $i . '/' . implode('/', $part) . '/';
                $result[] = $this->translate($key, null, end($part));
            }
            $displayText = implode(' > ', $result);
        } else {
            $displayText = $this->translate($value);
        }
        return compact('value', 'displayText', 'field', 'operator');
    }

    /**
     * Attempt to parse author id from a author-id filter.
     *
     * @param array $filter Filter
     *
     * @return mixed null|string
     */
    protected function parseAuthorIdFilter($filter)
    {
        $pat = sprintf('/%s:"([a-z0-9_.:]*)"/', AuthorityHelper::AUTHOR2_ID_FACET);

        if (!preg_match($pat, $filter, $matches)) {
            return null;
        }
        return $matches[1];
    }

    /**
     * Check if the given filter is a geographic filter.
     *
     * @param string|array $filter Facet
     *
     * @return boolean
     */
    public function isGeographicFilter($filter)
    {
        $filter = $filter[0]['field'] ?? $filter;
        return strncmp($filter, '{!geofilt', 9) === 0;
    }
}
