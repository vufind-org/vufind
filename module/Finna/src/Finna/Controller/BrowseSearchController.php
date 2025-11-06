<?php

/**
 * Browse Search Controller
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2015-2021.
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
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Kalle Pyykkönen <kalle.pyykkonen@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */

namespace Finna\Controller;

use VuFind\Exception\BadConfig as BadConfigException;
use VuFind\Exception\BadRequest as BadRequestException;
use VuFind\Search\RecommendListener;

/**
 * Redirects the user to the appropriate default VuFind action.
 *
 * @category VuFind
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Kalle Pyykkönen <kalle.pyykkonen@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class BrowseSearchController extends SearchController
{
    /**
     * Search class family to use.
     *
     * @var string
     */
    protected $searchClassId = 'SolrBrowse';

    /**
     * Should we save searches to history?
     *
     * @var bool
     */
    protected $saveToHistory = false;

    /**
     * Browse databases.
     *
     * @return mixed
     */
    public function databaseAction()
    {
        return $this->browse('Database');
    }

    /**
     * Browse journals.
     *
     * @return mixed
     */
    public function journalAction()
    {
        return $this->browse('Journal');
    }

    /**
     * Handler for database and journal browse actions.
     *
     * @param string $type Browse type
     *
     * @return mixed
     */
    protected function browse($type)
    {
        $config = $this->serviceLocator->get(\VuFind\Config\ConfigManagerInterface::class)->getConfigObject('browse');
        if (!isset($config['General'][$type]) || !$config['General'][$type]) {
            throw new BadRequestException("Browse action $type is disabled");
        }

        if (!isset($config[$type])) {
            throw new BadConfigException(
                "Missing configuration for browse action: $type"
            );
        }

        $config = $config[$type];
        $callback = function (
            $runner,
            $params,
            $searchId
        ) use (
            $config,
            $type
        ) {
            // Setup callback to attach listener if appropriate:
            $activeRecs = $this->getActiveRecommendationSettings();
            if (empty($activeRecs)) {
                return null;
            }

            $rManager = $this->serviceLocator
                ->get(\VuFind\Recommend\PluginManager::class);
            $listener = new RecommendListener($rManager, $searchId);
            $listener->setConfig(
                [
                    'side' => [
                        "SideFacets:Browse{$type}:CheckboxFacets:facets-browse",
                    ],
                ]
            );
            $listener->attach($runner->getEventManager()->getSharedManager());

            foreach ($config['filter']->toArray() ?: [] as $filter) {
                $params->addHiddenFilter($filter);
            }
            $params->getOptions()->setBrowseType(strtolower($type));
        };

        // Disable search memory for initial search (this also avoids creating
        // UrlQueryHelper with the hidden filters):
        $this->rememberSearch = false;

        // Perform search and create the view:
        $view = $this->getSearchResultsView($callback);

        $this->getViewRenderer()->plugin('headTitle')->append(
            $this->translate("browse_extended_$type") . ' '
        );

        $view->browse = strtolower($type);

        // Get rid of the hidden filters from search params so that they
        // don't linger around and cause trouble with the mechanism that
        // adds hidden filters to the searchbox for search tabs.
        $view->params->clearHiddenFilters();
        // Now remember the search:
        $this->rememberSearch = true;
        $this->rememberSearch($view->results);

        $view->setTemplate('search/results');

        return $view;
    }
}
