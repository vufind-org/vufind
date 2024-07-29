<?php

/**
 * Abstract factory for backends.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2021.
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
 * @package  Search
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Search\Factory;

use Laminas\Cache\Storage\StorageInterface;
use Laminas\Config\Config;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use VuFind\Service\GetServiceTrait;

/**
 * Abstract factory for backends.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
abstract class AbstractBackendFactory implements FactoryInterface
{
    use GetServiceTrait;

    /**
     * Constructor
     */
    public function __construct()
    {
    }

    /**
     * Initialize the factory
     *
     * @param ContainerInterface $sm Service manager
     *
     * @return void
     */
    public function setup(ContainerInterface $sm)
    {
        $this->serviceLocator = $sm;
    }

    /**
     * Create HTTP Client
     *
     * @param int    $timeout Request timeout
     * @param array  $options Other options
     * @param string $url     Request URL (needed for proper local address check when
     * the client is being proxified)
     *
     * @return \Laminas\Http\Client
     */
    protected function createHttpClient(
        ?int $timeout = null,
        array $options = [],
        string $url = null
    ): \Laminas\Http\Client {
        $client = $this->getService(\VuFindHttp\HttpService::class)->createClient($url);
        if (null !== $timeout) {
            $options['timeout'] = $timeout;
        }
        $client->setOptions($options);
        return $client;
    }

    /**
     * Create cache for the connector if enabled in configuration
     *
     * @param Config $searchConfig Search configuration
     *
     * @return ?StorageInterface
     */
    protected function createConnectorCache(Config $searchConfig): ?StorageInterface
    {
        if (empty($searchConfig->SearchCache->adapter)) {
            return null;
        }
        $cacheConfig = $searchConfig->SearchCache->toArray();
        $options = $cacheConfig['options'] ?? [];
        if (empty($options['namespace'])) {
            $options['namespace'] = 'Index';
        }
        if (empty($options['ttl'])) {
            $options['ttl'] = 300;
        }
        $settings = [
            'adapter' => $cacheConfig['adapter'],
            'options' => $options,
        ];
        return $this->getService(\Laminas\Cache\Service\StorageAdapterFactory::class)
            ->createFromArrayConfiguration($settings);
    }
}
