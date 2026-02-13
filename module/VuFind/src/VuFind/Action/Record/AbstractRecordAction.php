<?php

/**
 * Abstract base class for record actions.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010-2024.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */

namespace VuFind\Action\Record;

use Laminas\Psr7Bridge\Psr7ServerRequest;
use stdClass;
use VuFind\Action\AbstractTemplateRenderingAction;
use VuFind\Action\Helper\LoginHelper;
use VuFind\Auth\Manager as AuthManager;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Record\Loader as RecordLoader;
use VuFind\Record\Router as RecordRouter;
use VuFind\RecordDriver\AbstractBase as AbstractRecordDriver;
use VuFind\RecordTab\TabManager;
use VuFind\Search\Memory as SearchMemory;
use VuFind\Search\ResultScroller;
use VuFind\ServiceManager\Factory\Autowire;
use VuFindSearch\ParamBag;

use function is_array;
use function is_object;

/**
 * Abstract base class for record actions.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
abstract class AbstractRecordAction extends AbstractTemplateRenderingAction
{
    /**
     * Array of available tab options
     *
     * @var array
     */
    protected $allTabs = null;

    /**
     * Default tab to display (configured at record driver level)
     *
     * @var string
     */
    protected $defaultTab = null;

    /**
     * Default tab to display (fallback used if no record driver configuration)
     *
     * @var string
     */
    protected $fallbackDefaultTab = 'Holdings';

    /**
     * Array of background tabs
     *
     * @var array
     */
    protected $backgroundTabs = null;

    /**
     * Array of extra scripts for tabs
     *
     * @var array
     */
    protected $tabsExtraScripts = null;

    /**
     * Type of record to display
     *
     * @var string
     */
    protected $sourceId = 'Solr';

    /**
     * Record driver
     *
     * @var AbstractRecordDriver
     */
    protected $driver = null;

    /**
     * Constructor.
     *
     * @param SearchMemory   $searchMemory   Search memory
     * @param TabManager     $tabManager     Tab manager
     * @param AuthManager    $authManager    Authentication manager
     * @param RecordLoader   $recordLoader   Record loader
     * @param RecordRouter   $recordRouter   Record router
     * @param ResultScroller $resultScroller Result scroller
     * @param array          $config         VuFind configuration
     */
    #[Autowire()]
    public function __construct(
        protected SearchMemory $searchMemory,
        protected TabManager $tabManager,
        protected AuthManager $authManager,
        protected RecordLoader $recordLoader,
        protected RecordRouter $recordRouter,
        protected ResultScroller $resultScroller,
        #[Autowire(config: 'config')] protected array $config,
    ) {
        parent::__construct();
    }

    /**
     * Create view params.
     *
     * @param array $params Parameters to pass to view.
     *
     * @return stdClass
     */
    protected function getViewParams(array $params = []): stdClass
    {
        $viewParams = new stdClass();
        foreach ($params as $key => $value) {
            $viewParams->$key = $value;
        }
        $viewParams->driver = $this->loadRecord();
        $viewParams->searchClassId = $viewParams->driver->getSearchBackendIdentifier();
        return $viewParams;
    }

    /**
     * Load the record requested by the user; note that this is not done in the
     * init() method since we don't want to perform an expensive search twice
     * when homeAction() forwards to another method.
     *
     * @param ?ParamBag $params Search backend parameters
     * @param bool      $force  Set to true to force a reload of the record, even if
     * already loaded (useful if loading a record using different parameters)
     *
     * @return AbstractRecordDriver
     */
    protected function loadRecord(?ParamBag $params = null, bool $force = false)
    {
        // Only load the record if it has not already been loaded. Note that
        // when determining record ID, we check both the route match (the most
        // common scenario) and the GET parameters (a fallback used by some
        // legacy routes).
        if ($force || !is_object($this->driver)) {
            $cacheContext = $this->getQueryParam('cacheContext');
            if (isset($cacheContext)) {
                $this->recordLoader->setCacheContext($cacheContext);
            }
            $this->driver = $this->recordLoader->load(
                $this->getRouteParam('id') ?? $this->getQueryParam('id'),
                $this->sourceId,
                false,
                $params
            );
        }
        return $this->driver;
    }

    /**
     * Support method to load tab information from the RecordTab PluginManager.
     *
     * @return void
     */
    protected function loadTabDetails()
    {
        $driver = $this->loadRecord();
        $manager = $this->getRecordTabManager();
        $details = $manager
            ->getTabDetailsForRecord($driver, Psr7ServerRequest::toLaminas($this->request), $this->fallbackDefaultTab);
        $this->allTabs = $details['tabs'];
        $this->defaultTab = $details['default'] ? $details['default'] : false;
        $this->backgroundTabs = $manager->getBackgroundTabNames($driver);
        $this->tabsExtraScripts = $manager->getExtraScripts();
    }

    /**
     * Get default tab for a given driver
     *
     * @return string
     */
    protected function getDefaultTab()
    {
        // Load default tab if not already retrieved:
        if (null === $this->defaultTab) {
            $this->loadTabDetails();
        }
        return $this->defaultTab;
    }

    /**
     * Get all tab information for a given driver.
     *
     * @return array
     */
    protected function getAllTabs()
    {
        if (null === $this->allTabs) {
            $this->loadTabDetails();
        }
        return $this->allTabs;
    }

    /**
     * Get names of tabs to be loaded in the background.
     *
     * @return array
     */
    protected function getBackgroundTabs()
    {
        if (null === $this->backgroundTabs) {
            $this->loadTabDetails();
        }
        return $this->backgroundTabs;
    }

    /**
     * Get extra scripts required by tabs.
     *
     * @param array $tabs Tab names to consider
     *
     * @return array
     */
    protected function getTabsExtraScripts($tabs)
    {
        if (null === $this->tabsExtraScripts) {
            $this->loadTabDetails();
        }
        $allScripts = [];
        foreach (array_keys($tabs) as $tab) {
            if (!empty($this->tabsExtraScripts[$tab])) {
                $allScripts
                    = array_merge($allScripts, $this->tabsExtraScripts[$tab]);
            }
        }
        return array_unique($allScripts);
    }

    /**
     * Display a particular tab.
     *
     * @param string $tab  Name of tab to display
     * @param bool   $ajax Are we in AJAX mode?
     *
     * @return mixed
     */
    protected function showTab($tab, $ajax = false)
    {
        // Special case -- handle login request (currently needed for holdings
        // tab when driver-based holds mode is enabled, but may also be useful
        // in other circumstances):
        if (
            $this->getQueryParam('login') == 'true'
            && !$this->getUser()
        ) {
            return $this->getHelper(LoginHelper::class)->forceLogin($this->request, $this->response);
        } elseif (
            $this->getQueryParam('catalogLogin') == 'true'
            && !is_array($patron = $this->getHelper(LoginHelper::class)->catalogLogin($this->request, $this->response))
        ) {
            return $patron;
        }

        $viewParams = $this->getViewParams();
        $viewParams->tabs = $this->getAllTabs();
        $viewParams->activeTab = strtolower($tab);
        $viewParams->defaultTab = strtolower($this->getDefaultTab());
        $viewParams->backgroundTabs = $this->getBackgroundTabs();
        $viewParams->tabsExtraScripts = $this->getTabsExtraScripts($viewParams->tabs);
        $viewParams->loadInitialTabWithAjax = (bool)($this->config['Site']['loadInitialTabWithAjax'] ?? false);

        // Set up next/previous record links (if appropriate)
        if ($this->searchMemory->getCurrentSearch()?->getOptions()?->resultScrollerActive()) {
            $driver = $this->loadRecord();
            $viewParams->scrollData = $this->resultScroller->getScrollData($driver);
        }

        $viewParams->callnumberHandler = $this->config['Item_Status']['callnumber_handler'] ?? false;

        return $this->renderTemplate(
            $this->request,
            $this->response,
            get_object_vars($viewParams),
            $ajax ? 'record/ajaxtab' : 'record/view'
        );
    }

    /**
     * Get the tab configuration for this controller.
     *
     * @return TabManager
     */
    protected function getRecordTabManager(): TabManager
    {
        return $this->tabManager;
    }

    /**
     * Get the user object if logged in, false otherwise.
     *
     * @return ?UserEntityInterface
     */
    protected function getUser(): ?UserEntityInterface
    {
        return $this->authManager->getUserObject();
    }

    /**
     * Redirect the user to the main record view.
     *
     * @param string $params Parameters to append to record URL.
     * @param string $tab    Record tab to display (null for default).
     *
     * @return mixed
     */
    protected function redirectToRecord($params = '', $tab = null)
    {
        $details = $this->recordRouter->getTabRouteDetails($this->loadRecord(), $tab);
        $target = $this->getUrlFromRoute($details['route'], $details['params']);
        return $this->getRedirectResponse($this->response, $target . $params);
    }
}
