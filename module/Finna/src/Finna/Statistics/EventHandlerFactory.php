<?php

/**
 * Statistics event handler factory.
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
 * @package  Service
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\Statistics;

use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\ContainerInterface;
use VuFind\Net\IpAddressUtils;

/**
 * Statistics event handler factory.
 *
 * @category VuFind
 * @package  Service
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class EventHandlerFactory implements FactoryInterface
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
        ContainerInterface $container,
        $requestedName,
        ?array $options = null
    ) {
        if (!empty($options)) {
            throw new \Exception('Unexpected options passed to factory.');
        }

        $config = $container->get(\VuFind\Config\ConfigManagerInterface::class)->getConfigArray('config');

        $driver = null;
        $ipUtils = $container->get(\VuFind\Net\IpAddressUtils::class);
        $remoteAddress = new \Laminas\Http\PhpEnvironment\RemoteAddress();
        $clientIp = $remoteAddress->getIpAddress();
        if (
            !empty($config['Statistics']['driver'])
            && !$this->isRequestsExcluded($ipUtils, $clientIp, $config)
        ) {
            $driverManager
                = $container->get(\Finna\Statistics\Driver\PluginManager::class);
            $driver = $driverManager->get($config['Statistics']['driver']);
        }

        $request = $container->get('Request');
        $headers = $request->getHeaders();
        $userAgent = $headers->has('User-Agent') ? $headers->get('User-Agent')->getFieldValue() : '';

        // Don't collect stats about bots by default:
        if ($config['Statistics']['exclude_bots'] ?? true) {
            $crawlerDetect = new \Jaybizzle\CrawlerDetect\CrawlerDetect();
            if ($crawlerDetect->isCrawler($userAgent)) {
                $driver = null;
            }
        }

        return new $requestedName(
            $config['Site']['institution'] ?? '',
            rtrim(getenv('FINNA_BASE_URL') ?: '', '/'),
            $driver,
            $userAgent,
            $this->isMonitoringSystem($ipUtils, $clientIp, $config)
        );
    }

    /**
     * Check if the request should be excluded
     *
     * @param IpAddressUtils $ipUtils  IP address utilities
     * @param string         $clientIp Client IP address
     * @param array          $config   Main configuration
     *
     * @return bool
     */
    protected function isRequestsExcluded(
        IpAddressUtils $ipUtils,
        string $clientIp,
        array $config
    ): bool {
        if ('cli' === PHP_SAPI) {
            return true;
        }
        if ($ranges = ($config['Statistics']['exclude_ips'] ?? [])) {
            return $ipUtils->isInRange($clientIp, (array)$ranges);
        }
        return false;
    }

    /**
     * Check if the request comes from a monitoring system
     *
     * @param IpAddressUtils $ipUtils  IP address utilities
     * @param string         $clientIp Client IP address
     * @param array          $config   Main configuration
     *
     * @return bool
     */
    protected function isMonitoringSystem(
        IpAddressUtils $ipUtils,
        string $clientIp,
        array $config
    ): bool {
        if ($ranges = ($config['Statistics']['monitoring_ips'] ?? [])) {
            return $ipUtils->isInRange($clientIp, (array)$ranges);
        }
        return false;
    }
}
