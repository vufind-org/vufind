<?php
/**
 * EDS Autocomplete Module
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2018.
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
 * @author   Jochen Lienhard <jochen.lienhard@ub.uni-freiburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:autosuggesters Wiki
 */
namespace VuFind\Autocomplete;

/**
 * EDS Autocomplete Module
 *
 * This class provides popular terms provided by EDS.
 *
 * @category VuFind
 * @package  Autocomplete
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Jochen Lienhard <jochen.lienhard@ub.uni-freiburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:autosuggesters Wiki
 */
class Eds implements AutocompleteInterface
{
    /**
     * Autocomplete handler
     *
     * @var string
     */
    protected $handler;

    /**
     * Search object family to use
     *
     * @var string
     */
    protected $searchClassId = 'EDS';

    /**
     * Constructor
     *
     * @param \VuFind\Search\Results\PluginManager $results Results plugin manager
     */
    public function __construct(\VuFind\Search\Results\PluginManager $results)
    {
        $this->resultsManager = $results;
    }

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
        $results = ["TEST1","TEST2","TEST3"];

        // Send back results:
        //return array_unique($results);

        // perhaps this method can look like this
        // ToDo

        if (!is_object($this->searchObject)) {
            throw new \Exception('Please set configuration first.');
        }

        try {
            // Perform the autocomplete search:
            $results = $this->searchObject->getAutocomplete($query);

        } catch (\Exception $e) {
            // Ignore errors -- just return empty results if we must.
        }
        return isset($results) ? array_unique($results) : [];
    }

    /**
     * Set parameters that affect the behavior of the autocomplete handler.
     * These values normally come from the EDS configuration file.
     *
     * @param string $params Parameters to set
     *
     * @return void
     */
    public function setConfig($params)
    {
        // Set up the Search Object:
        $this->initSearchObject();
    }

    /**
     * Initialize the search object used for finding recommendations.
     *
     * @return void
     */
    protected function initSearchObject()
    {
        // Build a new search object:
        $this->searchObject = $this->resultsManager->get($this->searchClassId);
        // ToDo: Check if this is necessary
        // $this->searchObject->getOptions()->spellcheckEnabled(false);
    }

}
