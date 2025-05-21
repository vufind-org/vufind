<?php

/**
 * Primo Central Search Options
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Search_Primo
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Search\Primo;

/**
 * Primo Search Options
 *
 * @category VuFind
 * @package  Search_Primo
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Options extends \VuFind\Search\Base\Options
{
    /**
     * Advanced search operators
     *
     * @var array
     */
    protected $advancedOperators = [];

    /**
     * Constructor
     *
     * @param \VuFind\Config\PluginManager $configLoader Config loader
     */
    public function __construct(\VuFind\Config\PluginManager $configLoader)
    {
        $this->searchIni = $this->facetsIni = 'Primo';
        $this->advancedFacetSettingsSection = 'Advanced_Facet_Settings';

        // Override the default result limit with a value that we can support also with blending enabled in Primo:
        $this->defaultResultLimit = 3980;

        parent::__construct($configLoader);

        $this->highlight = !empty($this->searchSettings->General->highlighting);

        // Advanced operators:
        $this->advancedOperators = $this->searchSettings['Advanced_Operators'] ?? [];
    }

    /**
     * Return the route name for the search results action.
     *
     * @return string
     */
    public function getSearchAction()
    {
        return 'primo-search';
    }

    /**
     * Return the route name of the action used for performing advanced searches.
     * Returns false if the feature is not supported.
     *
     * @return string|bool
     */
    public function getAdvancedSearchAction()
    {
        return 'primo-advanced';
    }

    /**
     * Return the route name for the "cites" search action. Returns false to cover
     * unimplemented support.
     *
     * @return string|bool
     */
    public function getCitesAction()
    {
        return 'primo-cites';
    }

    /**
     * Return the route name for the "cited by" search action. Returns false to cover
     * unimplemented support.
     *
     * @return string|bool
     */
    public function getCitedByAction()
    {
        return 'primo-citedby';
    }

    /**
     * Basic 'getter' for Primo advanced search operators.
     *
     * @return array
     */
    public function getAdvancedOperators()
    {
        return $this->advancedOperators;
    }
}
