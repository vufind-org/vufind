<?php
/**
 * FavoriteFacets Recommendations Module
 *
 * PHP version 7
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */

namespace VuFind\Recommend;

use VuFind\Search\Solr\HierarchicalFacetHelper;

/**
 * FavoriteFacets Recommendations Module
 *
 * This class provides special facets for the Favorites area (tags/lists)
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */
class FavoriteFacets extends SideFacets
{
    /**
     * Tag capability setting
     *
     * @var string
     */
    protected $tagSetting;

    /**
     * Constructor
     *
     * @param \VuFind\Config\PluginManager $configLoader Configuration loader
     * @param HierarchicalFacetHelper      $facetHelper  Helper for handling
     * hierarchical facets
     * @param string                       $tagSetting   Tag capability setting
     */
    public function __construct(
        \VuFind\Config\PluginManager $configLoader,
        HierarchicalFacetHelper $facetHelper = null,
        $tagSetting = 'enabled'
    ) {
        parent::__construct($configLoader, $facetHelper);
        $this->tagSetting = $tagSetting;
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
        // Only display tags when enabled:
        $this->mainFacets = ($this->tagSetting !== 'disabled')
            ? ['tags' => 'Your Tags'] : [];
    }
}
