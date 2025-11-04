<?php

/**
 * Finna search results trait
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2015-2017.
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
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace Finna\Search\Results;

use Finna\Search\Factory\UrlQueryHelperFactory;

use function array_slice;
use function is_callable;

/**
 * Finna search results trait
 *
 * @category VuFind
 * @package  Search
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
trait SearchResultsTrait
{
    /*
     * Current request
     *
     * @var Request
     */
    protected $request = null;

    /**
     * Get the URL helper for this object.
     *
     * Finna: Creates a Finna version and adds current search id.
     *
     * @return \VuFind\Search\UrlQueryHelper
     */
    public function getUrlQuery()
    {
        // Set up URL helper:
        if (!isset($this->helpers['urlQuery'])) {
            $factory = new UrlQueryHelperFactory();
            $this->helpers['urlQuery'] = $factory->fromParams(
                $this->getParams(),
                $this->getUrlQueryHelperOptions()
            );
            if (
                null !== $this->request
                && is_callable([$this->helpers['urlQuery'], 'setSearchId'])
            ) {
                $savedSearches = $this->request->getQuery('search');
                if ($savedSearches) {
                    $this->helpers['urlQuery']
                        ->setDefaultParameter('search', $savedSearches);
                }
            }
        }
        return $this->helpers['urlQuery'];
    }

    /**
     * Set current request
     *
     * @param Request $request Current request
     *
     * @return void
     */
    public function setRequest($request)
    {
        $this->request = $request;
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
        $list = parent::getFacetList($filter);

        // Append date range facet to the list so that it gets
        // included even when facet counts are zero.
        $dateRangeField = $this->getParams()->getDateRangeSearchField();
        if (
            !isset($list[$dateRangeField])
            && (null === $filter || isset($filter[$dateRangeField]))
        ) {
            // Resolve facet index in list
            $ind = 0;
            $filter = $filter ?: $this->getParams()->getFacetConfig();

            if (!isset($filter[$dateRangeField])) {
                return $list;
            }

            foreach (array_keys($filter) as $field) {
                if ($field == $dateRangeField) {
                    break;
                }
                $ind++;
            }

            $data = [];
            $filter = $filter[$dateRangeField];
            $data['label'] = $filter;
            $data['list'] = $filter;

            $list
                = array_slice($list, 0, $ind)
                + [$dateRangeField => $data]
                + array_slice($list, $ind);
        }
        return $list;
    }
}
