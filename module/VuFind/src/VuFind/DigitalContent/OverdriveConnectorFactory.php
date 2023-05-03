<?php

/**
 * Overdrive Connector factory.
 *
 * PHP version 7
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301
 * USA
 *
 * @category VuFind
 * @package  DigitalContent
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Brent Palmer <brent-palmer@icpl.org>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public
 *           License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\DigitalContent;

use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\ContainerInterface;

/**
 * Overdrive Connector factory.
 *
 * @category VuFind
 * @package  DigitalContent
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Brent Palmer <brent-palmer@icpl.org>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public
 *           License
 * @link     https://vufind.org/wiki/development Wiki
 */
class OverdriveConnectorFactory implements
    \Laminas\ServiceManager\Factory\FactoryInterface
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
        if ($options !== null) {
            throw new \Exception('Unexpected options sent to factory!');
        }

        $configManager = $container->get(\VuFind\Config\PluginManager::class);
        $config = $configManager->get('config');
        $odConfig = $configManager->get('Overdrive');

        // Allow simulated connection if configured:
        if ($odConfig->API->simulateConnection ?? false) {
            return new FakeOverdriveConnector($config, $odConfig);
        }
        $auth = $container->get(\VuFind\Auth\ILSAuthenticator::class);
        $sessionContainer = null;

        if (PHP_SAPI !== 'cli') {
            $sessionContainer = new \Laminas\Session\Container(
                'DigitalContent\OverdriveController',
                $container->get(\Laminas\Session\SessionManager::class)
            );
        }
        $connector = new $requestedName(
            $config,
            $odConfig,
            $auth,
            $sessionContainer
        );

        // Populate cache storage
        $connector->setCacheStorage(
            $container->get(\VuFind\Cache\Manager::class)->getCache(
                'object',
                "Overdrive"
            )
        );

        return $connector;
    }
}
