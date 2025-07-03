<?php

/**
 * Turnstile service factory.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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
 * @package  Service
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\RateLimiter\Turnstile;

use Exception;
use Laminas\Cache\Storage\StorageInterface;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\ContainerInterface;
use VuFind\Service\GetServiceTrait;

/**
 * Turnstile service factory.
 *
 * @category VuFind
 * @package  RateLimiter
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class TurnstileFactory implements FactoryInterface
{
    use GetServiceTrait;

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
        if (!empty($options)) {
            throw new \Exception('Unexpected options sent to factory.');
        }

        $this->serviceLocator = $container;

        $yamlReader = $container->get(\VuFind\Config\YamlReader::class);
        $config = $yamlReader->get('RateLimiter.yaml');

        return new $requestedName(
            $config,
            $this->createTurnstileCache($config)
        );
    }

    /**
     * Create a cache for Turnstile results.
     *
     * @param array $config Rate limiter configuration
     *
     * @return ?StorageInterface
     */
    protected function createTurnstileCache(array $config): StorageInterface
    {
        $storageConfig = $config['Storage'];
        $storageConfig['options']['namespace'] = $storageConfig['turnstileOptions']['namespace'] ?? 'Turnstile';
        $storageConfig['options']['ttl'] = $storageConfig['turnstileOptions']['ttl'] ?? 60 * 60 * 24;
        $cacheManager = $this->getService(\VuFind\Cache\Manager::class);

        if ('redis' === strtolower($storageConfig['adapter'])) {
            throw new Exception('Turnstile adapter does not support redis.');
        }

        $cache = $cacheManager->createInMemoryCache($storageConfig);
        return $cache;
    }
}
