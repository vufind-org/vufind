<?php

/**
 * Combined search model.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2015.
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
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace Finna\Search\Combined;

use VuFind\Config\ConfigManagerInterface;

/**
 * Combined search model.
 *
 * @category VuFind
 * @package  Search_Base
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Options extends \VuFind\Search\Combined\Options
{
    use \Finna\Search\FinnaOptions;

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
        parent::__construct($configManager, $optionsManager);

        // Use Solr preference for autocomplete setting
        $searchSettings = $configManager->getConfigArray('searches');
        if (null !== ($enabled = $searchSettings['Autocomplete']['enabled'] ?? null)) {
            $this->autocompleteEnabled = $enabled;
        }
    }

    /**
     * Get tab configuration based on the full combined results configuration.
     *
     * @return array
     */
    public function getTabConfig()
    {
        $config = parent::getTabConfig();
        // Strip out additional non-tab sections of the configuration:
        unset($config['General']);
        return $config;
    }
}
