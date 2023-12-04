<?php

/**
 * DOI Recommendations Module
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2016.
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

/**
 * DOI Recommendations Module
 *
 * This class directs the user to a DOI resolver when appropriate.
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */
class DOI implements RecommendInterface
{
    /**
     * DOI found in search query (or null for none)
     *
     * @var string
     */
    protected $match = null;

    /**
     * URL prefix for resolving DOIs
     *
     * @var string
     */
    protected $prefix;

    /**
     * Are we configured to redirect to the resolver when a full match is found?
     *
     * @var bool
     */
    protected $redirectFullMatch = true;

    /**
     * Does the DOI in $match exactly match the user's query?
     *
     * @var bool
     */
    protected $exact = false;

    /**
     * Store the configuration of the recommendation module.
     *
     * @param string $settings Settings from searches.ini.
     *
     * @return void
     */
    public function setConfig($settings)
    {
        // Find the last colon in the configuration that is not part of a URL:
        $breakPoint = strrpos($settings, ':');
        if ($breakPoint && substr($settings, $breakPoint + 1, 2) !== '//') {
            $prefix = substr($settings, 0, $breakPoint);
            $redirect = substr($settings, $breakPoint + 1);
        } else {
            $prefix = $settings;
            $redirect = true;       // no redirect setting; use default
        }
        $this->prefix = $prefix;
        $this->redirectFullMatch = ($redirect && strtolower($redirect) !== 'false');
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
        $query = $results->getParams()->getDisplayQuery();
        preg_match('|10\.[^\s/]{4,}/[^\s]{1,}|', $query, $matches);
        $this->match = $matches[0] ?? null;
        $this->exact = (null !== $this->match) && ($this->match === $query);
    }

    /**
     * Get the matched DOI (or null if no match found)
     *
     * @return string
     */
    public function getDOI()
    {
        return $this->match;
    }

    /**
     * Get the URL to resolve the matched DOI (or null if no match found)
     *
     * @return string
     */
    public function getURL()
    {
        return null === $this->match
            ? null : $this->prefix . urlencode($this->match);
    }

    /**
     * Is the DOI returned by getDOI a match to the user's full search query?
     *
     * @return bool
     */
    public function isFullMatch()
    {
        return $this->exact;
    }

    /**
     * Are we configured to redirect to the resolver when a full match is found?
     *
     * @return bool
     */
    public function redirectFullMatch()
    {
        return $this->redirectFullMatch;
    }
}
