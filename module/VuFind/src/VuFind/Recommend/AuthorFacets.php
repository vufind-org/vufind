<?php

/**
 * AuthorFacets Recommendations Module
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
 * @package  Recommendations
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */

namespace VuFind\Recommend;

use Laminas\Http\Request;
use Laminas\Stdlib\Parameters;
use VuFindSearch\Query\Query;

/**
 * AuthorFacets Recommendations Module
 *
 * This class provides recommendations displaying authors on top of the page. Default
 * on author searches.
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */
class AuthorFacets implements RecommendInterface
{
    /**
     * Configuration settings
     *
     * @var string
     */
    protected $settings;

    /**
     * Search results object
     *
     * @var \VuFind\Search\Base\Results
     */
    protected $results;

    /**
     * Results plugin manager
     *
     * @var \VuFind\Search\Results\PluginManager
     */
    protected $resultsManager;

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
     * Store the configuration of the recommendation module.
     *
     * @param string $settings Settings from searches.ini.
     *
     * @return void
     */
    public function setConfig($settings)
    {
        // Save the basic parameters:
        $this->settings = $settings;
    }

    /**
     * Called before the Search Results object performs its main search
     * (specifically, in response to \VuFind\Search\SearchRunner::EVENT_CONFIGURED).
     * This method is responsible for setting search parameters needed by the
     * recommendation module and for reading any existing search parameters that may
     * be needed.
     *
     * @param \VuFind\Search\Base\Params $params  Search parameter object
     * @param Parameters                 $request Parameter object representing user
     * request.
     *
     * @return void
     */
    public function init($params, $request)
    {
        // No action needed here.
    }

    /**
     * Called after the Search Results object has performed its main search. This
     * may be used to extract necessary information from the Search Results object
     * or to perform completely unrelated processing.
     *
     * @param \VuFind\Search\Base\Results $results Search results object
     *
     * @return void
     */
    public function process($results)
    {
        $this->results = $results;
    }

    /**
     * Get results stored in the object.
     *
     * @return \VuFind\Search\Base\Results
     */
    public function getResults()
    {
        return $this->results;
    }

    /**
     * Returns search term.
     *
     * @return string
     */
    public function getSearchTerm()
    {
        $search = $this->results->getParams()->getQuery();
        return ($search instanceof Query) ? $search->getString() : '';
    }

    /**
     * Process similar authors from an author search
     *
     * @return array Facets data arrays
     */
    public function getSimilarAuthors()
    {
        // Do not provide recommendations for blank searches:
        $lookfor = $this->getSearchTerm();
        if (empty($lookfor)) {
            return ['count' => 0, 'list' => []];
        }

        // Start configuring the results object:
        $results = $this->resultsManager->get('SolrAuthorFacets');

        // Set up a special limit for the AuthorFacets search object:
        $results->getOptions()->setLimitOptions([10]);

        // Initialize object using parameters from the current Solr search object.
        $results->getParams()
            ->initFromRequest(new Parameters(['lookfor' => $lookfor]));

        // Send back the results:
        return [
            // Total authors (currently there is no way to calculate this without
            // risking out-of-memory errors or slow results, so we set this to
            // false; if we are able to find this information out in the future,
            // we can fill it in here and the templates will display it).
            'count' => false,
            'list' => $results->getResults(),
        ];
    }
}
