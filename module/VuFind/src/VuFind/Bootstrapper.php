<?php
/**
 * VuFind Bootstrapper
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
 * @package  Bootstrap
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace VuFind;

use Laminas\Config\Config;
use Laminas\Console\Console;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Mvc\MvcEvent;
use Laminas\Router\Http\RouteMatch;
use Laminas\View\Model\ViewModel;
use Psr\Container\ContainerInterface;
use VuFind\I18n\Locale\LocaleSettings as LocaleSettings;

/**
 * VuFind Bootstrapper
 *
 * @category VuFind
 * @package  Bootstrap
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Bootstrapper
{
    use \VuFind\I18n\Translator\LanguageInitializerTrait;

    /**
     * Main VuFind configuration
     *
     * @var Config
     */
    protected $config;

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * Current MVC event
     *
     * @var MvcEvent
     */
    protected $event;

    /**
     * Event manager
     *
     * @var EventManagerInterface
     */
    protected $events;

    /**
     * Constructor
     *
     * @param MvcEvent $event Laminas MVC Event object
     */
    public function __construct(MvcEvent $event)
    {
        $this->event = $event;
        $this->events = $event->getApplication()->getEventManager();
        $this->container = $event->getApplication()->getServiceManager();
    }

    /**
     * Bootstrap all necessary resources.
     *
     * @return void
     */
    public function bootstrap()
    {
        // automatically call all methods starting with "init":
        $methods = get_class_methods($this);
        foreach ($methods as $method) {
            if (substr($method, 0, 4) == 'init') {
                $this->$method();
            }
        }
    }

    /**
     * Set up configuration manager.
     *
     * @return void
     */
    protected function initConfig()
    {
        // Create the configuration manager:
        $app = $this->event->getApplication();
        $sm = $app->getServiceManager();
        $this->config = $sm->get(\VuFind\Config\PluginManager::class)->get('config');
    }

    /**
     * Set up cookie to flag test mode.
     *
     * @return void
     */
    protected function initTestMode()
    {
        // If we're in test mode (as determined by the config.ini property installed
        // by the build.xml startup process), set a cookie so the front-end code can
        // act accordingly. (This is needed to work around a problem where opening
        // print dialogs during testing stalls the automated test process).
        if ($this->config->System->runningTestSuite ?? false) {
            $app = $this->event->getApplication();
            $sm = $app->getServiceManager();
            $cm = $sm->get(\VuFind\Cookie\CookieManager::class);
            $cm->set('VuFindTestSuiteRunning', '1', 0, false);
        }
    }

    /**
     * If the system is offline, set up a handler to override the routing output.
     *
     * @return void
     */
    protected function initSystemStatus()
    {
        // If the system is unavailable and we're not in the console, forward to the
        // unavailable page.
        if (PHP_SAPI !== 'cli' && !($this->config->System->available ?? true)) {
            $callback = function ($e) {
                $routeMatch = new RouteMatch(
                    ['controller' => 'Error', 'action' => 'Unavailable'], 1
                );
                $routeMatch->setMatchedRouteName('error-unavailable');
                $e->setRouteMatch($routeMatch);
            };
            $this->events->attach('route', $callback);
        }
    }

    protected function initViewModel()
    {
        /** @var LocaleSettings $settings */
        $settings = $this->container->get(LocaleSettings::class);
        /** @var ViewModel $viewModel */
        $viewModel = $this->container->get('HttpViewManager')->getViewModel();
        $viewModel->setVariable('userLang', $locale = $settings->getUserLocale());
        $viewModel->setVariable('allLangs', $settings->getEnabledLanguages());
        $viewModel->setVariable('rtl', $settings->isRightToLeftLocale($locale));
    }

    /**
     * Initializes locale and timezone values
     *
     * @return void
     */
    protected function initLocaleAndTimeZone()
    {
        // Try to set the locale to UTF-8, but fail back to the exact string from
        // the config file if this doesn't work -- different systems may vary in
        // their behavior here.
        setlocale(
            LC_ALL,
            [
                "{$this->config->Site->locale}.UTF8",
                "{$this->config->Site->locale}.UTF-8",
                $this->config->Site->locale
            ]
        );
        date_default_timezone_set($this->config->Site->timezone);
    }

    /**
     * Set view variables representing the current context.
     *
     * @return void
     */
    protected function initContext()
    {
        $callback = function ($event) {
            $serviceManager = $event->getApplication()->getServiceManager();
            if (PHP_SAPI !== 'cli') {
                $viewModel = $serviceManager->get('ViewManager')->getViewModel();

                // Grab the template name from the first child -- we can use this to
                // figure out the current template context.
                $children = $viewModel->getChildren();
                if (!empty($children)) {
                    $parts = explode('/', $children[0]->getTemplate());
                    $viewModel->setVariable('templateDir', $parts[0]);
                    $viewModel->setVariable(
                        'templateName',
                        $parts[1] ?? null
                    );
                }
            }
        };
        $this->events->attach('dispatch', $callback);
    }

    /**
     * Set up headTitle view helper -- we always want to set, not append, titles.
     *
     * @return void
     */
    protected function initHeadTitle()
    {
        $callback = function ($event) {
            $serviceManager = $event->getApplication()->getServiceManager();
            $helperManager = $serviceManager->get('ViewHelperManager');
            $headTitle = $helperManager->get('headtitle');
            $headTitle->setDefaultAttachOrder(
                \Laminas\View\Helper\Placeholder\Container\AbstractContainer::SET
            );
        };
        $this->events->attach('dispatch', $callback);
    }

    /**
     * Set up theme handling.
     *
     * @return void
     */
    protected function initTheme()
    {
        // Attach remaining theme configuration to the dispatch event at high
        // priority (TODO: use priority constant once defined by framework):
        $config = $this->config->Site;
        $callback = function ($event) use ($config) {
            $theme = new \VuFindTheme\Initializer($config, $event);
            $theme->init();
        };
        $this->events->attach('dispatch.error', $callback, 9000);
        $this->events->attach('dispatch', $callback, 9000);
    }

    /**
     * Set up custom HTTP status based on exception information.
     *
     * @return void
     */
    protected function initExceptionBasedHttpStatuses()
    {
        // HTTP statuses not needed in console mode:
        if (PHP_SAPI == 'cli') {
            return;
        }

        $callback = function ($e) {
            $exception = $e->getParam('exception');
            if ($exception instanceof \VuFind\Exception\HttpStatusInterface) {
                $response = $e->getResponse();
                if (!$response) {
                    $response = new \Laminas\Http\Response();
                    $e->setResponse($response);
                }
                $response->setStatusCode($exception->getHttpStatus());
            }
        };
        $this->events->attach('dispatch.error', $callback);
    }

    /**
     * Set up search subsystem.
     *
     * @return void
     */
    protected function initSearch()
    {
        $sm     = $this->event->getApplication()->getServiceManager();
        $bm     = $sm->get(\VuFind\Search\BackendManager::class);
        $events = $sm->get('SharedEventManager');
        $events->attach('VuFindSearch', 'resolve', [$bm, 'onResolve']);
    }

    /**
     * Set up logging.
     *
     * @return void
     */
    protected function initErrorLogging()
    {
        $callback = function ($event) {
            $sm = $event->getApplication()->getServiceManager();
            if ($sm->has(\VuFind\Log\Logger::class)) {
                $log = $sm->get(\VuFind\Log\Logger::class);
                if (is_callable([$log, 'logException'])) {
                    $exception = $event->getParam('exception');
                    // Console request does not include server,
                    // so use a dummy in that case.
                    $server = (PHP_SAPI == 'cli')
                        ? new \Laminas\Stdlib\Parameters(['env' => 'console'])
                        : $event->getRequest()->getServer();
                    if (!empty($exception)) {
                        $log->logException($exception, $server);
                    }
                }
            }
        };
        $this->events->attach('dispatch.error', $callback);
        $this->events->attach('render.error', $callback);
    }

    /**
     * Set up handling for rendering problems.
     *
     * @return void
     */
    protected function initRenderErrorEvent()
    {
        // When a render.error is triggered, as a high priority, set a flag in the
        // layout that can be used to suppress actions in the layout templates that
        // might trigger exceptions -- this will greatly increase the odds of showing
        // a user-friendly message instead of a fatal error.
        $callback = function ($event) {
            $serviceManager = $event->getApplication()->getServiceManager();
            $viewModel = $serviceManager->get('ViewManager')->getViewModel();
            $viewModel->renderingError = true;
        };
        $this->events->attach('render.error', $callback, 10000);
    }

    /**
     * Set up content security policy
     *
     * @return void
     */
    protected function initContentSecurityPolicy()
    {
        if (PHP_SAPI === 'cli') {
            return;
        }
        $sm = $this->event->getApplication()->getServiceManager();
        $headers = $this->event->getResponse()->getHeaders();
        $cspHeaderGenerator = $sm->get(\VuFind\Security\CspHeaderGenerator::class);
        if ($cspHeader = $cspHeaderGenerator->getHeader()) {
            $headers->addHeader($cspHeader);
        }
    }
}
