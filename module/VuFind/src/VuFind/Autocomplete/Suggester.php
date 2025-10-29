<?php

/**
 * Autocomplete handler plugin manager
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  Autocomplete
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:autosuggesters Wiki
 */

namespace VuFind\Autocomplete;

use Laminas\Stdlib\Parameters;
use VuFind\Config\ConfigManagerInterface;
use VuFind\Search\Options\PluginManager as OptionsManager;

use function is_callable;
use function is_object;

/**
 * Autocomplete handler plugin manager
 *
 * @category VuFind
 * @package  Autocomplete
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:autosuggesters Wiki
 */
class Suggester
{
    /**
     * Constructor
     *
     * @param PluginManager          $pluginManager  Autocomplete plugin manager
     * @param ConfigManagerInterface $configManager  Config manager
     * @param OptionsManager         $optionsManager Options manager
     */
    public function __construct(
        protected PluginManager $pluginManager,
        protected ConfigManagerInterface $configManager,
        protected OptionsManager $optionsManager
    ) {
    }

    /**
     * This returns an array of suggestions based on current request parameters.
     * This logic is present in the factory class so that it can be easily shared
     * by multiple AJAX handlers.
     *
     * @param Parameters $request    The user request
     * @param string     $typeParam  Request parameter containing search type
     * @param string     $queryParam Request parameter containing query string
     *
     * @return array
     */
    public function getSuggestions($request, $typeParam = 'type', $queryParam = 'q')
    {
        // Process incoming parameters:
        $type = $request->get($typeParam, '');
        $query = $request->get($queryParam, '');
        $searcher = $request->get('searcher', 'Solr');
        $hiddenFilters = $request->get('hiddenFilters', []);

        if (str_starts_with($type, 'VuFind:')) {
            // If we're using a combined search box, we need to override the searcher
            // and type settings.
            [, $tmp] = explode(':', $type, 2);
            [$searcher, $type] = explode('|', $tmp, 2);
        } elseif (
            str_starts_with($type, 'External:')
            && str_contains($type, '/Alphabrowse')
        ) {
            // If includeAlphaBrowse is turned on in searchbox.ini, we should use a
            // special prefix to allow configuration of alphabrowse-specific handlers
            [, $tmp] = explode('?', $type, 2);
            parse_str($tmp, $browseQuery);
            if (!empty($browseQuery['source'])) {
                $type = 'alphabrowse_' . $browseQuery['source'];
            }
        }

        // get Autocomplete_Type config
        $options = $this->optionsManager->get($searcher);
        $config = $this->configManager->getConfigArray($options->getSearchIni());
        $types = $config['Autocomplete_Types'] ?? [];

        // Figure out which handler to use:
        if (!empty($type) && isset($types[$type])) {
            $module = $types[$type];
        } else {
            $module = $config['Autocomplete']['default_handler'] ?? false;
        }

        // Get suggestions:
        if ($module) {
            if (!str_contains($module, ':')) {
                $module .= ':'; // force colon to avoid warning in explode below
            }
            [$name, $params] = explode(':', $module, 2);
            $handler = $this->pluginManager->get($name);
            $handler->setConfig($params);
        } else {
            $handler = null;
        }

        if (is_callable([$handler, 'addFilters'])) {
            $handler->addFilters($hiddenFilters);
        }

        // if the handler needs the complete request, pass it on
        if (is_callable([$handler, 'setRequest'])) {
            $handler->setRequest($request);
        }

        return is_object($handler)
            ? array_values($handler->getSuggestions($query)) : [];
    }
}
