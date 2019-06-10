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

use Interop\Container\ContainerInterface;

/**
 * Generic Amazon content plugin factory.
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
    \Zend\ServiceManager\Factory\FactoryInterface
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
     * @throws ContainerException if any other error occurs
     */
    public function __invoke(
        ContainerInterface $container, $requestedName,
        array $options = null
    ) {
        if ($options !== null) {
            throw new \Exception('Unexpected options sent to factory!');
        }

        $config = $container->get('VuFind\Config\PluginManager')->get('config');
        $odConfig = $container->get('VuFind\Config\PluginManager')->get(
            'Overdrive'
        );
        $auth = $container->get('VuFind\Auth\ILSAuthenticator');
        $sessionContainer = null;

        if (PHP_SAPI !== 'cli') {
            $sessionContainer = new \Zend\Session\Container(
                'DigitalContent\OverdriveController',
                $container->get('Zend\Session\SessionManager')
            );
        }
        $connector = new $requestedName(
            $config, $odConfig, $auth, $sessionContainer
        );

        // Populate cache storage
        $connector->setCacheStorage(
            $container->get('VuFind\Cache\Manager')->getCache(
                'object', "Overdrive"
            )
        );

        return $connector;
    }
}
