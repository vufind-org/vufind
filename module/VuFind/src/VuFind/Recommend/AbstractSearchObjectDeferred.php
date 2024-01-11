<?php

/**
 * Abstract SearchObjectDeferred Recommendations Module (needs to be extended to use
 * a particular search object).
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2023.
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
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */

namespace VuFind\Recommend;

use function is_array;

/**
 * Abstract SearchObjectDeferred Recommendations Module (needs to be extended to use
 * a particular search object).
 *
 * This class sets up an AJAX call to trigger a call to some SearchObject implementation.
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */
abstract class AbstractSearchObjectDeferred implements RecommendInterface
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
     * Number of expected module parameters (from .ini config)
     *
     * @var int
     */
    protected $paramCount = 2;

    /**
     * Store the configuration of the recommendation module.
     *
     * @return string Module name in call to AjaxHandler
     */
    abstract protected function getAjaxModule();

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
     */
    public function init($params, $request)
    {
        // Parse out parameters:
        $settings = explode(':', $this->rawParams);

        // Make sure all elements of the params array are filled in, even if just
        // with a blank string, so we can rebuild the parameters to pass through
        // AJAX later on!
        for ($i = 0; $i < $this->paramCount; $i++) {
            $settings[$i] ??= '';
        }

        $this->initLookFor($params, $request, $settings);

        $this->processedParams = implode(':', $settings);
    }

    /**
     * Initialize the lookFor query parameter. Called from init().
     *
     * @param \VuFind\Search\Base\Params $params   Search parameter object
     * @param \Laminas\Stdlib\Parameters $request  Parameter object representing user
     * request.
     * @param array                      $settings Parameter array (passed by reference)
     *
     * @return void
     */
    protected function initLookFor($params, $request, &$settings)
    {
        // Map the user-specified query field to 'lookfor' for simplicity:
        $this->lookfor
            = $request->get(empty($settings[0]) ? 'lookfor' : $settings[0], '');
        $settings[0] = 'lookfor';

        // If lookfor has somehow been set as an array, collapse it into a string:
        if (is_array($this->lookfor)) {
            $this->lookfor = implode(' ', $this->lookfor);
        }
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
        // No action needed
    }

    /**
     * Get the URL parameters needed to make the AJAX recommendation request.
     *
     * @return string
     */
    public function getUrlParams()
    {
        return 'mod=' . urlencode($this->getAjaxModule())
            . '&params=' . urlencode($this->processedParams)
            . '&lookfor=' . urlencode($this->lookfor);
    }
}
