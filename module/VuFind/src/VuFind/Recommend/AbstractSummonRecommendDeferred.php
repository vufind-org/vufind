<?php

/**
 * Abstract base for deferred-load Summon recommendations modules
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2014.
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
 * @author   Lutz Biedinger <lutz.biedinger@gmail.com>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */

namespace VuFind\Recommend;

/**
 * Abstract base for deferred-load Summon recommendations modules
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Lutz Biedinger <lutz.biedigner@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */
class AbstractSummonRecommendDeferred implements RecommendInterface
{
    /**
     * Raw configuration parameters
     *
     * @var string
     */
    protected $rawParams;

    /**
     * Current search query
     *
     * @var string
     */
    protected $lookfor;

    /**
     * Configuration parameters processed for submission via AJAX
     *
     * @var string
     */
    protected $processedParams;

    /**
     * Name of module being loaded by AJAX -- MUST be set in constructor!
     *
     * @var string
     */
    protected $module = null;

    /**
     * Number of expected module parameters (from .ini config)
     *
     * @var int
     */
    protected $paramCount = 1;

    /**
     * Store the configuration of the recommendation module.
     *
     * @param string $settings Settings from searches.ini.
     *
     * @return void
     */
    public function setConfig($settings)
    {
        $this->rawParams = $settings;
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
     * @throws \Exception
     */
    public function init($params, $request)
    {
        // Validate module setting:
        if (null === $this->module) {
            throw new \Exception('Missing module property');
        }

        // Parse out parameters:
        $settings = explode(':', $this->rawParams);

        // Make sure all elements of the params array are filled in, even if just
        // with a blank string, so we can rebuild the parameters to pass through
        // AJAX later on!
        for ($i = 0; $i < $this->paramCount; $i++) {
            $settings[$i] ??= '';
        }

        // Collect the best possible search term(s):
        $lookforParam = empty($settings[0]) ? 'lookfor' : $settings[0];
        $this->lookfor = $request->get($lookforParam, '');
        if (empty($this->lookfor) && is_object($params)) {
            $this->lookfor = $params->getQuery()->getAllTerms();
        }
        $this->lookfor = trim($this->lookfor);

        // In AJAX mode, the query will always be found in the 'lookfor' parameter,
        // so override the setting:
        $settings[0] = 'lookfor';

        // Now rebuild the parameters to pass via AJAX:
        $this->processedParams = implode(':', $settings);
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
        // No action needed
    }

    /**
     * Get the URL parameters needed to make the AJAX recommendation request.
     *
     * @return string
     */
    public function getUrlParams()
    {
        return 'mod=' . urlencode($this->module)
            . '&params=' . urlencode($this->processedParams)
            . '&lookfor=' . urlencode($this->lookfor);
    }
}
