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
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Action\AbstractTemplateRenderingAction;
use VuFind\Action\BackendIdInterface;
use VuFind\Action\DefaultTabInterface;
use VuFind\ActionHelper\LoginHelper;
use VuFind\ActionHelper\RedirectHelper;
use VuFind\Auth\Manager as AuthManager;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Exception\ConfigException;
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
abstract class AbstractRecordAction extends AbstractTemplateRenderingAction implements
    BackendIdInterface,
    DefaultTabInterface
{
    /**
     * Array of available tab options.
     *
     * @var ?array
     */
    protected ?array $allTabs = null;

    /**
     * Default tab to display (configured at record driver level).
     *
     * Note that false is only used internally to indicate that the defaults were loaded but a default tab was not
     * specified.
     *
     * @var string|null|false
     */
    protected string|null|false $defaultTab = null;

    /**
     * Default tab to display (fallback used if no record driver configuration).
     *
     * @var ?string
     */
    protected ?string $fallbackDefaultTab = null;

    /**
     * Array of background tabs.
     *
     * @var ?array
     */
    protected ?array $backgroundTabs = null;

    /**
     * Array of extra scripts for tabs.
     *
     * @var ?array
     */
    protected ?array $tabsExtraScripts = null;

    /**
     * Type of record to display.
     *
     * @var ?string
     */
    protected ?string $sourceId = null;

    /**
     * Record driver.
     *
     * @var ?AbstractRecordDriver
     */
    protected ?AbstractRecordDriver $driver = null;

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
    public function __construct(
        protected SearchMemory $searchMemory,
        protected TabManager $tabManager,
        protected AuthManager $authManager,
        protected RecordLoader $recordLoader,
        protected RecordRouter $recordRouter,
        protected ResultScroller $resultScroller,
        #[Autowire(config: 'config')]
        protected array $config,
    ) {
        parent::__construct();
    }

    /**
     * Get backend identifier.
     *
     * @return string
     */
    public function getBackendId(): string
    {
        if (null === $this->sourceId) {
            throw new ConfigException('Backend ID not properly configured.');
        }
        return $this->sourceId;
    }

    /**
     * Set backend identifier.
     *
     * @param string $id Backend identifier
     *
     * @return static
     */
    public function setBackendId(string $id): static
    {
        $this->sourceId = $id;
        return $this;
    }

    /**
     * Get default tab.
     *
     * @return ?string
     */
    public function getDefaultTab(): ?string
    {
        // Load default tab if not already retrieved:
        if (null === $this->defaultTab) {
            $this->loadTabDetails();
        }
        return $this->defaultTab ?: null;
    }

    /**
     * Set default tab.
     *
     * @param ?string $tab Default tab
     *
     * @return static
     */
    public function setDefaultTab(?string $tab): static
    {
        $this->defaultTab = $tab;
        return $this;
    }

    /**
     * Get fallback default tab.
     *
     * @return ?string
     */
    public function getFallbackDefaultTab(): ?string
    {
        return $this->fallbackDefaultTab;
    }

    /**
     * Set fallback default tab.
     *
     * @param ?string $tab Fallback default tab
     *
     * @return static
     */
    public function setFallbackDefaultTab(?string $tab): static
    {
        $this->fallbackDefaultTab = $tab;
        return $this;
    }

    /**
     * Check that everything is in order for the action to be executed.
     *
     * May return a response or throw an exception if there are issues.
     *
     * @param ServerRequestInterface $request  Request
     * @param ResponseInterface      $response Response
     *
     * @return ?ResponseInterface
     */
    protected function checkPrerequisites(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ?ResponseInterface {
        if ($result = parent::checkPrerequisites($request, $response)) {
            return $result;
        }

        if (null === $this->sourceId) {
            $routeName = $request->getAttribute('route-match')?->getMatchedRouteName() ?? '<unknown>';
            throw new ConfigException("sourceId not properly configured for route '$routeName'");
        }

        return null;
    }

    /**
     * Create view params array.
     *
     * @param array $params Parameters
     *
     * @return array
     */
    protected function getTemplateParams(array $params = []): array
    {
        $params['driver'] = $this->loadRecord();
        $params['searchClassId'] = $params['driver']->getSearchBackendIdentifier();
        return $params;
    }

    /**
     * Load the record requested by the user; note that this is not done in the
     * init() method since we don't want to perform an expensive search twice
     * when an action forwards to another action.
     *
     * @param ?ParamBag $params Search backend parameters
     * @param bool      $force  Set to true to force a reload of the record, even if
     * already loaded (useful if loading a record using different parameters)
     *
     * @return AbstractRecordDriver
     */
    protected function loadRecord(?ParamBag $params = null, bool $force = false): AbstractRecordDriver
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
    protected function loadTabDetails(): void
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
     * Get all tab information for a given driver.
     *
     * @return array
     */
    protected function getAllTabs(): array
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
    protected function getBackgroundTabs(): array
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
    protected function getTabsExtraScripts(array $tabs): array
    {
        if (null === $this->tabsExtraScripts) {
            $this->loadTabDetails();
        }
        $allScripts = [];
        foreach (array_keys($tabs) as $tab) {
            if (!empty($this->tabsExtraScripts[$tab])) {
                $allScripts = array_merge($allScripts, $this->tabsExtraScripts[$tab]);
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
     * @return ResponseInterface
     */
    protected function showTab(string $tab, bool $ajax = false): ResponseInterface
    {
        // Special case -- handle login request (currently needed for holdings tab when driver-based holds mode is
        // enabled, but may also be useful in other circumstances):
        if (
            $this->getQueryParam('login') == 'true'
            && !$this->getUser()
        ) {
            return $this->getHelper(LoginHelper::class)->forceLogin($this->request, $this->response);
        } elseif (
            $this->getQueryParam('catalogLogin') == 'true'
            && !is_array($patron = $this->getHelper(LoginHelper::class)->catalogLogin($this->request, $this->response))
        ) {
            if (!($patron instanceof ResponseInterface)) {
                throw new \Exception('Unexpected response from LoginHelper::catalogLogin');
            }
            return $patron;
        }

        $tabs = $this->getAllTabs();
        $viewParams = $this->getTemplateParams([
            'tabs' => $tabs,
            'activeTab' => strtolower($tab),
            'defaultTab' => strtolower($this->getDefaultTab()),
            'backgroundTabs' => $this->getBackgroundTabs(),
            'tabsExtraScripts' => $this->getTabsExtraScripts($tabs),
        ]);

        // Set up next/previous record links (if appropriate)
        if ($this->searchMemory->getCurrentSearch()?->getOptions()?->resultScrollerActive()) {
            $driver = $this->loadRecord();
            $viewParams['scrollData'] = $this->resultScroller->getScrollData($driver);
        }

        $viewParams['callnumberHandler'] = $this->config['Item_Status']['callnumber_handler'] ?? false;

        return $this->renderTemplate(
            $this->request,
            $this->response,
            $viewParams,
            $this->getTabTemplate($ajax)
        );
    }

    /**
     * Get the template to use for rendering a record tab.
     *
     * @param bool $ajax Is this an AJAX tab request?
     *
     * @return string
     */
    protected function getTabTemplate(bool $ajax): string
    {
        return $ajax ? 'record/ajaxtab' : 'record/view';
    }

    /**
     * Get tab manager.
     *
     * This may be overridden e.g. to set the context.
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
     * @param string  $params Parameters to append to record URL.
     * @param ?string $tab    Record tab to display (null for default).
     *
     * @return ResponseInterface
     */
    protected function redirectToRecord(string $params = '', ?string $tab = null): ResponseInterface
    {
        $details = $this->recordRouter->getTabRouteDetails($this->loadRecord(), $tab);
        $target = $this->getRouteHelper()->getUrlFromRoute($details['route'], $details['params']);
        return $this->getHelper(RedirectHelper::class)->redirectToUrl($this->response, $target . $params);
    }
}
