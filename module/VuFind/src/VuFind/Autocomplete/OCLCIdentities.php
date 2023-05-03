<?php

/**
 * OCLC Identities Autocomplete Module
 *
 * PHP version 7
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
 * @package  Autocomplete
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:autosuggesters Wiki
 */

namespace VuFind\Autocomplete;

/**
 * OCLC Identities Autocomplete Module
 *
 * This class provides suggestions by using OCLC Identities.
 *
 * @category VuFind
 * @package  Autocomplete
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:autosuggesters Wiki
 */
class OCLCIdentities implements AutocompleteInterface
{
    /**
     * AutoSuggest base URL
     *
     * @var string
     */
    protected $url = 'http://worldcat.org/identities/AutoSuggest';

    /**
     * This method returns an array of strings matching the user's query for
     * display in the autocomplete box.
     *
     * @param string $query The user query
     *
     * @return array        The suggestions for the provided query
     */
    public function getSuggestions($query)
    {
        // Initialize return array:
        $results = [];

        // Build target URL:
        $target = $this->url . '?query=' . urlencode($query);

        // Retrieve and parse response:
        $tmp = file_get_contents($target);
        if (
            $tmp && ($json = json_decode($tmp)) && isset($json->result)
            && is_array($json->result)
        ) {
            foreach ($json->result as $current) {
                if (isset($current->term)) {
                    $results[] = $current->term;
                }
            }
        }

        // Send back results:
        return array_unique($results);
    }

    /**
     * Set parameters that affect the behavior of the autocomplete handler.
     * These values normally come from the search configuration file.
     *
     * @param string $params Parameters to set
     *
     * @return void
     */
    public function setConfig($params)
    {
        // For now, incoming parameters are ignored.
    }
}
