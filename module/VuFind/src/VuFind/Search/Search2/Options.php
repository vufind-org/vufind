<?php

/**
 * Search Options for second Solr index
 *
 * PHP version 8
 *
 * Copyright (C) Staats- und Universitätsbibliothek Hamburg 2018.
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
 * @package  Search_Search2
 * @author   Hajo Seng <hajo.seng@sub.uni-hamburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Search\Search2;

use VuFind\Config\ConfigManagerInterface;

/**
 * Search Options for second Solr index
 *
 * @category VuFind
 * @package  Search_Search2
 * @author   Hajo Seng <hajo.seng@sub.uni-hamburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
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
        $this->mainIni = $this->searchIni = $this->facetsIni = 'Search2';
        parent::__construct($configManager);
    }

    /**
     * Return the route name for the facet list action. Returns null to cover
     * unimplemented support.
     *
     * @return ?string
     */
    public function getFacetListAction(): ?string
    {
        return 'search2-facetlist';
    }

    /**
     * Return the route name for the versions search action. Returns null to cover
     * unimplemented support.
     *
     * @return ?string
     */
    public function getVersionsAction(): ?string
    {
        return $this->displayRecordVersions ? 'search2-versions' : null;
    }

    /**
     * Return the route name for the search results action.
     *
     * @return string
     */
    public function getSearchAction(): string
    {
        return 'search2-results';
    }

    /**
     * Return the route name of the action used for performing advanced searches.
     * Returns null if the feature is not supported.
     *
     * @return ?string
     */
    public function getAdvancedSearchAction(): ?string
    {
        return 'search2-advanced';
    }
}
