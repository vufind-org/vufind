<?php
/**
 * Combined Search Controller
 *
 * PHP version 5
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
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->searchClassId = 'Combined';
        parent::__construct();
    }

    /**
     * Home action
     *
     * @return mixed
     */
    public function homeAction()
    {
        return $this->createViewModel();
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
        $config = $this->getServiceLocator()->get('VuFind\Config')->get('combined')
            ->toArray();
        $tabConfig = $this->getTabConfig($config);
        if (!isset($tabConfig[$sectionId])) {
            throw new \Exception('Illegal ID');
        }
        list($searchClassId) = explode(':', $sectionId);

        // Retrieve results:
        $options = $this->getServiceLocator()
            ->get('VuFind\SearchOptionsPluginManager');
        $currentOptions = $options->get($searchClassId);
        list($controller, $action)
            = explode('-', $currentOptions->getSearchAction());
        $settings = $tabConfig[$sectionId];

        $this->adjustQueryForSettings($settings);
        $settings['view'] = $this->forwardTo($controller, $action);

        // Send response:
        $response = $this->getResponse();
        $headers = $response->getHeaders();
        $headers->addHeaderLine('Content-type', 'text/html');
        $headers->addHeaderLine('Cache-Control', 'no-cache, must-revalidate');
        $headers->addHeaderLine('Expires', 'Mon, 26 Jul 1997 05:00:00 GMT');

        // Should we suppress content due to emptiness?
        if (isset($settings['hide_if_empty']) && $settings['hide_if_empty']
            && $settings['view']->results->getResultTotal() == 0
        ) {
            $html = '';
        } else {
            $cart = $this->getServiceLocator()->get('VuFind\Cart');
            $general = $this->getServiceLocator()->get('VuFind\Config')
                ->get('config');
            $viewParams = [
                'searchClassId' => $searchClassId,
                'currentSearch' => $settings,
                'showCartControls' => $currentOptions->supportsCart()
                    && $cart->isActive(),
                'showBulkOptions' => $currentOptions->supportsCart()
                    && isset($general->Site->showBulkOptions)
                    && $general->Site->showBulkOptions
            ];
            $html = $this->getViewRenderer()->render(
                'combined/results-list.phtml',
                $viewParams
            );
        }
        $response->setContent($html);
        return $response;
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
        $results = $this->getServiceLocator()->get('VuFind\SearchRunner')->run(
            $request, 'Combined', $this->getSearchSetupCallback()
        );

        // Remember the current URL, then disable memory so multi-search results
        // don't overwrite it:
        $this->rememberSearch($results);
        $this->getSearchMemory()->disable();

        // Gather combined results:
        $combinedResults = [];
        $options = $this->getServiceLocator()
            ->get('VuFind\SearchOptionsPluginManager');
        $config = $this->getServiceLocator()->get('VuFind\Config')->get('combined')
            ->toArray();
        $supportsCart = false;
        $supportsCartOptions = [];
        foreach ($this->getTabConfig($config) as $current => $settings) {
            list($searchClassId) = explode(':', $current);
            $this->adjustQueryForSettings($settings);
            $currentOptions = $options->get($searchClassId);
            $supportsCartOptions[] = $currentOptions->supportsCart();
            if ($currentOptions->supportsCart()) {
                $supportsCart = true;
            }
            list($controller, $action)
                = explode('-', $currentOptions->getSearchAction());
            $combinedResults[$current] = $settings;

            // Calculate a unique DOM id for this section of the search results;
            // $searchClassId may contain colons, which must be converted.
            $combinedResults[$current]['domId']
                = 'combined_' . str_replace(':', '____', $current);

            $combinedResults[$current]['view']
                = (!isset($settings['ajax']) || !$settings['ajax'])
                ? $this->forwardTo($controller, $action)
                : $this->createViewModel(['results' => $results]);

            // Special case: include appropriate "powered by" message:
            if (strtolower($searchClassId) == 'summon') {
                $this->layout()->poweredBy = 'Powered by Summon™ from Serials '
                    . 'Solutions, a division of ProQuest.';
            }
        }

        // Run the search to obtain recommendations:
        $results->performAndProcessSearch();

        $columns = isset($config['Layout']['columns'])
        && intval($config['Layout']['columns']) <= count($combinedResults)
            ? intval($config['Layout']['columns'])
            : count($combinedResults);
        $placement = isset($config['Layout']['stack_placement'])
            ? $config['Layout']['stack_placement']
            : 'distributed';
        if (!in_array($placement, ['distributed', 'left', 'right'])) {
            $placement = 'distributed';
        }

        // Get default config for showBulkOptions
        $settings = $this->getServiceLocator()->get('VuFind\Config')->get('config');

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
                'showBulkOptions' => isset($settings->Site->showBulkOptions)
                    && $settings->Site->showBulkOptions
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
        list($type, $target) = explode(':', $this->params()->fromQuery('type'), 2);
        switch ($type) {
        case 'VuFind':
            list($searchClassId, $type) = explode('|', $target);
            $params = $this->getRequest()->getQuery()->toArray();
            $params['type'] = $type;

            // Disable retained filters if we are switching classes!
            $activeClass = $this->params()->fromQuery('activeSearchClassId');
            if ($activeClass != $searchClassId) {
                unset($params['filter']);
            }
            unset($params['activeSearchClassId']); // don't need to pass this forward

            $route = $this->getServiceLocator()
                ->get('VuFind\SearchOptionsPluginManager')
                ->get($searchClassId)->getSearchAction();
            $base = $this->url()->fromRoute($route);
            return $this->redirect()->toUrl($base . '?' . http_build_query($params));
        case 'External':
            $lookfor = $this->params()->fromQuery('lookfor');
            return $this->redirect()->toUrl($target . urlencode($lookfor));
        default:
            // If parameters are completely missing, just redirect to home instead
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
     * @param array $settings Settings
     *
     * @return void
     */
    protected function adjustQueryForSettings($settings)
    {
        // Apply limit setting, if any:
        $query = $this->getRequest()->getQuery();
        $query->limit = isset($settings['limit']) ? $settings['limit'] : null;

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

        // Always leave noresults active (useful for 0-hit searches) and
        // side inactive (no room to display) but display or hide top based
        // on include_recommendations setting.
        if (isset($settings['include_recommendations'])
            && $settings['include_recommendations']
        ) {
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
        unset($config['Layout']);
        unset($config['RecommendationModules']);

        return $config;
    }
}
