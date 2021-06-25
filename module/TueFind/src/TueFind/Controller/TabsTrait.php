<?php
/**
 * This trait is necessary because VuFind 7.0 only supports tabs in RecordController,
 * but not in the AuthorityController. There will be a discussion in one of the next
 * community calls to create a trait in a future VuFind-Version itself.
 *
 * Until then, functions are copied & slightly modified
 * from VuFind's RecordController for re-use.
 */

namespace TueFind\Controller;

trait TabsTrait
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
     * AJAX tab action -- render a tab without surrounding context.
     *
     * @return mixed
     */
    public function ajaxtabAction()
    {
        $this->loadRecord();
        // Set layout to render content only:
        $this->layout()->setTemplate('layout/lightbox');
        return $this->showTab(
            $this->params()->fromPost('tab', $this->getDefaultTab()), true
        );
    }

    /**
     * Support method to load tab information from the RecordTab PluginManager.
     *
     * @return void
     */
    protected function loadTabDetails()
    {
        $driver = $this->loadRecord();
        $request = $this->getRequest();
        $manager = $this->getRecordTabManager();
        $details = $manager
            ->getTabDetailsForRecord($driver, $request, $this->fallbackDefaultTab);
        $this->allTabs = $details['tabs'];
        $this->defaultTab = $details['default'] ? $details['default'] : false;
        $this->backgroundTabs = $manager->getBackgroundTabNames($driver);
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
        if ($this->params()->fromQuery('login', 'false') == 'true'
            && !$this->getUser()
        ) {
            return $this->forceLogin(null);
        } elseif ($this->params()->fromQuery('catalogLogin', 'false') == 'true'
            && !is_array($patron = $this->catalogLogin())
        ) {
            return $patron;
        }

        $config = $this->getConfig();

        $view = $this->createViewModel();
        $view->tabs = $this->getAllTabs();
        $view->activeTab = strtolower($tab);
        $view->defaultTab = strtolower($this->getDefaultTab());
        $view->backgroundTabs = $this->getBackgroundTabs();
        $view->loadInitialTabWithAjax
            = isset($config->Site->loadInitialTabWithAjax)
            ? (bool)$config->Site->loadInitialTabWithAjax : false;

        // Set up next/previous record links (if appropriate)
        if ($this->resultScrollerActive()) {
            $driver = $this->loadRecord();
            $view->scrollData = $this->resultScroller()->getScrollData($driver);
        }

        $view->callnumberHandler = isset($config->Item_Status->callnumber_handler)
            ? $config->Item_Status->callnumber_handler
            : false;

        // TueFind: DO NOT OVERRIDE TEMPLATE!
        //$view->setTemplate('authority/record');
        return $view;
    }
}
