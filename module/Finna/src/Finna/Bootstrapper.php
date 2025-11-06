<?php

/**
 * VuFind Bootstrapper
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2015-2022.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Bootstrap
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

namespace Finna;

use Laminas\Mvc\MvcEvent;

use function in_array;

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
    /**
     * Main VuFind configuration
     *
     * @var \VuFind\Config\Config
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
        $this->events = $event->getApplication()->getEventManager();
        $sm = $this->event->getApplication()->getServiceManager();
        $this->config = $sm->get(\VuFind\Config\ConfigManagerInterface::class)->getConfigObject('config');
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
        if (PHP_SAPI === 'cli') {
            return;
        }
        $callback = function ($event) {
            // Check User-Agent
            $headers = $event->getRequest()->getHeaders();
            if (!$headers->has('User-Agent')) {
                return;
            }
            $agent = $headers->get('User-Agent')->getFieldValue();
            $crawlerDetect = new \Jaybizzle\CrawlerDetect\CrawlerDetect();
            if (!$crawlerDetect->isCrawler($agent)) {
                return;
            }
            // Check if the action should be prevented
            $ajaxAllowed = [
                'onlinepaymentnotify',
                'systemstatus',
            ];
            $routeMatch = $event->getRouteMatch();
            $controller = strtolower($routeMatch->getParam('controller'));
            $action = strtolower($routeMatch->getParam('action'));
            $request = $event->getRequest();
            $view = $request->getPost('view') ?? $request->getQuery('view');
            if (
                ($controller == 'ajax' && !in_array($action, $ajaxAllowed))
                || ($controller == 'browse')
                || ($controller == 'browsesearch')
                || ($controller == 'l1' && $action == 'results' && $view !== 'rss')
                || ($controller == 'l1record' && $action == 'ajaxtab')
                || ($controller == 'myresearch')
                || ($controller == 'record' && $action == 'ajaxtab')
                || ($controller == 'record' && $action == 'holdings')
                || ($controller == 'record' && $action == 'details')
                || ($controller == 'record' && $action == 'downloadfile')
                || ($controller == 'record' && $action == 'map')
                || ($controller == 'record' && $action == 'usercomments')
                || ($controller == 'record' && $action == 'similar')
                || ($controller == 'record2' && $action == 'ajaxtab')
                || ($controller == 'qrcode')
                || ($controller == 'oai')
                || ($controller == 'authority' && $action == 'search')
                || ($controller == 'search' && $action == 'results' && $view !== 'rss')
                || ($controller == 'search2' && $action == 'results' && $view !== 'rss')
                || ($controller == 'pci' && $action == 'search')
                || ($controller == 'primo' && $action == 'search')
                || ($controller == 'primorecord')
                || ($controller == 'eds' && $action == 'search')
                || ($controller == 'edsrecord')
                || ($controller == 'blender' && $action == 'results')
                || ($controller == 'cover' && $action == 'download')
            ) {
                $response = $event->getResponse();
                $response->setStatusCode(403);
                $response->setContent('Forbidden');
                $event->stopPropagation(true);
                return $response;
            }
        };

        // Attach with a high priority
        $this->events->attach('dispatch', $callback, 12000);
    }

    /**
     * Set up statistics event handler
     *
     * N.B. The event handler may have already been created by the database row
     * session factory to ensure proper hookup before session events.
     *
     * @return void
     */
    protected function initStatisticsEventHandler()
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        $sm = $this->event->getApplication()->getServiceManager();
        $callback = function ($event) use ($sm): void {
            if (!($routeMatch = $event->getRouteMatch())) {
                return;
            }
            $controller = strtolower($routeMatch->getParam('controller'));
            $action = strtolower($routeMatch->getParam('action'));
            if (!in_array($controller, ['cover', 'qrcode'])) {
                if ('ajax' === $controller) {
                    if ('json' !== $action || !($request = $event->getRequest())) {
                        return;
                    }
                    $method = $request->getPost('method')
                        ?? $request->getQuery('method');
                    if (!in_array($method, ['getImageInformation'])) {
                        return;
                    }
                    $action .= "/$method";
                }
                if ('ajaxtab' === $action && $request = $event->getRequest()) {
                    $tab = $request->getPost('tab') ?? $request->getQuery('tab');
                    if ($tab) {
                        $action .= "/$tab";
                    }
                }
                $sm->get(\Finna\Statistics\EventHandler::class)
                    ->pageView($controller, $action);
            }
        };
        $this->events->attach('dispatch', $callback, 9000);
    }
}
