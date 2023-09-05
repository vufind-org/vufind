<?php

/**
 * OpenLibrarySubjects Recommendations Module
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
 * @author   Eoghan Ó Carragáin <eoghan.ocarragain@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */

namespace VuFind\Recommend;

use function is_object;

/**
 * OpenLibrarySubjects Recommendations Module
 *
 * This class provides recommendations by doing a search of the catalog; useful
 * for displaying catalog recommendations in other modules (i.e. Summon, Web, etc.)
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Eoghan Ó Carragáin <eoghan.ocarragain@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */
class OpenLibrarySubjectsDeferred extends OpenLibrarySubjects
{
    /**
     * Raw configuration string
     *
     * @var string
     */
    protected $rawParams;

    /**
     * Processed configuration string
     *
     * @var array
     */
    protected $processedParams;

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
        $this->requestParam = empty($settings[0]) ? 'lookfor' : $settings[0];
        $settings[0] = $this->requestParam;

        // Make sure all elements of the params array are filled in, even if just
        // with a blank string, so we can rebuild the parameters to pass through
        // AJAX later on!
        $settings[1] ??= '';

        // If Publication Date filter is to be applied, get the range and add it to
        //    $settings since the $searchObject will not be available after the AJAX
        //    call
        if (!isset($settings[2]) || empty($settings[2])) {
            $settings[2] = 'publishDate';
        }
        $pubDateRange = strtolower($settings[2]) == 'false' ?
            [] : $this->getPublishedDates($settings[2], $params, $request);
        if (!empty($pubDateRange)) {
            // Check if [Subject types] parameter has been supplied in searches.ini
            if (!isset($settings[3])) {
                $settings[3] = '';
            }
            $settings[4] = $pubDateRange;
        }

        $this->processedParams = implode(':', $settings);

        // Collect the best possible search term(s):
        $this->subject = $request->get($this->requestParam);
        if (empty($this->subject) && is_object($params)) {
            $this->subject = $params->getQuery()->getAllTerms();
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
        return 'mod=OpenLibrarySubjects&params=' . urlencode($this->processedParams)
            . '&' . urlencode($this->requestParam) . '=' . urlencode($this->subject);
    }
}
