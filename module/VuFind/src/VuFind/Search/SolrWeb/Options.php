<?php

/**
 * Solr Web aspect of the Search Multi-class (Options)
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2011.
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
 * @package  Search_SolrWeb
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Search\SolrWeb;

use VuFind\Config\ConfigManagerInterface;

/**
 * Solr Web Search Options
 *
 * @category VuFind
 * @package  Search_SolrWeb
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Options extends \VuFind\Search\Solr\Options
{
    /**
     * Constructor
     *
     * @param ConfigManagerInterface $configManager Config manager
     */
    public function __construct(ConfigManagerInterface $configManager)
    {
        $this->facetsIni = $this->searchIni = 'website';
        parent::__construct($configManager);
    }

    /**
     * Return the route name for the search results action.
     *
     * @return string
     */
    public function getSearchAction()
    {
        return 'web-results';
    }

    /**
     * Return the route name of the action used for performing advanced searches.
     * Returns false if the feature is not supported.
     *
     * @return string|bool
     */
    public function getAdvancedSearchAction()
    {
        // Not currently supported:
        return false;
    }

    /**
     * Return the route name for the facet list action. Returns false to cover
     * unimplemented support.
     *
     * @return string|bool
     */
    public function getFacetListAction()
    {
        return 'web-facetlist';
    }

    /**
     * Does this search option support the cart/book bag?
     *
     * @return bool
     */
    public function supportsCart()
    {
        // Not currently supported
        return false;
    }
}
