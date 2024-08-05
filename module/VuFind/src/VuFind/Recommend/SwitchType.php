<?php

/**
 * SwitchType Recommendations Module
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
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */

namespace VuFind\Recommend;

/**
 * SwitchType Recommendations Module
 *
 * This class recommends switching to a different search type.
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */
class SwitchType implements RecommendInterface
{
    /**
     * Search handler to try
     *
     * @var string
     */
    protected $newHandler;

    /**
     * On-screen description of handler
     *
     * @var string
     */
    protected $newHandlerName;

    /**
     * Is this module active?
     *
     * @var bool
     */
    protected $active;

    /**
     * Results object
     *
     * @var \VuFind\Search\Base\Results
     */
    protected $results;

    /**
     * Store the configuration of the recommendation module.
     *
     * @param string $settings Settings from searches.ini.
     *
     * @return void
     */
    public function setConfig($settings)
    {
        $params = explode(':', $settings);
        $this->newHandler = !empty($params[0]) ? $params[0] : 'AllFields';
        $this->newHandlerName = $params[1] ?? 'All Fields';
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
        $handler = $results->getParams()->getSearchHandler();
        $this->results = $results;

        // If the handler is null, we can't figure out a single handler, so this
        // is probably an advanced search. In that case, we shouldn't try to change
        // anything!  We should only show recommendations if we know what handler is
        // being used and can determine that it is not the same as the new handler
        // that we want to recommend.
        $this->active = (null !== $handler && $handler != $this->newHandler);
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
     * @return string|false
     */
    public function getNewHandler()
    {
        return $this->active ? $this->newHandler : false;
    }

    /**
     * Get the description of the new search handler.
     *
     * @return string
     */
    public function getNewHandlerName()
    {
        return $this->newHandlerName;
    }
}
