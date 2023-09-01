<?php

/**
 * VisualFacets Recommendations Module
 *
 * PHP version 8
 *
 * Copyright (C) Julia Bauder 2014.
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
 * @author   Julia Bauder <bauderj@grinnell.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */

namespace VuFind\Recommend;

use function is_callable;

/**
 * VisualFacets Recommendations Module
 *
 * This class supports visualizing pivot facet information as a treemap or circle
 * packing visualization.
 *
 * It must be used in combination with a template file including the necessary
 * Javascript in order to display the visualization to the user.
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Julia Bauder <bauderj@grinnell.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */
class VisualFacets extends AbstractFacets
{
    /**
     * Facet configuration
     *
     * @var string
     */
    protected $facets;

    /**
     * Store the configuration of the recommendation module.
     *
     * VisualFacets:[ini section]:[ini name]
     *      Display facets listed in the specified section of the specified ini file;
     *      if [ini name] is left out, it defaults to "facets."
     *
     * @param string $settings Settings from searches.ini.
     *
     * @return void
     */
    public function setConfig($settings)
    {
        $settings = explode(':', $settings);
        $mainSection = empty($settings[0]) ? 'Visual_Settings' : $settings[0];
        $iniName = $settings[1] ?? 'facets';

        // Load the desired facet information:
        $config = $this->configLoader->get($iniName);
        $this->facets = $config->$mainSection->visual_facets
            ?? 'callnumber-first,topic_facet';
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
        // Turn on pivot facets:
        $params->setPivotFacets($this->facets);
    }

    /**
     * Get facet information taken from the search.
     *
     * @return array
     */
    public function getPivotFacetSet()
    {
        // Avoid fatal error in case of unexpected results object (e.g. EmptySet):
        return is_callable([$this->results, 'getPivotFacetList'])
            ? $this->results->getPivotFacetList() : [];
    }
}
