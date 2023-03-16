<?php

/**
 * Combined Search Controller
 *
 * PHP version 7
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Controller;

use Laminas\ServiceManager\ServiceLocatorInterface;
use VuFind\Search\SearchRunner;

/**
 * Redirects the user to the appropriate default VuFind action.
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class CombinedController extends AbstractSearch
{
    use AjaxResponseTrait;

    /**
     * Constructor
     *
     * @param ServiceLocatorInterface $sm Service locator
     */
    public function __construct(ServiceLocatorInterface $sm)
    {
        $this->searchClassId = 'Combined';
        parent::__construct($sm);
    }

    /**
     * Home action
     *
     * @return mixed
     */
    public function homeAction()
    {
        // We need to load blocks differently in this controller since it
        // doesn't follow the usual configuration pattern.
        $blocks = $this->serviceLocator->get(\VuFind\ContentBlock\BlockLoader::class)
            ->getFromConfig('combined');
        return $this->createViewModel(compact('blocks'));
    }

    /**
     * Single result action (used for AJAX)
     *
     * @return mixed
     */
    public function resultAction()
    {
        $this->disableSessionWrites();  // avoid session write timing bug

        // Turn off search memory -- not relevant in this context:
        $this->getSearchMemory()->disable();

        // Validate configuration:
        $sectionId = $this->params()->fromQuery('id');
        $config = $this->serviceLocator->get(\VuFind\Config\PluginManager::class)
            ->get('combined')->toArray();
        $tabConfig = $this->getTabConfig($config);
        if (!isset($tabConfig[$sectionId])) {
            throw new \Exception('Illegal ID');
        }
        [$searchClassId] = explode(':', $sectionId);

        // Retrieve results:
        $options = $this->serviceLocator
            ->get(\VuFind\Search\Options\PluginManager::class);
        $currentOptions = $options->get($searchClassId);
        [$controller, $action]
            = explode('-', $currentOptions->getSearchAction());
        $settings = $tabConfig[$sectionId];

        $this->adjustQueryForSettings(
            $settings,
            $currentOptions->getHandlerForLabel($this->params()->fromQuery('type'))
        );
        $settings['view'] = $this->forwardTo($controller, $action);

        // Should we suppress content due to emptiness?
        if (($settings['hide_if_empty'] ?? false)
            && $settings['view']->results->getResultTotal() == 0
        ) {
            $html = '';
        } else {
            $cart = $this->serviceLocator->get(\VuFind\Cart::class);
            $general = $this->serviceLocator
                ->get(\VuFind\Config\PluginManager::class)
                ->get('config');
            $viewParams = [
                'searchClassId' => $searchClassId,
                'currentSearch' => $settings,
                'showCartControls' => $currentOptions->supportsCart()
                    && $cart->isActive(),
                'showBulkOptions' => $currentOptions->supportsCart()
                    && ($general->Site->showBulkOptions ?? false)
            ];
            // Load custom CSS, if necessary:
            $html = ($this->getViewRenderer()->plugin('headLink'))();
            // Render content:
            $html .= $this->getViewRenderer()->render(
                'combined/results-list.phtml',
                $viewParams
            );
        }
        return $this->getAjaxResponse('text/html', $html);
    }

    /**
     * Results action
     *
     * @return mixed
     */
    public function resultsAction()
    {
        // Set up current request context:
        $request = $this->getRequest()->getQuery()->toArray()
            + $this->getRequest()->getPost()->toArray();
        $results = $this->serviceLocator->get(SearchRunner::class)->run(
            $request,
            'Combined',
            $this->getSearchSetupCallback()
        );

        // Remember the current URL, then disable memory so multi-search results
        // don't overwrite it:
        $this->rememberSearch($results);
        $this->getSearchMemory()->disable();

        // Gather combined results:
        $combinedResults = [];
        $options = $this->serviceLocator
            ->get(\VuFind\Search\Options\PluginManager::class);
        $config = $this->serviceLocator->get(\VuFind\Config\PluginManager::class)
            ->get('combined')->toArray();
        $supportsCart = false;
        $supportsCartOptions = [];
        // Save the initial type value, since it may get manipulated below:
        $initialType = $this->params()->fromQuery('type');
        foreach ($this->getTabConfig($config) as $current => $settings) {
            [$searchClassId] = explode(':', $current);
            $currentOptions = $options->get($searchClassId);
            $this->adjustQueryForSettings(
                $settings,
                $currentOptions->getHandlerForLabel($initialType)
            );
            $supportsCartOptions[] = $currentOptions->supportsCart();
            if ($currentOptions->supportsCart()) {
                $supportsCart = true;
            }
            [$controller, $action]
                = explode('-', $currentOptions->getSearchAction());
            $combinedResults[$current] = $settings;

            // Calculate a unique DOM id for this section of the search results;
            // $searchClassId may contain colons, which must be converted.
            $combinedResults[$current]['domId']
                = 'combined_' . str_replace(':', '____', $current);

            $permissionDenied = isset($settings['permission'])
                && !$this->permission()->isAuthorized($settings['permission']);
            $isAjax = $settings['ajax'] ?? false;
            $combinedResults[$current]['view'] = ($permissionDenied || $isAjax)
                ? $this->createViewModel(['results' => $results])
                : $this->forwardTo($controller, $action);

            // Special case: include appropriate "powered by" message:
            if (strtolower($searchClassId) == 'summon') {
                $this->layout()->poweredBy = 'Powered by Summon™ from Serials '
                    . 'Solutions, a division of ProQuest.';
            }
        }

        // Restore the initial type value to the query to prevent weird behavior:
        $this->getRequest()->getQuery()->type = $initialType;

        // Run the search to obtain recommendations:
        $results->performAndProcessSearch();

        $actualMaxColumns = count($combinedResults);
        $columnConfig = intval($config['Layout']['columns'] ?? $actualMaxColumns);
        $columns = min($columnConfig, $actualMaxColumns);
        $placement = $config['Layout']['stack_placement'] ?? 'distributed';
        if (!in_array($placement, ['distributed', 'left', 'right'])) {
            $placement = 'distributed';
        }

        // Get default config for showBulkOptions
        $settings = $this->serviceLocator->get(\VuFind\Config\PluginManager::class)
            ->get('config');

        // Build view model:
        return $this->createViewModel(
            [
                'columns' => $columns,
                'combinedResults' => $combinedResults,
                'config' => $config,
                'params' => $results->getParams(),
                'placement' => $placement,
                'results' => $results,
                'supportsCart' => $supportsCart,
                'supportsCartOptions' => $supportsCartOptions,
                'showBulkOptions' => $settings->Site->showBulkOptions ?? false
            ]
        );
    }

    /**
     * Action to process the combined search box.
     *
     * @return mixed
     */
    public function searchboxAction()
    {
        [$type, $target] = explode(':', $this->params()->fromQuery('type'), 2);
        switch ($type) {
            case 'VuFind':
                [$searchClassId, $type] = explode('|', $target);
                $params = $this->getRequest()->getQuery()->toArray();
                $params['type'] = $type;

                // Disable retained filters if we are switching classes!
                $activeClass = $this->params()->fromQuery('activeSearchClassId');
                if ($activeClass != $searchClassId) {
                    unset($params['filter']);
                }
                // We don't need to pass activeSearchClassId forward:
                unset($params['activeSearchClassId']);

                $route = $this->serviceLocator
                    ->get(\VuFind\Search\Options\PluginManager::class)
                    ->get($searchClassId)->getSearchAction();
                $base = $this->url()->fromRoute($route);
                return $this->redirect()
                    ->toUrl($base . '?' . http_build_query($params));
            case 'External':
                $lookfor = $this->params()->fromQuery('lookfor');
                $finalTarget = (false === strpos($target, '%%lookfor%%'))
                    ? $target . urlencode($lookfor)
                    : str_replace('%%lookfor%%', urlencode($lookfor), $target);
                return $this->redirect()->toUrl($finalTarget);
            default:
                // If parameters are completely missing, redirect to home instead
                // of throwing an error; this is possibly a misbehaving crawler that
                // followed the SearchBox URL without passing any parameters.
                if (empty($type) && empty($target)) {
                    return $this->redirect()->toRoute('home');
                }
                // If we have a weird value here, report it as an Exception:
                throw new \VuFind\Exception\BadRequest(
                    'Unexpected search type: "' . $type . '".'
                );
        }
    }

    /**
     * Adjust the query context to reflect the current settings.
     *
     * @param array  $settings   Settings
     * @param string $searchType Override for search handler name
     *
     * @return void
     */
    protected function adjustQueryForSettings($settings, $searchType = null)
    {
        // Apply limit setting, if any:
        $query = $this->getRequest()->getQuery();
        $query->limit = $settings['limit'] ?? null;

        // Apply filters, if any:
        $query->filter = isset($settings['filter'])
            ? (array)$settings['filter'] : null;

        // Apply hidden filters, if any:
        $query->hiddenFilters = isset($settings['hiddenFilter'])
            ? (array)$settings['hiddenFilter'] : null;

        // Apply shards, if any:
        $query->shard = isset($settings['shard'])
            ? (array)$settings['shard'] : null;

        // Reset override to avoid bleed-over from one section to the next!
        $query->recommendOverride = false;

        // Always disable 'jumpto' setting, as it does not make sense to
        // load a record view in the context of combined search.
        $query->jumpto = false;

        // Override the search type:
        $query->type = $searchType;

        // Always leave noresults active (useful for 0-hit searches) and
        // side inactive (no room to display) but display or hide top based
        // on include_recommendations setting.
        if ($settings['include_recommendations'] ?? false) {
            $query->noRecommend = 'side';
            if (is_array($settings['include_recommendations'])) {
                $query->recommendOverride
                    = ['top' => $settings['include_recommendations']];
            }
        } else {
            $query->noRecommend = 'top,side';
        }
    }

    /**
     * Get tab configuration based on the full combined results configuration.
     *
     * @param array $config Combined results configuration
     *
     * @return array
     */
    protected function getTabConfig($config)
    {
        // Strip out non-tab sections of the configuration:
        unset($config['Basic_Searches']);
        unset($config['HomePage']);
        unset($config['Layout']);
        unset($config['RecommendationModules']);

        return $config;
    }
}
