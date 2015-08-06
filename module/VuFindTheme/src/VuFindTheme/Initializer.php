<?php
/**
 * VuFind Theme Initializer
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Theme
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFindTheme;
use Zend\Config\Config,
    Zend\Mvc\MvcEvent,
    Zend\Stdlib\RequestInterface as Request;

/**
 * VuFind Theme Initializer
 *
 * @category VuFind2
 * @package  Theme
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Initializer
{
    /**
     * Theme configuration object
     *
     * @var Config
     */
    protected $config;

    /**
     * Zend MVC Event
     *
     * @var MvcEvent
     */
    protected $event;

    /**
     * Top-level service manager
     *
     * @var \Zend\ServiceManager\ServiceManager
     */
    protected $serviceManager;

    /**
     * Theme tools object
     *
     * @var \VuFindTheme\ThemeInfo
     */
    protected $tools;

    /**
     * Mobile interface detector
     *
     * @var \VuFindTheme\Mobile
     */
    protected $mobile;

    /**
     * Cookie manager
     *
     * @var \VuFind\Cookie\CookieManager
     */
    protected $cookieManager;

    /**
     * Constructor
     *
     * @param Config   $config Configuration object containing these keys:
     * <ul>
     *   <li>theme - the name of the default theme for non-mobile devices</li>
     *   <li>mobile_theme - the name of the default theme for mobile devices
     * (omit to disable mobile support)</li>
     *   <li>alternate_themes - a comma-separated list of alternate themes that
     * can be accessed via the ui GET parameter; each entry is a colon-separated
     * parameter-value:theme-name pair.</li>
     *   <li>selectable_themes - a comma-separated list of themes that may be
     * selected through the user interface; each entry is a colon-separated
     * name:description pair, where name may be 'standard,' 'mobile,' or one of
     * the parameter-values from the alternate_themes array.</li>
     *   <li>generator - a Generator value to display in the HTML header
     * (optional)</li>
     * </ul>
     * @param MvcEvent $event  Zend MVC Event object
     */
    public function __construct(Config $config, MvcEvent $event)
    {
        // Store parameters:
        $this->config = $config;
        $this->event = $event;

        // Grab the service manager for convenience:
        $this->serviceManager = $this->event->getApplication()->getServiceManager();

        // Get the cookie manager from the service manager:
        $this->cookieManager = $this->serviceManager->get('VuFind\CookieManager');

        // Get base directory from tools object:
        $this->tools = $this->serviceManager->get('VuFindTheme\ThemeInfo');

        // Set up mobile device detector:
        $this->mobile = $this->serviceManager->get('VuFindTheme\Mobile');
        $this->mobile->enable(isset($this->config->mobile_theme));
    }

    /**
     * Adjust template injection to a strategy that works better with our themes.
     * This needs to be called prior to the dispatch event, which is why it is a
     * separate static method rather than part of the init() method below.
     *
     * @param MvcEvent $event Dispatch event object
     *
     * @return void
     */
    public static function configureTemplateInjection(MvcEvent $event)
    {
        // Get access to the shared event manager:
        $sharedEvents
            = $event->getApplication()->getEventManager()->getSharedManager();

        // Detach the default listener:
        $listeners = $sharedEvents->getListeners(
            'Zend\Stdlib\DispatchableInterface', MvcEvent::EVENT_DISPATCH
        );
        foreach ($listeners as $listener) {
            $metadata = $listener->getMetadata();
            $callback = $listener->getCallback();
            if (is_a($callback[0], 'Zend\Mvc\View\Http\InjectTemplateListener')) {
                $priority = $metadata['priority'];
                $sharedEvents->detach(
                    'Zend\Stdlib\DispatchableInterface', $listener
                );
                break;
            }
        }

        // If we didn't successfully detach a listener above, priority will not be
        // set.  This is an unexpected situation, so we should throw an exception.
        if (!isset($priority)) {
            throw new \Exception('Unable to detach InjectTemplateListener');
        }

        // Attach our own listener in place of the one we removed:
        $injectTemplateListener  = new InjectTemplateListener();
        $sharedEvents->attach(
            'Zend\Stdlib\DispatchableInterface', MvcEvent::EVENT_DISPATCH,
            [$injectTemplateListener, 'injectTemplate'], $priority
        );
    }

    /**
     * Initialize the theme.  This needs to be triggered as part of the dispatch
     * event.
     *
     * @throws \Exception
     * @return void
     */
    public function init()
    {
        // Determine the current theme:
        $currentTheme = $this->pickTheme($this->event->getRequest());

        // Determine theme options:
        $this->sendThemeOptionsToView();

        // Make sure the current theme is set correctly in the tools object:
        try {
            $this->tools->setTheme($currentTheme);
        } catch (\Exception $error) {
            // If an illegal value is passed in, the setter may throw an exception.
            // We should ignore it for now and throw it after we have set up the
            // theme (the setter will use a safe value instead of the illegal one).
        }

        // Using the settings we initialized above, actually configure the themes; we
        // need to do this even if there is an error, since we need a theme in order
        // to display an error message!
        $this->setUpThemes(array_reverse($this->tools->getThemeInfo()));

        // If we encountered an error loading theme settings, fail now.
        if (isset($error)) {
            throw new \Exception($error->getMessage());
        }
    }

    /**
     * Support method for init() -- figure out which theme option is active.
     *
     * @param Request $request Request object (for obtaining user parameters).
     *
     * @return string
     */
    protected function pickTheme(Request $request)
    {
        // Load standard configuration options:
        $standardTheme = $this->config->theme;
        $mobileTheme = $this->mobile->enabled()
            ? $this->config->mobile_theme : false;

        // Find out if the user has a saved preference in the POST, URL or cookies:
        $selectedUI = $request->getPost()->get(
            'ui', $request->getQuery()->get(
                'ui', isset($request->getCookie()->ui)
                ? $request->getCookie()->ui : null
            )
        );
        if (empty($selectedUI)) {
            $selectedUI = ($mobileTheme && $this->mobile->detect())
                ? 'mobile' : 'standard';
        }

        // Save the current setting to a cookie so it persists:
        $this->cookieManager->set('ui', $selectedUI);

        // Do we have a valid mobile selection?
        if ($mobileTheme && $selectedUI == 'mobile') {
            return $mobileTheme;
        }

        // Do we have a non-standard selection?
        if ($selectedUI != 'standard'
            && isset($this->config->alternate_themes)
        ) {
            // Check the alternate theme settings for a match:
            $parts = explode(',', $this->config->alternate_themes);
            foreach ($parts as $part) {
                $subparts = explode(':', $part);
                if ((trim($subparts[0]) == trim($selectedUI))
                    && isset($subparts[1]) && !empty($subparts[1])
                ) {
                    return $subparts[1];
                }
            }
        }

        // If we got this far, we either have a standard option or the user chose
        // an invalid non-standard option; either way, we need to default to the
        // standard theme:
        return $standardTheme;
    }

    /**
     * Make the theme options available to the view.
     *
     * @return void
     */
    protected function sendThemeOptionsToView()
    {
        // Get access to the view model:
        $viewModel = $this->serviceManager->get('viewmanager')->getViewModel();

        // Send down the view options:
        $viewModel->setVariable('themeOptions', $this->getThemeOptions());
    }

    /**
     * Return an array of information about user-selectable themes.  Each entry in
     * the array is an associative array with 'name', 'desc' and 'selected' keys.
     *
     * @return array
     */
    protected function getThemeOptions()
    {
        $options = [];
        if (isset($this->config->selectable_themes)) {
            $parts = explode(',', $this->config->selectable_themes);
            foreach ($parts as $part) {
                $subparts = explode(':', $part);
                $name = trim($subparts[0]);
                $desc = isset($subparts[1]) ? trim($subparts[1]) : '';
                $desc = empty($desc) ? $name : $desc;
                if (!empty($name)) {
                    $options[] = [
                        'name' => $name, 'desc' => $desc,
                        'selected' => ($this->cookieManager->get('ui') == $name)
                    ];
                }
            }
        }
        return $options;
    }

    /**
     * Support method for setUpThemes -- register view helpers.
     *
     * @param array $helpers Helper settings
     *
     * @return void
     */
    protected function setUpThemeViewHelpers($helpers)
    {
        // Grab the helper loader from the view manager:
        $loader = $this->serviceManager->get('viewmanager')->getHelperManager();

        // Register all the helpers:
        $config = new \Zend\ServiceManager\Config($helpers);
        $config->configureServiceManager($loader);
    }

    /**
     * Support method for init() -- set up theme once current settings are known.
     *
     * @param array $themes Theme configuration information.
     *
     * @return void
     */
    protected function setUpThemes($themes)
    {
        $templatePathStack = [];

        // Grab the resource manager for tracking CSS, JS, etc.:
        $resources = $this->serviceManager->get('VuFindTheme\ResourceContainer');

        // Set generator if necessary:
        if (isset($this->config->generator)) {
            $resources->setGenerator($this->config->generator);
        }

        $lessActive = false;
        // Find LESS activity
        foreach ($themes as $key => $currentThemeInfo) {
            if (isset($currentThemeInfo['less']['active'])) {
                $lessActive = $currentThemeInfo['less']['active'];
            }
        }

        // Apply the loaded theme settings in reverse for proper inheritance:
        foreach ($themes as $key => $currentThemeInfo) {
            if (isset($currentThemeInfo['helpers'])) {
                $this->setUpThemeViewHelpers($currentThemeInfo['helpers']);
            }

            // Add template path:
            $templatePathStack[] = $this->tools->getBaseDir() . "/$key/templates";

            // Add CSS and JS dependencies:
            if ($lessActive && isset($currentThemeInfo['less'])) {
                $resources->addLessCss($currentThemeInfo['less']);
            }
            if (isset($currentThemeInfo['css'])) {
                $resources->addCss($currentThemeInfo['css']);
            }
            if (isset($currentThemeInfo['js'])) {
                $resources->addJs($currentThemeInfo['js']);
            }

            // Select encoding:
            if (isset($currentThemeInfo['encoding'])) {
                $resources->setEncoding($currentThemeInfo['encoding']);
            }

            // Select favicon:
            if (isset($currentThemeInfo['favicon'])) {
                $resources->setFavicon($currentThemeInfo['favicon']);
            }
        }

        // Inject the path stack generated above into the view resolver:
        $resolver = $this->serviceManager->get('viewmanager')->getResolver();
        if (!is_a($resolver, 'Zend\View\Resolver\AggregateResolver')) {
            throw new \Exception('Unexpected resolver: ' . get_class($resolver));
        }
        foreach ($resolver as $current) {
            if (is_a($current, 'Zend\View\Resolver\TemplatePathStack')) {
                $current->setPaths($templatePathStack);
            }
        }

        // Add theme specific language files for translation
        $this->updateTranslator($themes);
    }

    /**
     * Support method for setUpThemes() - add theme specific language files for
     * translation.
     *
     * @param array $themes Theme configuration information.
     *
     * @return void
     */
    protected function updateTranslator($themes)
    {
        $pathStack = [];
        foreach (array_keys($themes) as $theme) {
            $dir = APPLICATION_PATH . '/themes/' . $theme . '/languages';
            if (is_dir($dir)) {
                $pathStack[] = $dir;
            }
        }

        if (!empty($pathStack)) {
            try {
                $translator = $this->serviceManager->get('VuFind\Translator');

                $pm = $translator->getPluginManager();
                $pm->get('extendedini')->addToPathStack($pathStack);
            } catch (\Zend\Mvc\Exception\BadMethodCallException $e) {
                // This exception likely indicates that translation is disabled,
                // so we can't proceed.
                return;
            }

            // Override the default cache with a theme-specific cache to avoid
            // key collisions in a multi-theme environment.
            try {
                $cacheManager = $this->serviceManager->get('VuFind\CacheManager');
                $cacheName = $cacheManager->addLanguageCacheForTheme($theme);
                $translator->setCache($cacheManager->getCache($cacheName));
            } catch (\Exception $e) {
                // Don't let a cache failure kill the whole application, but make
                // note of it:
                $logger = $this->serviceManager->get('VuFind\Logger');
                $logger->debug(
                    'Problem loading cache: ' . get_class($e) . ' exception: '
                    . $e->getMessage()
                );
            }
        }
    }
}
