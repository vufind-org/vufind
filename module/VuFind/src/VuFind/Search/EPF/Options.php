<?php

/**
 * EPF API Options
 *
 * PHP version 8
 *
 * Copyright (C) EBSCO Industries 2013
 * Copyright (C) The National Library of Finland 2022
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
 * @package  EBSCO
 * @author   Michelle Milton <mmilton@epnet.com>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Search\EPF;

use VuFind\Search\EDS\AbstractEDSOptions;

use function count;

/**
 * EPF API Options
 *
 * @category VuFind
 * @package  EBSCO
 * @author   Michelle Milton <mmilton@epnet.com>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Options extends AbstractEDSOptions
{
    /**
     * Default view option
     *
     * @var ?string
     */
    protected $defaultView = null;

    /**
     * Search configuration
     *
     * @var \VuFind\Config\Config
     */
    protected $searchSettings;

    /**
     * Constructor
     *
     * @param \VuFind\Config\PluginManager $configLoader Configuration loader
     */
    public function __construct(
        \VuFind\Config\PluginManager $configLoader
    ) {
        $this->searchIni = $this->facetsIni = 'EPF';
        $this->searchSettings = $configLoader->get($this->searchIni);

        parent::__construct($configLoader);

        $this->setOptionsFromConfig();
    }

    /**
     * Return the route name for the search results action.
     *
     * @return string
     */
    public function getSearchAction()
    {
        return 'epf-search';
    }

    /**
     * Return the view associated with this configuration
     *
     * @return string
     */
    public function getView()
    {
        return $this->defaultView;
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

    /**
     * Load options from the configuration file.
     *
     * @return void
     */
    protected function setOptionsFromConfig()
    {
        // View preferences
        $this->initViewOptions($this->searchSettings);
    }

    /**
     * Extract a component from the defaultView API property.
     *
     * The defaultView API property takes the form vufindSetting_ebscoSetting -- the first component
     * of the underscore-delimited string is the view name used by VuFind (e.g. list or grid).
     * However, for EDS only list is suggested to be used. The second component is the format
     * requested from the EDS API (e.g. title, brief or detailed).
     *
     * @param int     $index   Index of part to extract from the property
     * @param ?string $default Default to use as a fallback if the property does not contain delimited values
     *
     * @return string
     */
    protected function getDefaultViewPart(int $index, ?string $default = null): string
    {
        $viewArr = explode('_', $this->defaultView);
        return (count($viewArr) > 1) ? $viewArr[$index] : ($default ?? $this->defaultView);
    }
}
