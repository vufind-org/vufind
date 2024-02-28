<?php

/**
 * Abstract base class for pulling Summon-specific recommendations.
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */

namespace VuFind\Recommend;

/**
 * Abstract base class for pulling Summon-specific recommendations.
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */
abstract class AbstractSummonRecommend implements RecommendInterface
{
    /**
     * Database details
     *
     * @var \VuFind\Search\Summon\Results
     */
    protected $results;

    /**
     * Request parameter to pull query from
     *
     * @var string
     */
    protected $requestParam = 'lookfor';

    /**
     * User query
     *
     * @var string
     */
    protected $lookfor;

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
        // Only one setting -- HTTP request field containing search terms (ignored
        // if $searchObject is Summon type).
        $this->requestParam = empty($settings) ? $this->requestParam : $settings;
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
        // Save search query in case we need it later:
        $this->lookfor = $request->get($this->requestParam);
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
        // If we received a Summon search object, we'll use that. If not, we need
        // to create a new Summon search object using the specified request
        // parameter for search terms.
        if ($results->getParams()->getSearchClassId() != 'Summon') {
            $results = $this->resultsManager->get('Summon');
            $this->configureSummonResults($results);
            $results->performAndProcessSearch();
        }
        $this->results = $results;
    }

    /**
     * If we have to create a new Summon results object, this method is used to
     * configure it with appropriate settings.
     *
     * @param \VuFind\Search\Summon\Results $results Search results object
     *
     * @return void
     */
    protected function configureSummonResults(\VuFind\Search\Summon\Results $results)
    {
        $results->getParams()->setBasicSearch($this->lookfor, 'AllFields');
    }

    /**
     * Get specific results needed by template.
     *
     * @return array
     */
    abstract public function getResults();
}
