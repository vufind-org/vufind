<?php

/**
 * Solr Reserves Autocomplete Module
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Autocomplete
 * @author   Tuan Nguyen <tuan@yorku.ca>
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:autosuggesters Wiki
 */

namespace VuFind\Autocomplete;

/**
 * Solr Reserves Autocomplete Module
 *
 * This class provides suggestions by using the local Solr reserves index.
 *
 * @category VuFind
 * @package  Autocomplete
 * @author   Tuan Nguyen <tuan@yorku.ca>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:autosuggesters Wiki
 */
class SolrReserves extends Solr
{
    /**
     * Constructor
     *
     * @param \VuFind\Search\Results\PluginManager $results Results plugin manager
     */
    public function __construct(\VuFind\Search\Results\PluginManager $results)
    {
        parent::__construct($results);
        $this->defaultDisplayField = 'course';
        $this->searchClassId = 'SolrReserves';
    }

    /**
     * Try to turn an array of record drivers into an array of suggestions.
     * Excluding `no_*_listed` matches since those are the translation values
     * when there is no data in that field.
     *
     * @param array  $searchResults An array of record drivers
     * @param string $query         User search query
     * @param bool   $exact         Ignore non-exact matches?
     *
     * @return array
     */
    protected function getSuggestionsFromSearch($searchResults, $query, $exact)
    {
        $results = [];
        foreach ($searchResults as $object) {
            $current = $object->getRawData();
            foreach ($this->displayField as $field) {
                if (isset($current[$field]) && !preg_match('/no_.*_listed/', $current[$field])) {
                    $bestMatch = $this->pickBestMatch(
                        $current[$field],
                        $query,
                        $exact
                    );
                    if ($bestMatch) {
                        $results[] = $bestMatch;
                        break;
                    }
                }
            }
        }
        return $results;
    }
}
