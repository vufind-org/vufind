<?php

/**
 * Factory for instantiating Session Manager
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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
 * @package  Session_Handlers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\Session;

use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\ContainerInterface;

/**
 * Factory for instantiating Session Manager
 *
 * @category VuFind
 * @package  Session_Handlers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 *
 * @codeCoverageIgnore
 */
class ManagerFactory extends \VuFind\Session\ManagerFactory
{
    /**
     * Create an object
     *
     * @param ContainerInterface $container     Service manager
     * @param string             $requestedName Service being created
     * @param null|array         $options       Extra options (optional)
     *
     * @return object
     *
     * @throws ServiceNotFoundException if unable to resolve the service.
     * @throws ServiceNotCreatedException if an exception is raised when
     * creating a service.
     * @throws ContainerException&\Throwable if any other error occurs
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        ?array $options = null
    ) {
        $sessionManager = parent::__invoke($container, $requestedName, $options);

        $serverName = $_SERVER['SERVER_NAME'] ?? '';

        // Verify that any existing session has the correct server name to avoid using
        // a cookie from another domain.
        $storage = new \Laminas\Session\Container('SessionState', $sessionManager);
        if (null !== $storage->serverName) {
            if ($storage->serverName !== $serverName) {
                // Disable writes temporarily to keep the existing session intact
                $sessionManager->getSaveHandler()->disableWrites();
                // Regenerate session ID and reset the session data
                $sessionManager->regenerateId(false);
                session_unset();
                $sessionManager->getSaveHandler()->enableWrites();
                $storage->serverName = $serverName;
            }
        } else {
            $storage->serverName = $serverName;
        }

        return $sessionManager;
    }
}
