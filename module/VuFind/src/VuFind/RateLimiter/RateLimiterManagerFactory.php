<?php

/**
 * Rate limiter manager factory.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2024.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\RateLimiter;

use Closure;
use Laminas\Cache\Psr\CacheItemPool\CacheItemPoolDecorator;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\ContainerInterface;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;
use Symfony\Component\RateLimiter\Storage\StorageInterface;
use VuFind\RateLimiter\Storage\CredisStorage;

/**
 * Rate limiter manager factory.
 *
 * @category VuFind
 * @package  Service
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class RateLimiterManagerFactory implements FactoryInterface
{
    /**
     * Service locator
     *
     * @var ContainerInterface
     */
    protected $serviceLocator;

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

        $this->serviceLocator = $container;

        $yamlReader = $container->get(\VuFind\Config\YamlReader::class);
        $config = $yamlReader->get('RateLimiter.yaml');

        $authManager = $container->get(\VuFind\Auth\Manager::class);
        $request = $container->get('Request');

        return new $requestedName(
            $config,
            $request->getServer('REMOTE_ADDR'),
            $authManager->getUserObject()?->getId(),
            Closure::fromCallable([$this, 'getRateLimiter']),
            $container->get(\VuFind\Net\IpAddressUtils::class)
        );
    }

    /**
     * Get rate limiter
     *
     * @param array   $config   Rate limiter configuration
     * @param string  $policyId Policy ID
     * @param string  $clientIp Client's IP address
     * @param ?string $userId   User ID or null if not logged in
     *
     * @return LimiterInterface
     */
    protected function getRateLimiter(
        array $config,
        string $policyId,
        string $clientIp,
        ?string $userId
    ): LimiterInterface {
        $policy = $config['Policies'][$policyId] ?? [];
        $rateLimiterConfig = $policy['rateLimiterSettings'] ?? [];
        $rateLimiterConfig['id'] = $policyId;
        if (null !== $userId && !($policy['preferIPAddress'] ?? false)) {
            $clientId = "u:$userId";
        } else {
            $clientId = "ip:$clientIp";
        }
        $factory = new RateLimiterFactory($rateLimiterConfig, $this->createCache($config));
        return $factory->create($clientId);
    }

    /**
     * Create cache for the rate limiter
     *
     * @param array $config Rate limiter configuration
     *
     * @return ?StorageInterface
     */
    protected function createCache(array $config): StorageInterface
    {
        if (empty($config['Storage']['namespace'])) {
            $config['Storage']['namespace'] = 'RateLimiter';
        }
        $storageConfig = $config['Storage'] ?? [];
        $adapter = $storageConfig['adapter'] ?? 'memcached';
        // Handle Redis cache separately:
        if ('redis' === $adapter || 'Redis' === $adapter) {
            return $this->createRedisCache($config);
        }

        // Laminas cache:
        $settings = [
            'adapter' => $storageConfig['adapter'],
            'options' => $storageConfig['options'] ?? [],
        ];
        $laminasCache = $this->serviceLocator
            ->get(\Laminas\Cache\Service\StorageAdapterFactory::class)
            ->createFromArrayConfiguration($settings);

        return new CacheStorage(new CacheItemPoolDecorator($laminasCache));
    }

    /**
     * Create Redis cache for the rate limiter
     *
     * @param array $config Rate limiter configuration
     *
     * @return ?StorageInterface
     */
    protected function createRedisCache(array $config): StorageInterface
    {
        // Set defaults if nothing set in config file:
        $options = $config['Storage']['options'] ?? [];
        $host = $options['redis_host'] ?? 'localhost';
        $port = $options['redis_port'] ?? 6379;
        $timeout = $options['redis_connection_timeout'] ?? 0.5;
        $password = $options['redis_auth'] ?? null;
        $username = $options['redis_user'] ?? null;
        $redisDb = $options['redis_db'] ?? 0;

        // Create Credis client, the connection is established lazily:
        $redis = new \Credis_Client($host, $port, $timeout, '', $redisDb, $password, $username);
        if ($options['redis_standalone'] ?? true) {
            $redis->forceStandalone();
        }

        return new CredisStorage($redis, $config['Storage'] ?? []);
    }
}
