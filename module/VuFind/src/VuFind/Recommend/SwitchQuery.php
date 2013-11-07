<?php
/**
 * SwitchQuery Recommendations Module
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
 * @package  Recommendations
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:recommendation_modules Wiki
 */
namespace VuFind\Recommend;
use VuFind\Search\BackendManager;

/**
 * SwitchQuery Recommendations Module
 *
 * This class recommends adjusting your search query to yield better results.
 *
 * @category VuFind2
 * @package  Recommendations
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:recommendation_modules Wiki
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
     * Search backend plugin manager.
     *
     * @var BackendManager
     */
    protected $backendManager;

    /**
     * Improved query suggestions.
     *
     * @var array
     */
    protected $suggestions = array();

    /**
     * Names of checks that should be skipped. These should correspond
     * with check method names -- e.g. to skip the check found in the
     * checkWildcard() method, you would put 'wildcard' into this array.
     *
     * @var array
     */
    protected $skipChecks = array();

    /**
     * Constructor
     *
     * @param BackendManager $backendManager Search backend plugin manager
     */
    public function __construct(BackendManager $backendManager)
    {
        $this->backendManager = $backendManager;
    }

    /**
     * setConfig
     *
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
        $this->skipChecks = !empty($params[1])
            ? array_map($callback, explode(',', $params[1])) : array();
    }

    /**
     * init
     *
     * Called at the end of the Search Params objects' initFromRequest() method.
     * This method is responsible for setting search parameters needed by the
     * recommendation module and for reading any existing search parameters that may
     * be needed.
     *
     * @param \VuFind\Search\Base\Params $params  Search parameter object
     * @param \Zend\StdLib\Parameters    $request Parameter object representing user
     * request.
     *
     * @return void
     */
    public function init($params, $request)
    {
    }

    /**
     * process
     *
     * Called after the Search Results object has performed its main search.  This
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
            if (substr($method, 0, 5) == 'check') {
                $currentCheck = strtolower(substr($method, 5));
                if (!in_array($currentCheck, $this->skipChecks)) {
                    $result = $this->$method($query);
                    if ($result) {
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
        if (substr($query, 0, 3) == 'id:') {
            return true;
        }
        return false;
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
        // capitalizaton method available:
        $qb = $this->getQueryBuilder();
        if (!$qb || !isset($qb->caseSensitiveBooleans)
            || !is_callable(array($qb, 'capitalizeBooleans'))
            || !$qb->caseSensitiveBooleans
        ) {
            return false;
        }

        // Try to capitalize booleans, return new query if a change is found:
        $newQuery = $qb->capitalizeBooleans($query);
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
        $qb = $this->getQueryBuilder();
        if (!$qb || !is_callable(array($qb, 'containsBooleans'))
            || !$qb->containsBooleans($query)
            || (substr($query, 0, 1) == '"' && substr($query, -1) == '"')
        ) {
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
        return (strpos($query, '"') === false)
            ? false : str_replace('"', ' ', $query);
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
        // Don't pile wildcards on phrases:
        if (substr($query, -1) == '"') {
            return false;
        }
        $query = trim($query, ' ?');
        return (substr($query, -1) != '*') ? $query . '*' : false;
    }

    /**
     * Extract a query builder from the search backend.
     *
     * @return object
     */
    protected function getQueryBuilder()
    {
        $backend = $this->backendManager->get($this->backend);
        return is_callable(array($backend, 'getQueryBuilder'))
            ? $backend->getQueryBuilder() : false;
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
     * Get the new search handler, or false if it does not apply.
     *
     * @return array
     */
    public function getSuggestions()
    {
        return $this->suggestions;
    }
}