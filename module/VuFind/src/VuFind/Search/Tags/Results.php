<?php

/**
 * Tags aspect of the Search Multi-class (Results)
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
 * @package  Search_Tags
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Search\Tags;

use VuFind\Record\Loader;
use VuFind\Search\Base\Results as BaseResults;
use VuFind\Tags\TagsService;
use VuFindSearch\Service as SearchService;

use function count;

/**
 * Search Tags Results
 *
 * @category VuFind
 * @package  Search_Tags
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Results extends BaseResults
{
    /**
     * Constructor
     *
     * @param \VuFind\Search\Base\Params $params        Object representing user
     * search parameters.
     * @param SearchService              $searchService Search service
     * @param Loader                     $recordLoader  Record loader
     * @param TagsService                $tagsService   Tags service
     */
    public function __construct(
        \VuFind\Search\Base\Params $params,
        SearchService $searchService,
        Loader $recordLoader,
        protected TagsService $tagsService
    ) {
        parent::__construct($params, $searchService, $recordLoader);
    }

    /**
     * Process a fuzzy tag query.
     *
     * @param string $q Raw query
     *
     * @return string
     */
    protected function formatFuzzyQuery($q)
    {
        // Change unescaped asterisks to percent signs to translate more common
        // wildcard character into format used by database.
        return preg_replace('/(?<!\\\\)\\*/', '%', $q);
    }

    /**
     * Return resources associated with the user tag query.
     *
     * @param bool $fuzzy Is this a fuzzy query or an exact match?
     *
     * @return array
     */
    protected function performTagSearch($fuzzy)
    {
        $query = $fuzzy
            ? $this->formatFuzzyQuery($this->getParams()->getDisplayQuery())
            : $this->getParams()->getDisplayQuery();
        $rawResults = $this->tagsService->getResourcesMatchingTagQuery(
            $query,
            null,
            $this->getParams()->getSort(),
            0,
            null,
            $fuzzy
        );

        // How many results were there?
        $this->resultTotal = count($rawResults);

        // Apply offset and limit if necessary!
        $limit = $this->getParams()->getLimit();
        if ($this->resultTotal > $limit) {
            $rawResults = $this->tagsService->getResourcesMatchingTagQuery(
                $query,
                null,
                $this->getParams()->getSort(),
                $this->getStartRecord() - 1,
                $limit,
                $fuzzy
            );
        }

        return $rawResults;
    }

    /**
     * Support method for performAndProcessSearch -- perform a search based on the
     * parameters passed to the object.
     *
     * @return void
     */
    protected function performSearch()
    {
        // There are two possibilities here: either we are in "fuzzy" mode because
        // we are coming in from a search, in which case we want to do a fuzzy
        // search that supports wildcards, or else we are coming in from a tag
        // link, in which case we want to do an exact match.
        $results = $this->performTagSearch($this->getParams()->isFuzzyTagSearch());

        // Retrieve record drivers for the selected items.
        $callback = function ($row) {
            return ['id' => $row['record_id'], 'source' => $row['source']];
        };
        $this->results = $this->recordLoader
            ->loadBatch(array_map($callback, $results), true);
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
        // Facets not supported:
        return [];
    }
}
