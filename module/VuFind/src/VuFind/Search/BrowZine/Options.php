<?php

/**
 * BrowZine aspect of the Search Multi-class (Options)
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2017.
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
 * @package  Search_BrowZine
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Search\BrowZine;

use VuFind\Config\ConfigManagerInterface;

/**
 * BrowZine Search Options
 *
 * @category VuFind
 * @package  Search_BrowZine
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Options extends \VuFind\Search\Base\Options
{
    use \VuFind\Search\Options\ViewOptionsTrait;

    /**
     * Constructor
     *
     * @param ConfigManagerInterface $configManager Config manager
     */
    public function __construct(ConfigManagerInterface $configManager)
    {
        $this->facetsIni = $this->searchIni = 'BrowZine';
        parent::__construct($configManager);

        // BrowZine only supports relevance sorting:
        $this->sortOptions = ['relevance' => 'Relevance'];

        // Set up views
        $this->initViewOptions($this->searchSettings);
    }

    /**
     * Return the route name for the search results action.
     *
     * @return string
     */
    public function getSearchAction(): string
    {
        return 'browzine-search';
    }

    /**
     * Return the route name of the action used for performing advanced searches.
     * Returns null if the feature is not supported.
     *
     * @return ?string
     */
    public function getAdvancedSearchAction(): ?string
    {
        // Not currently supported:
        return null;
    }

    /**
     * Does this search option support the cart/book bag?
     *
     * @return bool
     */
    public function supportsCart(): bool
    {
        // Not currently supported
        return false;
    }
}
