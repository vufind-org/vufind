<?php

/**
 * Application bootstrap event listener
 *
 * Runs early on bootstrap to set the base URL properly before e.g.
 * CookieManagerFactory needs it.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

namespace Finna;

use Laminas\EventManager\EventManagerInterface;
use Laminas\Mvc\MvcEvent;

/**
 * Application bootstrap event listener
 *
 * @category VuFind
 * @package  Bootstrap
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class AppBootstrapListener
{
    /**
     * Attach to an event manager
     *
     * @param EventManagerInterface $events   Event manager
     * @param int                   $priority Priority
     *
     * @return void
     */
    public function attach(EventManagerInterface $events, $priority = 1)
    {
        $events->attach(MvcEvent::EVENT_BOOTSTRAP, [$this, 'onBootstrap']);
    }

    /**
     * Handle application bootstrap event.
     *
     * @param MvcEvent $event Event
     *
     * @return void
     */
    public function onBootstrap($event)
    {
        if (PHP_SAPI === 'cli') {
            return;
        }
        $application = $event->getApplication();
        $request = $application->getRequest();
        $baseUrl = $request->getServer('FINNA_BASE_URL');

        if (!empty($baseUrl)) {
            $baseUrl = '/' . trim($baseUrl, '/');
            $router = $application->getServiceManager()->get('Router');
            $router->setBaseUrl($baseUrl);
            $request->setBaseUrl($baseUrl);
        }
    }
}
