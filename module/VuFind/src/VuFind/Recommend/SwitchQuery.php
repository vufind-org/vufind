<?php

/**
 * SwitchQuery Recommendations Module
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

use VuFindSearch\Command\GetLuceneHelperCommand;
use VuFindSearch\Service;

use function in_array;
use function strlen;

/**
 * SwitchQuery Recommendations Module
 *
 * This class recommends adjusting your search query to yield better results.
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */
class SwitchQuery implements RecommendInterface
{
    /**
     * Search backend identifier that we are working with.
     *
     * @var string
     */
    protected $backend;

    /**
     * Search service.
     *
     * @var Service
     */
    protected $searchService;

    /**
     * Improved query suggestions.
     *
     * @var array
     */
    protected $suggestions = [];

    /**
     * Names of checks that should be skipped. These should correspond
     * with check method names -- e.g. to skip the check found in the
     * checkWildcard() method, you would put 'wildcard' into this array.
     *
     * @var array
     */
    protected $skipChecks = [];

    /**
     * List of 'opt-in' methods (others are 'opt-out' by default).
     *
     * @var array
     */
    protected $optInMethods = ['fuzzy', 'truncatechar'];

    /**
     * Search results object.
     *
     * @var \VuFind\Search\Base\Results
     */
    protected $results;

    /**
     * Constructor
     *
     * @param Service $searchService Search backend plugin manager
     */
    public function __construct(Service $searchService)
    {
        $this->searchService = $searchService;
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
        $params = explode(':', $settings);
        $this->backend = !empty($params[0]) ? $params[0] : 'Solr';
        $callback = function ($i) {
            return trim(strtolower($i));
        };
        // Get a list of "opt out" preferences from the user...
        $this->skipChecks = !empty($params[1])
            ? array_map($callback, explode(',', $params[1])) : [];
        $optIns = !empty($params[2])
            ? explode(',', $params[2]) : [];
        $this->skipChecks = array_merge(
            $this->skipChecks,
            array_diff($this->optInMethods, $optIns)
        );
    }

    /**
     * Called before the Search Results object performs its main search
     * (specifically, in response to \VuFind\Search\SearchRunner::EVENT_CONFIGURED).
     * This method is responsible for setting search parameters needed by the
     * recommendation module and for reading any existing search parameters that may
     * be needed.
     *
     * @param \VuFind\Search\Base\Params $params  Search parameter object
     * @param \Laminas\Stdlib\Parameters $request Parameter object representing user
     * request.
     *
     * @return void
     */
    public function init($params, $request)
    {
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

        // We can't currently deal with advanced searches:
        if ($this->results->getParams()->getSearchType() == 'advanced') {
            return;
        }

        // Get the query to manipulate:
        $query = $this->results->getParams()->getDisplayQuery();

        // If the query is of a type that should be skipped, go no further:
        if ($this->queryShouldBeSkipped($query)) {
            return;
        }

        // Perform all checks (based on naming convention):
        $methods = get_class_methods($this);
        foreach ($methods as $method) {
            if (str_starts_with($method, 'check')) {
                $currentCheck = strtolower(substr($method, 5));
                if (!in_array($currentCheck, $this->skipChecks)) {
                    if ($result = $this->$method($query)) {
                        $this->suggestions['switchquery_' . $currentCheck] = $result;
                    }
                }
            }
        }
    }

    /**
     * Should the query be ignored when making recommendations?
     *
     * @param string $query Query to check
     *
     * @return bool
     */
    protected function queryShouldBeSkipped($query)
    {
        // If this is an ID list query, it was probably generated by New Items,
        // Course Reserves, etc., and thus should not be further manipulated by
        // the user.
        return str_starts_with($query, 'id:');
    }

    /**
     * Will a fuzzy search help?
     *
     * @param string $query Query to check
     *
     * @return string|bool
     */
    protected function checkFuzzy($query)
    {
        // Don't stack tildes:
        if (str_contains($query, '~')) {
            return false;
        }
        $query = trim($query, ' ?*');
        // Fuzzy search only works for single keywords, not phrases:
        if (str_ends_with($query, '"')) {
            return false;
        }
        return str_ends_with($query, '~') ? false : $query . '~';
    }

    /**
     * Does the query contain lowercase boolean operators that should be uppercased?
     *
     * @param string $query Query to check
     *
     * @return string|bool
     */
    protected function checkLowercaseBools($query)
    {
        // This test only applies if booleans are case-sensitive and there is a
        // capitalization method available:
        $lh = $this->getLuceneHelper();
        if (!$lh || !$lh->hasCaseSensitiveBooleans()) {
            return false;
        }

        // Try to capitalize booleans, return new query if a change is found:
        $newQuery = $lh->capitalizeBooleans($query);
        return ($query == $newQuery) ? false : $newQuery;
    }

    /**
     * Does the query contain terms that are being treated as boolean operators,
     * perhaps unintentionally?
     *
     * @param string $query Query to check
     *
     * @return string|bool
     */
    protected function checkUnwantedBools($query)
    {
        $query = trim($query);
        $lh = $this->getLuceneHelper();
        if (!$lh || !$lh->containsBooleans($query)) {
            return false;
        }
        return '"' . addcslashes($query, '"') . '"';
    }

    /**
     * Would removing quotes help?
     *
     * @param string $query Query to check
     *
     * @return string|bool
     */
    protected function checkUnwantedQuotes($query)
    {
        // Remove escaped quotes as they are of no consequence:
        $query = str_replace('\"', ' ', $query);
        return (!str_contains($query, '"'))
            ? false : trim(str_replace('"', ' ', $query));
    }

    /**
     * Will adding a wildcard help?
     *
     * @param string $query Query to check
     *
     * @return string|bool
     */
    protected function checkWildcard($query)
    {
        $query = trim($query, ' ?~');
        // Don't pile wildcards on phrases:
        if (str_ends_with($query, '"')) {
            return false;
        }
        return !str_ends_with($query, '*') ? $query . '*' : false;
    }

    /**
     * Broaden search by truncating one character (e.g. call number)
     *
     * @param string $query Query to transform
     *
     * @return string|bool
     */
    protected function checkTruncatechar($query)
    {
        // Don't truncate phrases:
        if (str_ends_with($query, '"')) {
            return false;
        }
        $query = trim($query);
        return (strlen($query) > 1) ? substr($query, 0, -1) : false;
    }

    /**
     * Extract a Lucene syntax helper from the search backend, if possible.
     *
     * @return bool|\VuFindSearch\Backend\Solr\LuceneSyntaxHelper
     */
    protected function getLuceneHelper()
    {
        $command = new GetLuceneHelperCommand($this->backend);
        return $this->searchService->invoke($command)->getResult();
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
     * Get an array of suggestion messages.
     *
     * @return array
     */
    public function getSuggestions()
    {
        return $this->suggestions;
    }
}
