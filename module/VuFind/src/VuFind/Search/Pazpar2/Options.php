<?php

/**
 * Pazpar2 Search Options
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Search_Pazpar2
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Search\Pazpar2;

use VuFind\Config\ConfigManagerInterface;

use function is_array;

/**
 * Pazpar2 Search Options
 *
 * @category VuFind
 * @package  Search_Pazpar2
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Options extends \VuFind\Search\Base\Options
{
    /**
     * Constructor
     *
     * @param ConfigManagerInterface $configManager Config manager
     */
    public function __construct(ConfigManagerInterface $configManager)
    {
        parent::__construct($configManager);
        $this->searchIni = $this->facetsIni = 'Pazpar2';

        $this->limitOptions = [$this->defaultLimit];

        // Load source settings
        $searchSettings = $configManager->getConfigArray($this->searchIni);
        if (!empty($searchSettings['IndexSources'])) {
            foreach ($searchSettings['IndexSources'] as $k => $v) {
                $this->shards[$k] = $v;
            }
            // If we have a default from the configuration, use that...
            if (
                isset($searchSettings['SourcePreferences']['defaultChecked'])
                && !empty($searchSettings['SourcePreferences']['defaultChecked'])
            ) {
                $defaultChecked
                    = is_array($searchSettings['SourcePreferences']['defaultChecked'])
                    ? $searchSettings['SourcePreferences']['defaultChecked']
                    : [$searchSettings['SourcePreferences']['defaultChecked']];
                foreach ($defaultChecked as $current) {
                    $this->defaultSelectedShards[] = $current;
                }
            } else {
                // If no default is configured, use all sources...
                $this->defaultSelectedShards = array_keys($this->shards);
            }
            // Apply checkbox visibility setting if applicable:
            if (isset($searchSettings['SourcePreferences']['showCheckboxes'])) {
                $this->visibleShardCheckboxes
                    = $searchSettings['SourcePreferences']['showCheckboxes'];
            }
        }
    }

    /**
     * Return the route name for the search results action.
     *
     * @return string
     */
    public function getSearchAction()
    {
        return 'pazpar2-search';
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
     * Does this search option support the cart/book bag?
     *
     * @return bool
     */
    public function supportsCart()
    {
        // Not currently supported
        return false;
    }
}
