<?php

/**
 * RandomRecommend Recommendations Module
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2012, 2022.
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
 * @author   Luke O'Sullivan <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Recommend;

use VuFindSearch\Command\RandomCommand;

use function count;

/**
 * RandomRecommend Module
 *
 * This class provides random recommendations based on the Solr random field
 *
 * Originally developed by Luke O'Sullivan at Swansea University.
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Luke O'Sullivan <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class RandomRecommend implements RecommendInterface
{
    /**
     * Results
     *
     * @var array
     */
    protected $results;

    /**
     * Backend to use
     *
     * @var string
     */
    protected $backend = 'Solr';

    /**
     * Results Limit
     *
     * @var int
     */
    protected $limit = 10;

    /**
     * Display Mode
     *
     * @var string
     */
    protected $displayMode = 'standard';

    /**
     * Mode
     *
     * @var string
     */
    protected $mode = 'retain';

    /**
     * Result Set Minimum
     *
     * @var number
     */
    protected $minimum = 0;

    /**
     * Filters
     *
     * @var array
     */
    protected $filters = [];

    /**
     * Settings from configuration
     *
     * @var string
     */
    protected $settings;

    /**
     * Search Service
     *
     * @var \VuFindSearch\Service
     */
    protected $searchService;

    /**
     * Params manager
     *
     * @var \VuFind\Search\Params\PluginManager
     */
    protected $paramManager;

    /**
     * Constructor
     *
     * @param \VuFindSearch\Service               $searchService VuFind Search Service
     * @param \VuFind\Search\Params\PluginManager $paramManager  Params manager
     */
    public function __construct(
        \VuFindSearch\Service $searchService,
        \VuFind\Search\Params\PluginManager $paramManager
    ) {
        $this->searchService = $searchService;
        $this->paramManager = $paramManager;
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

        // Apply any settings that override the defaults by being non-empty:
        $properties = ['backend', 'limit', 'displayMode', 'mode', 'minimum'];
        $settings = explode(':', $settings);
        foreach ($properties as $i => $property) {
            if (!empty($settings[$i])) {
                $this->$property = $settings[$i];
            }
        }

        // all other params are filters and their values respectively
        for ($i = 5; $i < count($settings); $i += 2) {
            if (isset($settings[$i + 1])) {
                $this->filters[] = $settings[$i] . ':' . $settings[$i + 1];
            }
        }
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
        if ('retain' !== $this->mode) {
            $randomParams = $this->paramManager->get($params->getSearchClassId());
        } else {
            $randomParams = clone $params;
        }
        foreach ($this->filters as $filter) {
            $randomParams->addFilter($filter);
        }
        $query = $randomParams->getQuery();
        $paramBag = $randomParams->getBackendParameters();
        $command = new RandomCommand(
            $this->backend,
            $query,
            $this->limit,
            $paramBag
        );
        $this->results = $this->searchService->invoke($command)
            ->getResult()->getRecords();
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
    }

    /**
     * Get Results
     *
     * @return array
     */
    public function getResults()
    {
        if (count($this->results) < $this->minimum) {
            return [];
        }
        return $this->results;
    }

    /**
     * Get Display Mode
     *
     * @return string
     */
    public function getDisplayMode()
    {
        return $this->displayMode;
    }
}
