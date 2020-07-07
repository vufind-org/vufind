<?php
/**
 * Primo Central Search Options
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2016.
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
 * @package  Search_Primo
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace Finna\Search\Primo;

/**
 * Primo Search Options
 *
 * @category VuFind
 * @package  Search_Primo
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Options extends \VuFind\Search\Primo\Options
{
    use \Finna\Search\FinnaOptions;

    /**
     * Date range visualization settings
     *
     * @var string
     */
    protected $dateRangeVis = '';

    /**
     * Constructor
     *
     * @param \VuFind\Config\PluginManager $configLoader Config loader
     */
    public function __construct(\VuFind\Config\PluginManager $configLoader)
    {
        parent::__construct($configLoader);

        $searchSettings = $configLoader->get($this->searchIni);
        // Load autocomplete preference:
        if (isset($searchSettings->Autocomplete->enabled)) {
            $this->autocompleteEnabled = $searchSettings->Autocomplete->enabled;
        }

        // Date range facet:
        $facetSettings = $configLoader->get($this->facetsIni);
        if (isset($facetSettings->SpecialFacets->dateRangeVis)) {
            $this->dateRangeVis = $facetSettings->SpecialFacets->dateRangeVis;
        }
    }

    /**
     * Get the field used for date range search
     *
     * @return string
     */
    public function getDateRangeSearchField()
    {
        list($field) = explode(':', $this->dateRangeVis);
        return $field;
    }

    /**
     * Get the field used for date range visualization
     *
     * @return string
     */
    public function getDateRangeVisualizationField()
    {
        $fields = explode(':', $this->dateRangeVis);
        return $fields[1] ?? '';
    }
}
