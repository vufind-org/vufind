<?php
/**
 * VuFind Bootstrapper
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
 * @package  Bootstrap
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind;
use VuFind\Config\Reader as ConfigReader,
    Zend\Console\Console, Zend\Mvc\MvcEvent, Zend\Mvc\Router\Http\RouteMatch;

/**
 * VuFind Bootstrapper
 *
 * @category VuFind2
 * @package  Bootstrap
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Bootstrapper
{
    protected $config;
    protected $event;
    protected $events;

    /**
     * Constructor
     *
     * @param MvcEvent $event Zend MVC Event object
     */
    public function __construct(MvcEvent $event)
    {
        $this->config = ConfigReader::getConfig();
        $this->event = $event;
        $this->events = $event->getApplication()->getEventManager();
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
     * Set up plugin managers.
     *
     * @return void
     */
    protected function initPluginManagers()
    {
        $app = $this->event->getApplication();
        $serviceManager = $app->getServiceManager();
        $config = $app->getConfig();

        // Use naming conventions to set up a bunch of services based on namespace:
        $namespaces = array(
            'Auth', 'Autocomplete', 'Db\Table', 'Hierarchy\Driver',
            'Hierarchy\TreeDataSource', 'Hierarchy\TreeRenderer', 'ILS\Driver',
            'Recommend', 'RecordDriver', 'RecordTab', 'Related', 'Resolver\Driver',
            'Session', 'Statistics\Driver'
        );
        foreach ($namespaces as $ns) {
            $serviceName = 'VuFind\\' . str_replace('\\', '', $ns) . 'PluginManager';
            $factory = function ($sm) use ($config, $ns) {
                $className = 'VuFind\\' . $ns . '\PluginManager';
                $configKey = strtolower(str_replace('\\', '_', $ns));
                return new $className(
                    new \Zend\ServiceManager\Config(
                        $config['vufind']['plugin_managers'][$configKey]
                    )
                );
            };
            $serviceManager->setFactory($serviceName, $factory);
        }

        // Set up search manager a little differently -- it is a more complex class
        // that doesn't work like the other standard plugin managers.
        $factory = function ($sm) use ($config) {
            return new \VuFind\Search\Manager($config['vufind']['search_manager']);
        };
        $serviceManager->setFactory('SearchManager', $factory);

        // TODO: factor out static connection manager.
        \VuFind\Connection\Manager::setServiceLocator($serviceManager);
    }

    /**
     * Set up the session.  This should be done early since other startup routines
     * may rely on session access.
     *
     * @return void
     */
    protected function initSession()
    {
        // Don't bother with session in CLI mode (it just causes error messages):
        if (Console::isConsole()) {
            return;
        }

        // Get session configuration:
        if (!isset($this->config->Session->type)) {
            throw new \Exception('Cannot initialize session; configuration missing');
        }

        // Set up the session handler by retrieving all the pieces from the service
        // manager and injecting appropriate dependencies:
        $serviceManager = $this->event->getApplication()->getServiceManager();
        $sessionManager = $serviceManager->get('VuFind\SessionManager');
        $sessionPluginManager = $serviceManager->get('VuFind\SessionPluginManager');
        $sessionHandler = $sessionPluginManager->get($this->config->Session->type);
        $sessionHandler->setConfig($this->config->Session);
        $sessionManager->setSaveHandler($sessionHandler);

        // Start up the session:
        $sessionManager->start();

        // According to the PHP manual, session_write_close should always be
        // registered as a shutdown function when using an object as a session
        // handler: http://us.php.net/manual/en/function.session-set-save-handler.php
        register_shutdown_function(
            function () use ($sessionManager) {
                // If storage is immutable, the session is already closed:
                if (!$sessionManager->getStorage()->isImmutable()) {
                    $sessionManager->writeClose();
                }
            }
        );

        // Make sure account credentials haven't expired:
        $serviceManager->get('VuFind\AuthManager')->checkForExpiredCredentials();
    }

    /**
     * If the system is offline, set up a handler to override the routing output.
     *
     * @return void
     */
    protected function initSystemStatus()
    {
        // If the system is unavailable, forward to a different place:
        if (isset($this->config->System->available)
            && !$this->config->System->available
        ) {
            $callback = function ($e) {
                $routeMatch = new RouteMatch(
                    array('controller' => 'Error', 'action' => 'Unavailable'), 1
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
    protected function initLocaleAndTimeZone()
    {
        // Try to set the locale to UTF-8, but fail back to the exact string from
        // the config file if this doesn't work -- different systems may vary in
        // their behavior here.
        setlocale(
            LC_MONETARY,
            array("{$this->config->Site->locale}.UTF-8", $this->config->Site->locale)
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
            $viewModel = $serviceManager->get('viewmanager')->getViewModel();

            // Grab the template name from the first child -- we can use this to
            // figure out the current template context.
            $children = $viewModel->getChildren();
            if (!empty($children)) {
                $parts = explode('/', $children[0]->getTemplate());
                $viewModel->setVariable('templateDir', $parts[0]);
                $viewModel->setVariable(
                    'templateName', isset($parts[1]) ? $parts[1] : null
                );
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
            $renderer = $serviceManager->get('viewmanager')->getRenderer();
            $headTitle = $renderer->plugin('headtitle');
            $headTitle->setDefaultAttachOrder(
                \Zend\View\Helper\Placeholder\Container\AbstractContainer::SET
            );
        };
        $this->events->attach('dispatch', $callback);
    }

    /**
     * Set up language handling.
     *
     * @return void
     */
    protected function initLanguage()
    {
        // Language not supported in CLI mode:
        if (Console::isConsole()) {
            return;
        }

        $config =& $this->config;
        $callback = function ($event) use ($config) {
            // Setup Translator
            $request = $event->getRequest();
            if (($language = $request->getPost()->get('mylang', false))
                || ($language = $request->getQuery()->get('lng', false))
            ) {
                setcookie('language', $language, null, '/');
            } else {
                $language = !empty($request->getCookie()->language)
                    ? $request->getCookie()->language
                    : $config->Site->language;
            }
            // Make sure language code is valid, reset to default if bad:
            if (!in_array($language, array_keys($config->Languages->toArray()))) {
                $language = $config->Site->language;
            }

            $sm = $event->getApplication()->getServiceManager();
            $langFile = APPLICATION_PATH  . '/languages/' . $language . '.ini';
            $sm->get('VuFind\Translator')
                ->addTranslationFile('ExtendedIni', $langFile, 'default', $language)
                ->setLocale($language);

            // Send key values to view:
            $viewModel = $sm->get('viewmanager')->getViewModel();
            $viewModel->setVariable('userLang', $language);
            $viewModel->setVariable('allLangs', $config->Languages);
        };
        $this->events->attach('dispatch.error', $callback, 9000);
        $this->events->attach('dispatch', $callback, 9000);
    }

    /**
     * Set up theme handling.
     *
     * @return void
     */
    protected function initTheme()
    {
        // Themes not needed in console mode:
        if (Console::isConsole()) {
            return;
        }

        // Attach template injection configuration to the route event:
        $this->events->attach(
            'route', array('VuFindTheme\Initializer', 'configureTemplateInjection')
        );

        // Attach remaining theme configuration to the dispatch event at high
        // priority (TODO: use priority constant once defined by framework):
        $config = $this->config->Site;
        $callback = function ($event) use ($config) {
            $theme = new \VuFindTheme\Initializer($config, $event);
            $theme->init();
        };
        $this->events->attach('dispatch.error', $callback, 10000);
        $this->events->attach('dispatch', $callback, 10000);
    }

    /**
     * Set up custom 404 status based on exception type.
     *
     * @return void
     */
    protected function initExceptionBased404s()
    {
        // 404s not needed in console mode:
        if (Console::isConsole()) {
            return;
        }

        $callback = function ($e) {
            $exception = $e->getParam('exception');
            if (is_object($exception)) {
                if ($exception instanceof \VuFind\Exception\RecordMissing) {
                    // TODO: it might be better to solve this problem by using a
                    // custom RouteNotFoundStrategy.
                    $response = $e->getResponse();
                    if (!$response) {
                        $response = new HttpResponse();
                        $e->setResponse($response);
                    }
                    $response->setStatusCode(404);
                }
            }
        };
        $this->events->attach('dispatch.error', $callback);
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
            if ($sm->has('VuFind\Logger')) {
                $log = $sm->get('VuFind\Logger');
                if (is_callable(array($log, 'logException'))) {
                    $exception = $event->getParam('exception');
                    // Console request does not include server,
                    // so use a dummy in that case.
                    $server = Console::isConsole()
                        ? new \Zend\Stdlib\Parameters(array('env' => 'console'))
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
            $viewModel = $serviceManager->get('viewmanager')->getViewModel();
            $viewModel->renderingError = true;
        };
        $this->events->attach('render.error', $callback, 10000);
    }
}