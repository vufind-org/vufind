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
use VuFind\Account\Manager as AccountManager,
    VuFind\Config\Reader as ConfigReader,
    VuFind\Theme\Initializer as ThemeInitializer,
    Zend\Mvc\MvcEvent;
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
        $this->events = $event->getApplication()->events();
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
     * Make account manager available to views.
     *
     * @return void
     */
    protected function initAccount()
    {
        $callback = function($event) {
            $serviceManager = $event->getApplication()->getServiceManager();
            $viewModel = $serviceManager->get('viewmanager')->getViewModel();
            $viewModel->setVariable('account', AccountManager::getInstance());
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
            $parts = explode('/', $children[0]->getTemplate());
            $viewModel->setVariable('templateDir', $parts[0]);
            $viewModel->setVariable(
                'templateName', isset($parts[1]) ? $parts[1] : null
            );
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
        $callback = function($event) {
            /* TODO:
            // Setup Translator
            if (($language = $request->getPost('mylang', false))
                || ($language = $request->getParam('lng', false))
            ) {
                setcookie('language', $language, null, '/');
            } else {
                $language = $request->getCookie('language')
                    ? $request->getCookie('language')
                    : $this->config->Site->language;
            }
            // Make sure language code is valid, reset to default if bad:
            $validLanguages = array();
            foreach ($this->config->Languages as $key => $value) {
                $validLanguages[] = $key;
            }
            if (!in_array($language, $validLanguages)) {
                $language = $this->config->Site->language;
            }

            // Set up language caching for better performance:
            $manager = new VF_Cache_Manager();
            Zend_Translate::setCache($manager->getCache('language'));

            // Set up the actual translator object:
            $translator = VF_Translate_Factory::getTranslator($language);
            Zend_Registry::getInstance()->set('Zend_Translate', $translator);

            // Send key values to view:
            $this->view->userLang = $language;
            $this->view->allLangs = $this->config->Languages;
             */
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
        // Attach template injection configuration to the route event:
        $this->events->attach(
            'route', array('VuFind\Theme\Initializer', 'configureTemplateInjection')
        );

        // Attach remaining theme configuration to the dispatch event:
        $config =& $this->config;
        $callback = function($event) use ($config) {
            $theme = new ThemeInitializer($config, $event);
            $theme->init();
        };
        $this->events->attach('dispatch', $callback);
    }

    /**
     * Set up the default database adapter.
     *
     * @return void
     */
    protected function initDatabase()
    {
        /* TODO:
        $this->_db = VF_DB::connect();
        Zend_Db_Table::setDefaultAdapter($this->_db);
         */
    }

    /**
     * Set up mail configuration.
     *
     * @return void
     */
    protected function initMail()
    {
        /* TODO:
        // Load settings from the config file into the object; we'll do the
        // actual creation of the mail object later since that will make error
        // detection easier to control.
        // build Zend_Mail

        $settings = array (
            'port' => $this->config->Mail->port
        );
        if(isset($this->config->Mail->username)
            && isset($this->config->Mail->password)
        ) {
            $settings['auth'] = 'login';
            $settings['username'] = $this->config->Mail->username;
            $settings['password'] = $this->config->Mail->password;
        }
        $tr = new Zend_Mail_Transport_Smtp($this->config->Mail->host,$settings);
        Zend_Mail::setDefaultTransport($tr);
         */
    }

    /**
     * Set up logging.
     *
     * @return void
     */
    protected function initLog()
    {
        /* TODO:
        // Note that we need to initialize the database and the mailer prior to
        // starting up logging to ensure that dependencies are prepared!
        if (!Zend_Registry::isRegistered('Log')) {
            $logger = new VF_Logger();
            Zend_Registry::set('Log', $logger);
        }
         */
    }

    /**
     * Set up the session.
     *
     * @return void
     */
    protected function initSession()
    {
        // Don't bother with session in CLI mode (it just causes error messages):
        if (PHP_SAPI == 'cli') {
            return;
        }

        /* TODO:
        // Get session configuration:
        if (!isset($this->config->Session->type)) {
            throw new Exception('Cannot initialize session; configuration missing');
        }

        // Set up session handler (after manipulating the type setting for legacy
        // compatibility -- VuFind 1.x used MySQL instead of Database and had
        // "Session" as part of the configuration string):
        $type = ucwords(
            str_replace('session', '', strtolower($this->config->Session->type))
        );
        if ($type == 'Mysql') {
            $type = 'Database';
        }
        $class = 'VF_Session_' . $type;
        Zend_Session::setSaveHandler(new $class($this->config->Session));

        // Start up the session:
        Zend_Session::start();

        // According to the PHP manual, session_write_close should always be
        // registered as a shutdown function when using an object as a session
        // handler: http://us.php.net/manual/en/function.session-set-save-handler.php
        register_shutdown_function(array('Zend_Session', 'writeClose'));

        // Check user credentials:
        VF_Account_Manager::getInstance()->checkForExpiredCredentials();
         */
    }
}