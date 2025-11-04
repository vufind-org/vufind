<?php

/**
 * Search Options for L1 index
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2020.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Search_L1
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace Finna\Search\L1;

use VuFind\Config\ConfigManagerInterface;

/**
 * Search Options for L1 index
 *
 * @category VuFind
 * @package  Search_L1
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Options extends \Finna\Search\Solr\Options
{
    /**
     * Constructor
     *
     * @param ConfigManagerInterface $configManager Config manager
     */
    public function __construct(ConfigManagerInterface $configManager)
    {
        $this->mainIni = $this->searchIni = $this->facetsIni = 'L1';
        parent::__construct($configManager);
    }

    /**
     * Return the route name for the facet list action. Returns false to cover
     * unimplemented support.
     *
     * @return string|bool
     */
    public function getFacetListAction()
    {
        return 'l1-facetlist';
    }

    /**
     * Return the route name for the search results action.
     *
     * @return string
     */
    public function getSearchAction()
    {
        return 'l1-results';
    }

    /**
     * Return the route name of the action used for performing advanced searches.
     * Returns false if the feature is not supported.
     *
     * @return string|bool
     */
    public function getAdvancedSearchAction()
    {
        return false;
    }
}
