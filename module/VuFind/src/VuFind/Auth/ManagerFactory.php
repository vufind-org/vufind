<?php

/**
 * Authentication Manager factory.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2018.
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
 * @package  Authentication
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Auth;

use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\ContainerInterface;

/**
 * Authentication Manager factory.
 *
 * @category VuFind
 * @package  Authentication
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ManagerFactory implements FactoryInterface
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
        array $options = null
    ) {
        if (!empty($options)) {
            throw new \Exception('Unexpected options sent to factory.');
        }
        // Load dependencies:
        $config = $container->get(\VuFind\Config\PluginManager::class)->get('config');
        $userService = $container->get(\VuFind\Db\Service\PluginManager::class)
            ->get(\VuFind\Db\Service\UserServiceInterface::class);
        $sessionManager = $container->get(\Laminas\Session\SessionManager::class);
        $pm = $container->get(\VuFind\Auth\PluginManager::class);
        $cookies = $container->get(\VuFind\Cookie\CookieManager::class);
        $csrf = $container->get(\VuFind\Validator\CsrfInterface::class);
        $loginTokenManager = $container->get(\VuFind\Auth\LoginTokenManager::class);
        $ils = $container->get(\VuFind\ILS\Connection::class);

        // Build the object and make sure account credentials haven't expired:
        $manager = new $requestedName(
            $config,
            $userService,   // for UserServiceInterface
            $userService,   // for UserSessionPersistenceInterface
            $sessionManager,
            $pm,
            $cookies,
            $csrf,
            $loginTokenManager,
            $ils
        );
        $manager->setIlsAuthenticator($container->get(\VuFind\Auth\ILSAuthenticator::class));
        $manager->checkForExpiredCredentials();
        return $manager;
    }
}
