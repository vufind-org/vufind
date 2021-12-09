<?php
/**
 * Abstract factory for backends.
 *
 * PHP version 7
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

use Interop\Container\ContainerInterface;

use Laminas\ServiceManager\Factory\FactoryInterface;

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
    /**
     * Superior service manager.
     *
     * @var ContainerInterface
     */
    protected $serviceLocator;

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
     * @param int   $timeout Request timeout
     * @param array $options Other options
     *
     * @return \Laminas\Http\Client
     */
    protected function createHttpClient(
        ?int $timeout = null,
        array $options = []
    ): \Laminas\Http\Client {
        $client = $this->serviceLocator->get(\VuFindHttp\HttpService::class)
            ->createClient();
        $options = $options ?? [];
        if (null !== $timeout) {
            $options['timeout'] = $timeout;
        }
        $client->setOptions($options);
        return $client;
    }
}
