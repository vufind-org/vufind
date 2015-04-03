<?php
/**
 * No Autocomplete Module
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Autocomplete
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:autosuggesters Wiki
 */
namespace VuFind\Autocomplete;

/**
 * No Autocomplete Module
 *
 * This class allows autocomplete to be disabled for certain search types.
 *
 * @category VuFind2
 * @package  Autocomplete
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:autosuggesters Wiki
 */
class None implements AutocompleteInterface
{
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
        // No suggestions
        return [];
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
        // Ignore all parameters
    }
}
