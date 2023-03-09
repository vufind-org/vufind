<?php

/**
 * Abstract SearchObject Recommendations Module (needs to be extended to use
 * a particular search object).
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
 * @package  Recommendations
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */

namespace VuFind\Recommend;

use VuFind\Search\SearchRunner;

/**
 * Abstract SearchObject Recommendations Module (needs to be extended to use
 * a particular search object).
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */
abstract class SearchObject implements RecommendInterface
{
    /**
     * Results object
     *
     * @var \VuFind\Search\Base\Results
     */
    protected $results;

    /**
     * Number of results to show
     *
     * @var int
     */
    protected $limit;

    /**
     * Name of request parameter to use for search query
     *
     * @var string
     */
    protected $requestParam;

    /**
     * Search runner
     *
     * @var SearchRunner
     */
    protected $runner;

    /**
     * Constructor
     *
     * @param SearchRunner $runner Search runner
     */
    public function __construct(SearchRunner $runner)
    {
        $this->runner = $runner;
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
        $settings = explode(':', $settings);
        $this->requestParam = empty($settings[0]) ? 'lookfor' : $settings[0];
        $this->limit
            = (isset($settings[1]) && is_numeric($settings[1]) && $settings[1] > 0)
            ? intval($settings[1]) : 5;
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
        // See if we can determine the label for the current search type; first
        // check for an override in the GET parameters, then look at the incoming
        // params object....
        $typeLabel = $request->get('typeLabel');
        $type = $request->get('type');
        if (empty($typeLabel) && !empty($type)) {
            $typeLabel = $params->getOptions()->getLabelForBasicHandler($type);
        }

        // Extract a search query:
        $lookfor = $request->get($this->requestParam);
        if (empty($lookfor) && is_object($params)) {
            $lookfor = $params->getQuery()->getAllTerms();
        }

        // Set up the callback to initialize the parameters:
        $limit = $this->limit;
        $callback = function ($runner, $params) use ($lookfor, $limit, $typeLabel) {
            $params->setLimit($limit);
            $params->setBasicSearch(
                $lookfor,
                $params->getOptions()->getHandlerForLabel($typeLabel)
            );
        };

        // Perform the search:
        $this->results
            = $this->runner->run([], $this->getSearchClassId(), $callback);
    }

    /**
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
        // No action needed.
    }

    /**
     * Get search results.
     *
     * @return \VuFind\Search\Base\Results
     */
    public function getResults()
    {
        return $this->results;
    }

    /**
     * Get the search class ID to use for building search objects.
     *
     * @return string
     */
    abstract protected function getSearchClassId();
}
