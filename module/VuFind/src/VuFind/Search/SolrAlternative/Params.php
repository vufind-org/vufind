<?php

/**
 * Search Params for second Solr index
 *
 * PHP version 7
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Search_SolrAlternative
 * @author   Hajo Seng <hajo.seng@sub.uni-hamburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace VuFind\Search\SolrAlternative;

use VuFindSearch\ParamBag;

/**
  * Search Params for second Solr index
 *
 * @category VuFind
 * @package  Search_SolrAlternative
 * @author   Hajo Seng <hajo.seng@sub.uni-hamburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
*/
class Params extends \VuFind\Search\Solr\Params
{
    /**
     * Create search backend parameters for advanced features.
     *
     * @return ParamBag
     */
    public function getBackendParameters()
    {
        return parent::getBackendParameters();
    }


    public function activateAllFacets($preferredSection = false)
    {
        // Based on preference, change the order of initialization to make sure
        // that preferred facet labels come in last.
        if ($preferredSection == 'Advanced') {
            $this->initHomePageFacets();
            $this->initBasicFacets();
            $this->initAdvancedFacets();
        } else {
            $this->initHomePageFacets();
            $this->initAdvancedFacets();
            $this->initBasicFacets();
        }
        $this->initCheckboxFacets();
    }

    /**
     * Initialize facet settings for the advanced search screen.
     *
     * @param string name of the facet config file
     *
     * @return void
     */

    public function initAdvancedFacets()
    {
        $this->initFacetList('Advanced', 'Advanced_Settings', 'SolrAlternative');
    }

    /**
     * Initialize facet settings for the home page.
     *
     * @param string name of the facet config file
     *
     * @return void
     */

    public function initHomePageFacets()
    {
        // Load Advanced settings if HomePage settings are missing (legacy support):
        if (!$this->initFacetList('HomePage', 'HomePage_Settings', 'SolrAlternative')) {
            $this->initAdvancedFacets();
        }
    }

    /**
     * Initialize facet settings for the standard search screen.
     *
     * @param string name of the facet config file
     *
     * @return void
     */
    public function initBasicFacets()
    {
        $this->initFacetList('ResultsTop', 'Results_Settings', 'SolrAlternative');
        $this->initFacetList('Results', 'Results_Settings', 'SolrAlternative');
    }
}
