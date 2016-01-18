<?php
/**
 * VuFind Bootstrapper
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015.
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
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna;
use Zend\Console\Console, Zend\Mvc\MvcEvent, Zend\Mvc\Router\Http\RouteMatch;

/**
 * VuFind Bootstrapper
 *
 * @category VuFind2
 * @package  Bootstrap
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Bootstrapper
{
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
        $this->config = $sm->get('VuFind\Config')->get('config');
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
            $controller = $routeMatch->getParam('controller');
            $action = $routeMatch->getParam('action');
            if ($controller == 'AJAX'
                || ($controller == 'Record' && $action == 'AjaxTab')
            ) {
                $response = $event->getResponse();
                $response->setStatusCode(403);
                $response->setContent('Forbidden');
                $event->stopPropagation(true);
                return $response;
            }
        };
        // Attach with a high priority
        $this->events->attach('dispatch', $callback, 11000);
    }

    /**
     * Set up language handling.
     *
     * @return void
     */
    protected function initLanguage()
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
            } elseif (!(($language = $request->getPost()->get('mylang', false))
                || ($language = $request->getQuery()->get('lng', false)))
            ) {
                $language = $config->Site->language;
            }

            try {
                $sm = $event->getApplication()->getServiceManager();
                $sm->get('VuFind\Translator')
                    ->addTranslationFile('ExtendedIni', null, 'default', $language)
                    ->setLocale($language);
            } catch (\Zend\Mvc\Exception\BadMethodCallException $e) {
                if (!extension_loaded('intl')) {
                    throw new \Exception(
                        'Translation broken due to missing PHP intl extension.'
                        . ' Please disable translation or install the extension.'
                    );
                }
            }
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
        if (!Console::isConsole()) {
            // Attach template injection configuration to the route event:
            $this->events->attach(
                'route', ['FinnaTheme\Initializer', 'configureTemplateInjection']
            );
        }

        // Attach remaining theme configuration to the dispatch event at high
        // priority (TODO: use priority constant once defined by framework):
        $config = $this->config->Site;
        $callback = function ($event) use ($config) {
            if ($this->isApiRoute($event)) {
                return;
            }
            $theme = new \FinnaTheme\Initializer($config, $event);
            $theme->init();
        };
        $this->events->attach('dispatch.error', $callback, 9000);
        $this->events->attach('dispatch', $callback, 9000);
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
