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
 * @package  Support_Classes
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind;
use VuFind\Auth\Manager as AuthManager, VuFind\Config\Reader as ConfigReader,
    VuFind\Db\AdapterFactory as DbAdapterFactory, VuFind\Logger,
    VuFind\Theme\Initializer as ThemeInitializer,
    VuFind\Translator\Translator, Zend\Console\Console,
    Zend\Db\TableGateway\Feature\GlobalAdapterFeature as DbGlobalAdapter,
    Zend\Mvc\MvcEvent, Zend\Mvc\Router\Http\RouteMatch,
    Zend\Session\SessionManager;

/**
 * VuFind Bootstrapper
 *
 * @category VuFind2
 * @package  Support_Classes
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Bootstrap
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
     * Set up the default database adapter.  Do this first since the session handler
     * may depend on database access.
     *
     * @return void
     */
    protected function initDatabase()
    {
        DbGlobalAdapter::setStaticAdapter(DbAdapterFactory::getAdapter());
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

        // Register a session manager in the service manager:
        $sessionManager = new SessionManager();
        $serviceManager = $this->event->getApplication()->getServiceManager();
        $serviceManager->setService('SessionManager', $sessionManager);

        // Set up session handler (after manipulating the type setting for legacy
        // compatibility -- VuFind 1.x used MySQL instead of Database and had
        // "Session" as part of the configuration string):
        $type = ucwords(
            str_replace('session', '', strtolower($this->config->Session->type))
        );
        if ($type == 'Mysql') {
            $type = 'Database';
        }
        $class = 'VuFind\\Session\\' . $type;
        $sessionManager->setSaveHandler(new $class($this->config->Session));

        // Start up the session:
        $sessionManager->start();

        // According to the PHP manual, session_write_close should always be
        // registered as a shutdown function when using an object as a session
        // handler: http://us.php.net/manual/en/function.session-set-save-handler.php
        register_shutdown_function(array($sessionManager, 'writeClose'));
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
            $callback = function($e) {
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
     * Make account manager available to service manager and views.
     *
     * @return void
     */
    protected function initAccount()
    {
        $authManager = new AuthManager();

        // Register in service manager:
        $serviceManager = $this->event->getApplication()->getServiceManager();
        $serviceManager->setService('AuthManager', $authManager);

        // ...and register service manager in AuthManager (for access to other
        // services, such as the session manager):
        $authManager->setServiceLocator($serviceManager);

        // Now that we're all set up, make sure credentials haven't expired:
        $authManager->checkForExpiredCredentials();

        // Register in view:
        $callback = function($event) use ($authManager) {
            $serviceManager = $event->getApplication()->getServiceManager();
            $viewModel = $serviceManager->get('viewmanager')->getViewModel();
            $viewModel->setVariable('account', $authManager);
        };
        $this->events->attach('dispatch', $callback);
    }

    /**
     * Set view variables representing the current context.
     *
     * @return void
     */
    protected function initContext()
    {
        $callback = function($event) {
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
        $callback = function($event) {
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
        $callback = function($event) use ($config) {
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
            $validLanguages = array();
            foreach ($config->Languages as $key => $value) {
                $validLanguages[] = $key;
            }
            if (!in_array($language, $validLanguages)) {
                $language = $config->Site->language;
            }

            Translator::init($event, $language);

            // Send key values to view:
            $viewModel = $event->getApplication()->getServiceManager()
                ->get('viewmanager')->getViewModel();
            $viewModel->setVariable('userLang', $language);
            $viewModel->setVariable('allLangs', $config->Languages);
        };
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
            'route', array('VuFind\Theme\Initializer', 'configureTemplateInjection')
        );

        // Attach remaining theme configuration to the dispatch event at high
        // priority (TODO: use priority constant once defined by framework):
        $config =& $this->config;
        $callback = function($event) use ($config) {
            $theme = new ThemeInitializer($config, $event);
            $theme->init();
        };
        $this->events->attach('dispatch', $callback, 10000);
    }

    /**
     * Set up logging.
     *
     * @return void
     */
    protected function initLog()
    {
        // TODO: We need to figure out how to capture exceptions to the log -- the
        // logic found in 2.0alpha's ErrorController needs a new home in 2.0beta.
    }
}