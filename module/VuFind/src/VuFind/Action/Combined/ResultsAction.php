<?php

/**
 * Combined search results action.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2026.
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
 * @package  Action
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Action\Combined;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerAwareInterface;
use VuFind\ActionHelper\GlobalsHelper;
use VuFind\ActionHelper\PermissionHelper;
use VuFind\Log\LoggerAwareTrait;

use function count;
use function in_array;
use function intval;
use function is_array;

/**
 * Combined search results action.
 *
 * @category VuFind
 * @package  Action
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ResultsAction extends AbstractCombinedSearchAndResultsAction implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Display results.
     *
     * @param ServerRequestInterface $request  Server request
     * @param ResponseInterface      $response Response
     *
     * @return ResponseInterface
     */
    public function action(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        // Set up current request context:
        $requestParams = $request->getQueryParams() + $request->getParsedBody();
        $results = $this->searchRunner->run(
            $requestParams,
            'Combined',
            $this->getSearchSetupCallback($request)
        );

        // Remember the current URL:
        $this->rememberSearch($results);

        // Gather combined results:
        $combinedResults = [];
        $combinedOptions = $this->searchOptionsPluginManager->get('combined');
        // Save the initial type value, since it may get manipulated below:
        $initialType = $this->getQueryParam('type');
        foreach ($combinedOptions->getTabConfig() as $current => $currentSearch) {
            [$searchClassId] = explode(':', $current);
            try {
                $currentOptions = $this->searchOptionsPluginManager->get($searchClassId);
            } catch (\Exception $e) {
                // Prevent errors from any of the combined search results
                // from raising up to the user interface and instead just skip them
                $this->logError("Failed to get combined options for {$searchClassId}.");
                $this->logException($e);
                continue;
            }
            $adjustedRequest = $this->adjustQueryForSettings(
                $request,
                $currentSearch,
                $currentOptions->getHandlerForLabel($initialType)
            );
            $combinedResults[$current] = $currentSearch;

            // Calculate a unique DOM id for this section of the search results;
            // $searchClassId may contain colons, which must be converted.
            $combinedResults[$current]['domId'] = 'combined_' . str_replace(':', '____', $current);

            $permissionDenied = isset($currentSearch['permission'])
                && !$this->getHelper(PermissionHelper::class)->isAuthorized($currentSearch['permission']);
            $isAjax = $currentSearch['ajax'] ?? false;
            if (($permissionDenied || $isAjax)) {
                $combinedResults[$current]['templateParams'] = compact('results');
            } else {
                // Perform search and get template params:
                $combinedResults[$current]['templateParams']
                    = $this->getSearchResultsTemplateParams($adjustedRequest, $searchClassId);
                // Save to history for hidden filters etc:
                $this->saveSearchToHistory($combinedResults[$current]['templateParams']['results']);
            }

            // Special case: include appropriate "powered by" message:
            if (strtolower($searchClassId) == 'summon') {
                $this->getHelper(GlobalsHelper::class)->getContainer()['poweredBy']
                    = 'Powered by Summon™ from Serials Solutions, a division of ProQuest.';
            }
        }

        // Run the search to obtain recommendations:
        $results->performAndProcessSearch();

        $actualMaxColumns = count($combinedResults);
        $config = $this->configManager->getConfigArray('combined');
        $columnConfig = intval($config['Layout']['columns'] ?? $actualMaxColumns);
        $columns = min($columnConfig, $actualMaxColumns);
        $placement = $config['Layout']['stack_placement'] ?? 'distributed';
        if (!in_array($placement, ['distributed', 'left', 'right', 'grid'])) {
            $placement = 'distributed';
        }

        // Identify if any modules use include_recommendations_side or
        // include_recommendations_noresults_side.
        $columnSideRecommendations = [];
        foreach ($config as $subconfig) {
            foreach (['include_recommendations_side', 'include_recommendations_noresults_side'] as $type) {
                if (is_array($subconfig[$type] ?? false)) {
                    foreach ($subconfig[$type] as $recommendation) {
                        $recommendationModuleName = strtok($recommendation, ':');
                        $recommendationModule = $this->recommendPluginManager->get($recommendationModuleName);
                        $columnSideRecommendations[] = str_replace('\\', '_', $recommendationModule::class);
                    }
                }
            }
        }

        return $this->renderTemplate(
            $request,
            $response,
            $this->createTemplateParams(
                [
                    'columns' => $columns,
                    'combinedResults' => $combinedResults,
                    'config' => $config,
                    'params' => $results->getParams(),
                    'placement' => $placement,
                    'results' => $results,
                    'columnSideRecommendations' => $columnSideRecommendations,
                ]
            )
        );
    }
}
