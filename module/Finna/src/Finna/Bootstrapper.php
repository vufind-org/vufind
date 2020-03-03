<?php
/**
 * VuFind Bootstrapper
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2015-2020.
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
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna;

use Zend\Console\Console;
use Zend\Mvc\MvcEvent;

/**
 * VuFind Bootstrapper
 *
 * @category VuFind
 * @package  Bootstrap
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Bootstrapper
{
    use \VuFind\I18n\Translator\LanguageInitializerTrait;

    /**
     * Main VuFind configuration
     *
     * @var \Zend\Config\Config
     */
    protected $config = null;

    /**
     * Current MVC event
     *
     * @var MvcEvent
     */
    protected $event;

    /**
     * Event manager
     *
     * @var \Zend\EventManager\EventManagerInterface
     */
    protected $events;

    /**
     * Constructor
     *
     * @param MvcEvent $event Zend MVC Event object
     */
    public function __construct(MvcEvent $event)
    {
        $this->event = $event;
        $this->events = $event->getApplication()->getEventManager();
        $sm = $this->event->getApplication()->getServiceManager();
        $this->config = $sm->get(\VuFind\Config\PluginManager::class)->get('config');
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
     * Set up bot check that disallows access to some functions from bots
     *
     * @return void
     */
    protected function initBotCheck()
    {
        $callback = function ($event) {
            // Check User-Agent
            $headers = $event->getRequest()->getHeaders();
            if (!$headers->has('User-Agent')) {
                return;
            }
            $agent = $headers->get('User-Agent')->toString();
            if (!preg_match('/bot|crawl|slurp|spider/i', $agent)) {
                return;
            }
            // Check if the action should be prevented
            $routeMatch = $event->getRouteMatch();
            $controller = strtolower($routeMatch->getParam('controller'));
            $action = strtolower($routeMatch->getParam('action'));
            if (($controller == 'ajax' && $action != 'systemstatus')
                || ($controller == 'record' && $action == 'ajaxtab')
                || ($controller == 'record' && $action == 'holdings')
                || ($controller == 'record' && $action == 'details')
                || ($controller == 'record' && $action == 'map')
                || ($controller == 'record' && $action == 'usercomments')
                || ($controller == 'record' && $action == 'similar')
                || ($controller == 'qrcode')
                || ($controller == 'oai')
                || ($controller == 'pci' && $action == 'search')
                || ($controller == 'primo' && $action == 'search')
                || ($controller == 'primorecord')
                || ($controller == 'eds' && $action == 'search')
                || ($controller == 'edsrecord')
                || ($controller == 'search' && $action == 'blended')
            ) {
                $response = $event->getResponse();
                $response->setStatusCode(403);
                $response->setContent('Forbidden');
                $event->stopPropagation(true);
                return $response;
            }
        };

        // Attach with a high priority
        if (!Console::isConsole()) {
            $this->events->attach('dispatch', $callback, 11000);
        }
    }

    /**
     * Set up language handling.
     *
     * @return void
     */
    protected function initCliOrApiLanguage()
    {
        $config = &$this->config;
        $sm = $this->event->getApplication()->getServiceManager();

        $callback = function ($event) use ($config, $sm) {
            // Special initialization only for CLI and API routes
            if (!Console::isConsole() && !$this->isApiRoute($event)) {
                return;
            }
            $request = $event->getRequest();
            if (Console::isConsole()) {
                $language = $config->Site->language;
            } elseif (($language = $request->getPost()->get('mylang', false))
                || ($language = $request->getQuery()->get('lng', false))
            ) {
                // Make sure language code is valid, reset to default if bad:
                if (!in_array($language, array_keys($config->Languages->toArray()))
                ) {
                    $language = $config->Site->language;
                }
            } else {
                $language = $config->Site->language;
            }

            try {
                $translator = $sm->get(\Zend\Mvc\I18n\Translator::class);
                $translator->setLocale($language);
                $this->addLanguageToTranslator($translator, $language);
            } catch (\Zend\Mvc\I18n\Exception\BadMethodCallException $e) {
                if (!extension_loaded('intl')) {
                    throw new \Exception(
                        'Translation broken due to missing PHP intl extension.'
                        . ' Please disable translation or install the extension.'
                    );
                }
            }
            // Send key values to view:
            $viewManager = $sm->get('ViewManager');
            if (!($viewManager instanceof \Zend\Mvc\Console\View\ViewManager)) {
                $viewModel = $viewManager->getViewModel();
                $viewModel->setVariable('userLang', $language);
                $viewModel->setVariable('allLangs', $config->Languages);
            }
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
        // Attach remaining theme configuration to the dispatch event at high
        // priority (TODO: use priority constant once defined by framework):
        $config = $this->config->Site;
        $callback = function ($event) use ($config) {
            if ($this->isApiRoute($event)) {
                return;
            }
            $theme = new \VuFindTheme\Initializer($config, $event);
            $theme->init();
        };
        $this->events->attach('dispatch.error', $callback, 9000);
        $this->events->attach('dispatch', $callback, 9000);
    }

    /**
     * Initialize the base url for the application from an environment variable
     *
     * @return void
     */
    protected function initBaseUrl()
    {
        if (Console::isConsole()) {
            return;
        }
        $callback = function ($event) {
            $application = $event->getApplication();
            $request = $application->getRequest();
            $baseUrl = $request->getServer('FINNA_BASE_URL');

            if (!empty($baseUrl)) {
                $baseUrl = '/' . trim($baseUrl, '/');
                $router = $application->getServiceManager()->get('Router');
                $router->setBaseUrl($baseUrl);
                $request->setBaseUrl($baseUrl);
            }
        };
        $this->events->attach('route', $callback, 9000);
    }

    /**
     * Check if we're processing an API route
     *
     * @param MvcEvent $event Event being handled
     *
     * @return boolean
     */
    protected function isApiRoute($event)
    {
        $routeMatch = $event->getRouteMatch();
        $routeName = $routeMatch !== null ? $routeMatch->getMatchedRouteName() : '';
        return substr($routeName, -3) === 'Api';
    }
}
