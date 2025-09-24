<?php

/**
 * Combined search model.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2024.
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
 * @package  Search_Base
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Search\Combined;

use VuFind\Config\ConfigManagerInterface;

/**
 * Combined search model.
 *
 * @category VuFind
 * @package  Search_Base
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Options extends \VuFind\Search\Base\Options
{
    /**
     * Constructor
     *
     * @param ConfigManagerInterface               $configManager  Config loader
     * @param \VuFind\Search\Options\PluginManager $optionsManager Options plugin manager
     */
    public function __construct(
        ConfigManagerInterface $configManager,
        protected \VuFind\Search\Options\PluginManager $optionsManager
    ) {
        parent::__construct($configManager);
        $searchSettings = $this->configManager->getConfigArray('combined');
        $this->basicHandlers = $searchSettings['Basic_Searches'] ?? [];
    }

    /**
     * Return the route name for the search results action.
     *
     * @return string
     */
    public function getSearchAction()
    {
        return 'combined-results';
    }

    /**
     * Load all recommendation settings from the relevant ini file. Returns an
     * associative array where the key is the location of the recommendations (top
     * or side) and the value is the settings found in the file (which may be either
     * a single string or an array of strings).
     *
     * @param string $handler Name of handler for which to load specific settings.
     *
     * @return array associative: location (top/side/etc.) => search settings
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getRecommendationSettings($handler = null)
    {
        $recommend = [];
        $config = $this->configManager->getConfigArray('combined');
        foreach (['top', 'bottom'] as $location) {
            if (isset($config['RecommendationModules'][$location])) {
                $recommend[$location]
                    = $config['RecommendationModules'][$location];
            }
        }
        return $recommend;
    }

    /**
     * Get tab configuration based on the full combined results configuration.
     *
     * @return array
     */
    public function getTabConfig()
    {
        $config = $this->configManager->getConfigArray('combined');

        // Strip out non-tab sections of the configuration:
        unset($config['Basic_Searches']);
        unset($config['HomePage']);
        unset($config['Layout']);
        unset($config['RecommendationModules']);

        return $config;
    }

    /**
     * Does this search option support the cart/book bag?
     *
     * @return bool
     */
    public function supportsCart()
    {
        // Cart is supported if any of the tabs support cart:
        foreach (array_keys($this->getTabConfig()) as $current) {
            [$searchClassId] = explode(':', $current);
            $currentOptions = $this->optionsManager->get($searchClassId);
            if ($currentOptions->supportsCart()) {
                return true;
            }
        }
        return false;
    }
}
