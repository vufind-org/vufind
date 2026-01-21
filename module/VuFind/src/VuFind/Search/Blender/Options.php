<?php

/**
 * Blender aspect of the Search Multi-class (Options)
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2015-2022.
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
 * @package  Search_Blender
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Search\Blender;

use VuFind\Config\ConfigManagerInterface;

/**
 * Blender Search Options
 *
 * @category VuFind
 * @package  Search_Blender
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Options extends \VuFind\Search\Solr\Options
{
    /**
     * Configuration file to read search settings from
     *
     * Note that any change to this must be made before calling the constructor of this class.
     *
     * @var string
     */
    protected string $searchIni = 'Blender';

    /**
     * Configuration file to read facet settings from
     *
     * Note that any change to this must be made before calling the constructor of this class.
     *
     * @var string
     */
    protected string $facetsIni = 'Blender';

    /**
     * The route name for the search results action.
     *
     * @var string
     */
    protected string $searchAction = 'blender-results';

    /**
     * The route name for the advanced search action.
     *
     * @var string
     */
    protected string $advancedSearchAction = 'blender-advanced';

    /**
     * Constructor
     *
     * @param ConfigManagerInterface $configManager Config manager
     */
    public function __construct(ConfigManagerInterface $configManager)
    {
        // Override the default result limit with a value that we can always support:
        $this->defaultResultLimit = 400;

        parent::__construct($configManager);

        // Make sure first-last navigation is never enabled since we cannot support it:
        $this->firstLastNavigationSupported = false;
    }

    /**
     * Return the route name for the search results action.
     *
     * @return string
     */
    public function getSearchAction(): string
    {
        return $this->searchAction;
    }

    /**
     * Return the route name of the action used for performing advanced searches.
     * Returns null if the feature is not supported.
     *
     * @return ?string
     */
    public function getAdvancedSearchAction(): ?string
    {
        return $this->advancedHandlers ? $this->advancedSearchAction : null;
    }

    /**
     * Return the route name for the facet list action. Returns null to cover
     * unimplemented support.
     *
     * @return ?string
     */
    public function getFacetListAction(): ?string
    {
        return null;
    }
}
