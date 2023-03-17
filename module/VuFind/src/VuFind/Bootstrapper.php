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

use Laminas\Mvc\MvcEvent;
use Laminas\Router\Http\RouteMatch;
use Psr\Container\ContainerInterface;
use VuFind\I18n\Locale\LocaleSettings;

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
    /**
     * Main VuFind configuration
     *
     * @var \Laminas\Config\Config
     */
    protected $config;

    /**
     * Service manager
     *
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
     * @var \Laminas\EventManager\EventManagerInterface
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
        $app = $event->getApplication();
        $this->events = $app->getEventManager();
        $this->container = $app->getServiceManager();
        $this->config = $this->container->get(\VuFind\Config\PluginManager::class)
            ->get('config');
    }

    /**
     * Bootstrap all necessary resources.
     *
     * @return void
     */
    public function bootstrap(): void
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
     * Set up cookie to flag test mode.
     *
     * @return void
     */
    protected function initTestMode(): void
    {
        // If we're in test mode (as determined by the config.ini property installed
        // by the build.xml startup process), set a cookie so the front-end code can
        // act accordingly. (This is needed to work around a problem where opening
        // print dialogs during testing stalls the automated test process).
        if ($this->config->System->runningTestSuite ?? false) {
            $cm = $this->container->get(\VuFind\Cookie\CookieManager::class);
            $cm->set('VuFindTestSuiteRunning', '1', 0, false);
        }
    }

    /**
     * If the system is offline, set up a handler to override the routing output.
     *
     * @return void
     */
    protected function initSystemStatus(): void
    {
        // If the system is unavailable and we're not in the console, forward to the
        // unavailable page.
        if (PHP_SAPI !== 'cli' && !($this->config->System->available ?? true)) {
            $callback = function ($e) {
                $routeMatch = new RouteMatch(
                    ['controller' => 'Error', 'action' => 'Unavailable'],
                    1
                );
                $routeMatch->setMatchedRouteName('error-unavailable');
                $e->setRouteMatch($routeMatch);
            };
            $this->events->attach('route', $callback);
        }
    }

    /**
     * Initializes locale and timezone values
     *
     * @return void
     */
    protected function initLocaleAndTimeZone(): void
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
    protected function initContext(): void
    {
        $callback = function ($event) {
            if (PHP_SAPI !== 'cli') {
                $viewModel = $this->container->get('ViewManager')->getViewModel();

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
     * Set up the initial view model.
     *
     * @return void
     */
    protected function initViewModel(): void
    {
        $settings = $this->container->get(LocaleSettings::class);
        $locale = $settings->getUserLocale();
        $viewModel = $this->container->get('HttpViewManager')->getViewModel();
        $viewModel->setVariable('userLang', $locale);
        $viewModel->setVariable('allLangs', $settings->getEnabledLocales());
        $viewModel->setVariable('rtl', $settings->isRightToLeftLocale($locale));
    }

    /**
     * Update language in user account, as needed.
     *
     * @return void
     */
    protected function initUserLanguage(): void
    {
        // Store last selected language in user account, if applicable:
        $settings = $this->container->get(LocaleSettings::class);
        $language = $settings->getUserLocale();
        $authManager = $this->container->get(\VuFind\Auth\Manager::class);
        if (
            ($user = $authManager->isLoggedIn())
            && $user->last_language != $language
        ) {
            $user->updateLastLanguage($language);
        }
    }

    /**
     * Set up theme handling.
     *
     * @return void
     */
    protected function initTheme(): void
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
    protected function initExceptionBasedHttpStatuses(): void
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
    protected function initSearch(): void
    {
        $bm = $this->container->get(\VuFind\Search\BackendManager::class);
        $events = $this->container->get('SharedEventManager');
        $events->attach(
            'VuFindSearch',
            \VuFindSearch\Service::EVENT_RESOLVE,
            [$bm, 'onResolve']
        );
    }

    /**
     * Set up logging.
     *
     * @return void
     */
    protected function initErrorLogging(): void
    {
        $callback = function ($event) {
            if ($this->container->has(\VuFind\Log\Logger::class)) {
                $log = $this->container->get(\VuFind\Log\Logger::class);
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
    protected function initRenderErrorEvent(): void
    {
        // When a render.error is triggered, as a high priority, set a flag in the
        // layout that can be used to suppress actions in the layout templates that
        // might trigger exceptions -- this will greatly increase the odds of showing
        // a user-friendly message instead of a fatal error.
        $callback = function ($event) {
            $viewModel = $this->container->get('ViewManager')->getViewModel();
            $viewModel->renderingError = true;
        };
        $this->events->attach('render.error', $callback, 10000);
    }

    /**
     * Set up content security policy
     *
     * @return void
     */
    protected function initContentSecurityPolicy(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }
        $headers = $this->event->getResponse()->getHeaders();
        $cspHeaderGenerator = $this->container
            ->get(\VuFind\Security\CspHeaderGenerator::class);
        if ($cspHeader = $cspHeaderGenerator->getHeader()) {
            $headers->addHeader($cspHeader);
        }
    }
}
