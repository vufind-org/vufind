<?php
/**
 * VuFind Theme Initializer
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
 * @package  Theme
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace VuFindTheme;

use Laminas\Config\Config;
use Laminas\Mvc\MvcEvent;
use Laminas\Stdlib\RequestInterface as Request;
use Laminas\View\Resolver\TemplatePathStack;
use Psr\Container\ContainerInterface;

/**
 * VuFind Theme Initializer
 *
 * @category VuFind
 * @package  Theme
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
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
     * Laminas MVC Event
     *
     * @var MvcEvent
     */
    protected $event;

    /**
     * Top-level service container
     *
     * @var \Psr\Container\ContainerInterface
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
     * A static flag used to determine if the theme has been initialized
     *
     * @var bool
     */
    protected static $themeInitialized = false;

    /**
     * Constructor
     *
     * @param Config                      $config           Configuration object
     * containing these keys:
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
     * @param MvcEvent|ContainerInterface $eventOrContainer Laminas MVC Event object
     * OR service container object
     */
    public function __construct(Config $config, $eventOrContainer)
    {
        // Store parameters:
        $this->config = $config;

        if ($eventOrContainer instanceof MvcEvent) {
            $this->event = $eventOrContainer;
            $this->serviceManager = $this->event->getApplication()
                ->getServiceManager();
        } elseif ($eventOrContainer instanceof ContainerInterface) {
            $this->event = null;
            $this->serviceManager = $eventOrContainer;
        } else {
            throw new \Exception(
                'Illegal type for $eventOrContainer: ' . get_class($eventOrContainer)
            );
        }

        // Get the cookie manager from the service manager:
        $this->cookieManager = $this->serviceManager
            ->get(\VuFind\Cookie\CookieManager::class);

        // Get base directory from tools object:
        $this->tools = $this->serviceManager->get(\VuFindTheme\ThemeInfo::class);

        // Set up mobile device detector:
        $this->mobile = $this->serviceManager->get(\VuFindTheme\Mobile::class);
        $this->mobile->enable(isset($this->config->mobile_theme));
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
        // Make sure to initialize the theme just once
        if (self::$themeInitialized) {
            return;
        }
        self::$themeInitialized = true;

        // Determine the current theme:
        $currentTheme = $this->pickTheme(
            isset($this->event) ? $this->event->getRequest() : null
        );

        // Determine theme options:
        $this->sendThemeOptionsToView();

        // Make sure the current theme is set correctly in the tools object:
        $error = null;
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
     * @param Request $request Request object (for obtaining user parameters);
     * set to null if no request context is available.
     *
     * @return string
     */
    protected function pickTheme(?Request $request)
    {
        // Load standard configuration options:
        $standardTheme = $this->config->theme;
        if (PHP_SAPI == 'cli') {
            return $standardTheme;
        }
        $mobileTheme = $this->mobile->enabled()
            ? $this->config->mobile_theme : false;

        // Find out if the user has a saved preference in the POST, URL or cookies:
        $selectedUI = null;
        if (isset($request)) {
            $selectedUI = $request->getPost()->get(
                'ui',
                $request->getQuery()->get(
                    'ui',
                    $request->getCookie()->ui ?? null
                )
            );
        }
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
        if (PHP_SAPI !== 'cli') {
            $viewModel = $this->serviceManager->get('ViewManager')->getViewModel();

            // Send down the view options:
            $viewModel->setVariable('themeOptions', $this->getThemeOptions());
        }
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
        $loader = $this->serviceManager->get('ViewHelperManager');

        // Register all the helpers:
        $config = new \Laminas\ServiceManager\Config($helpers);
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
        $resources = $this->serviceManager
            ->get(\VuFindTheme\ResourceContainer::class);

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

        // Determine doctype and apply it:
        $doctype = 'HTML5';
        foreach ($themes as $key => $currentThemeInfo) {
            if (isset($currentThemeInfo['doctype'])) {
                $doctype = $currentThemeInfo['doctype'];
                break;
            }
        }
        $loader = $this->serviceManager->get('ViewHelperManager');
        ($loader->get('doctype'))($doctype);

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

        // Inject the path stack generated above into the resolver:
        $resolver = $this->serviceManager->get(TemplatePathStack::class);
        $resolver->addPaths($templatePathStack);

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
        $theme = null;
        $pathStack = [];
        foreach (array_keys($themes) as $theme) {
            $dir = APPLICATION_PATH . '/themes/' . $theme . '/languages';
            if (is_dir($dir)) {
                $pathStack[] = $dir;
            }
        }

        if (!empty($pathStack)) {
            try {
                $translator = $this->serviceManager
                    ->get(\Laminas\Mvc\I18n\Translator::class);
                $pm = $translator->getPluginManager();
                $pm->get('ExtendedIni')->addToPathStack($pathStack);
            } catch (\Laminas\Mvc\I18n\Exception\BadMethodCallException $e) {
                // This exception likely indicates that translation is disabled,
                // so we can't proceed.
                return;
            }

            // Override the default cache with a theme-specific cache to avoid
            // key collisions in a multi-theme environment.
            try {
                $cacheManager = $this->serviceManager
                    ->get(\VuFind\Cache\Manager::class);
                $cacheName = $cacheManager->addLanguageCacheForTheme($theme);
                $translator->setCache($cacheManager->getCache($cacheName));
            } catch (\Exception $e) {
                // Don't let a cache failure kill the whole application, but make
                // note of it:
                $logger = $this->serviceManager->get(\VuFind\Log\Logger::class);
                $logger->debug(
                    'Problem loading cache: ' . get_class($e) . ' exception: '
                    . $e->getMessage()
                );
            }
        }
    }
}
