<?php

/**
 * Search Params for GVI Solr index
 *
 * PHP version 8
 *
 * Copyright (C) Universitätsbibliothek Mannheim 2026.
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
 * @package  Search_GVI
 * @author   Stefan Weil <sw@weilnetz.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Search\GVI;

/**
 * Search Params for second Solr index
 *
 * @category VuFind
 * @package  Search_GVI
 * @author   Stefan Weil <sw@weilnetz.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Params extends \VuFind\Search\Solr\Params
{
    /**
     * Config sections to search for facet labels if no override configuration
     * is set.
     *
     * @var array
     */
    protected array $defaultFacetLabelSections = [
        'Advanced_Facets', 'HomePage_Facets', 'ResultsTop', 'Results',
        'ExtraFacetLabels',
    ];

    /**
     * Initialize facet settings for the advanced search screen.
     *
     * @return void
     */
    public function initAdvancedFacets(): void
    {
        $this->initFacetList('Advanced_Facets', 'Advanced_Settings');
    }

    /**
     * Create search backend parameters for advanced features.
     *
     * @return ParamBag
     */
    public function getBackendParameters()
    {
        $parameters = parent::getBackendParameters();
        if ($parameters->hasParam('sort')) {
            $sort = array_map(function($value) {
                return str_replace('publishDateSort', 'publish_date_sort', $value);
            }, $parameters->get('sort'));
            $parameters->remove('sort');
            $parameters->add('sort', $sort);
        }
        return $parameters;
    }

    /**
     * Initialize facet settings for the home page.
     *
     * @return void
     */
    public function initHomePageFacets(): void
    {
        // Load Advanced settings if HomePage settings are missing (legacy support):
        if (!$this->initFacetList('HomePage_Facets', 'HomePage_Facet_Settings')) {
            $this->initAdvancedFacets();
        }
    }
}
