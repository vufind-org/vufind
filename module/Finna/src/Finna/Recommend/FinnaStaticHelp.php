<?php

/**
 * FinnaStaticHelp Recommendation Module
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2021.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace Finna\Recommend;

/**
 * FinnaStaticHelp Recommendation Module
 *
 * This class provides a simple static help box for text with an optional link
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class FinnaStaticHelp implements \VuFind\Recommend\RecommendInterface
{
    /**
     * Settings from searches.ini
     *
     * @var array
     */
    protected $settings = [];

    /**
     * Search type
     *
     * @var string
     */
    protected $searchType = 'basic';

    /**
     * Search results
     *
     * @var \VuFind\Search\Base\Results
     */
    protected $results = null;

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
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function init($params, $request)
    {
        $this->searchType = $params->getSearchType() ?: $request->get('searchType');
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
        $this->settings = $settings;
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
     * Return current search type
     *
     * @return string
     */
    public function getSearchType()
    {
        return $this->searchType;
    }

    /**
     * Return results
     *
     * @return \VuFind\Search\Base\Results
     */
    public function getResults()
    {
        return $this->results;
    }
}
