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
class Options extends \VuFind\Search\Base\Options
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
     * @var \Laminas\Config\Config
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
     * Return the view associated with this configuration
     *
     * @return string
     */
    public function getEpfView()
    {
        $viewArr = explode('|', $this->defaultView);
        return (1 < count($viewArr)) ? $viewArr[1] : $this->defaultView;
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
        if (isset($this->searchSettings->General->default_view)) {
            $this->defaultView
                = 'list|' . $this->searchSettings->General->default_view;
        }
    }
}
