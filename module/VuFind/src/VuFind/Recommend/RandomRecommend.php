<?php
/**
 * RandomRecommend Recommendations Module
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2012.
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
 * @author   Luke O'Sullivan (Swansea University)
 * <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFind\Recommend;

use VuFindSearch\Query\Query,
    VuFindSearch\ParamBag;

/**
 * RandomRecommend Module
 *
 * This class provides random recommendations based on the Solr random field
 *
 * @category VuFind2
 * @package  Recommendations
 * @author   Luke O'Sullivan (Swansea University)
 * <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
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
     * Results Limit
     *
     * @var number
     */
    protected $limit;

    /**
     * Display Mode
     *
     * @var string
     */
    protected $displayMode;

    /**
     * Mode
     *
     * @var string
     */
    protected $mode;

    /**
     * Result Set Minimum
     *
     * @var number
     */
    protected $minimum;

    /**
     * Filters
     *
     * @var array
     */
    protected $filters = array();

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
     * @param \VuFindSearch\Service               $searchService VuFind Search Serive
     * @param \VuFind\Search\Params\PluginManager $paramManager  Params manager
     */
    public function __construct(\VuFindSearch\Service $searchService,
        \VuFind\Search\Params\PluginManager $paramManager
    ) {
        $this->searchService = $searchService;
        $this->paramManager = $paramManager;
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
        // Save the basic parameters:
        $this->settings = $settings;

        // Parse the additional settings:
        $settings = explode(':', $settings);
        $this->backend = !empty($settings[0]) ? $settings[0] : 'Solr';
        $this->limit = isset($settings[1]) && !empty($settings[1])
            ? $settings[1] : 10;
        $this->displayMode = isset($settings[2]) && !empty($settings[2])
            ? $settings[2] : "standard";
        $this->mode = !empty($settings[3]) ? $settings[3] : 'retain';
        $this->minimum = !empty($settings[4]) ? $settings[4] : 0;

        // all other params are filters and there values respectively
        for ($i = 5; $i < count($settings); $i += 2) {
            if (isset($settings[$i+1])) {
                $this->filters[] = $settings[$i] . ':' . $settings[$i + 1];
            }
        }
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
        if ("retain" !== $this->mode) {
            $randomParams = $this->paramManager->get($params->getSearchClassId());
        } else {
            $randomParams = clone $params;
        }
        foreach ($this->filters as $filter) {
            $randomParams->addFilter($filter);
        }
        $query = $randomParams->getQuery();
        $paramBag = $randomParams->getBackendParameters();
        $this->results = $this->searchService->random(
            $this->backend, $query, $this->limit, $paramBag
        )->getRecords();
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
    }

    /**
     * Get Results
     *
     * @return array
     */
    public function getResults()
    {
        if (count($this->results) < $this->minimum) {
            return array();
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
