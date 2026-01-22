<?php

/**
 * Factory for LibGuides backends.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2013.
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
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Search\Factory;

use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use VuFind\Config\Config;
use VuFindSearch\Backend\LibGuides\Backend;
use VuFindSearch\Backend\LibGuides\Connector;
use VuFindSearch\Backend\LibGuides\QueryBuilder;
use VuFindSearch\Backend\LibGuides\Response\RecordCollectionFactory;

/**
 * Factory for LibGuides backends.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class LibGuidesBackendFactory extends AbstractBackendFactory
{
    /**
     * Return the service name.
     *
     * @return string
     */
    protected function getServiceName(): string
    {
        return 'LibGuides';
    }

    /**
     * Logger.
     *
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

    /**
     * LibGuides configuration
     *
     * @var Config
     */
    protected Config $libGuidesConfig;

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
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        ?array $options = null
    ) {
        $this->setup($container);
        $this->libGuidesConfig = $this->getService(\VuFind\Config\ConfigManagerInterface::class)
            ->getConfigObject($this->getServiceName());
        if ($this->serviceLocator->has(\VuFind\Log\Logger::class)) {
            $this->logger = $this->getService(\VuFind\Log\Logger::class);
        }
        $connector = $this->createConnector();
        $backend   = $this->createBackend($connector);
        return $backend;
    }

    /**
     * Create the LibGuides backend.
     *
     * @param Connector $connector Connector
     *
     * @return Backend
     */
    protected function createBackend(Connector $connector): Backend
    {
        $defaultSearch = $this->libGuidesConfig->General->defaultSearch ?? null;
        $backend = new Backend(
            $connector,
            $this->createRecordCollectionFactory(),
            $defaultSearch
        );
        $backend->setLogger($this->logger);
        $backend->setQueryBuilder($this->createQueryBuilder());
        return $backend;
    }

    /**
     * Create the LibGuides connector.
     *
     * @return Connector
     */
    protected function createConnector(): Connector
    {
        // Load credentials:
        $iid = $this->libGuidesConfig->General->iid ?? null;

        // Pick version:
        $ver = $this->libGuidesConfig->General->version ?? 1;

        // Get base URI, if available:
        $baseUrl = $this->libGuidesConfig->General->baseUrl ?? null;

        // Optionally parse the resource description
        $displayDescription = $this->libGuidesConfig->General->displayDescription ?? false;

        // Create connector:
        $connector = new Connector(
            $iid,
            $this->createHttpClient($this->libGuidesConfig->General->timeout ?? 30),
            $ver,
            $baseUrl,
            $displayDescription
        );
        $connector->setLogger($this->logger);
        return $connector;
    }

    /**
     * Create the LibGuides query builder.
     *
     * @return QueryBuilder
     */
    protected function createQueryBuilder(): QueryBuilder
    {
        return new QueryBuilder();
    }

    /**
     * Create the record collection factory
     *
     * @return RecordCollectionFactory
     */
    protected function createRecordCollectionFactory(): RecordCollectionFactory
    {
        $manager = $this->getService(\VuFind\RecordDriver\PluginManager::class);
        $callback = function ($data) use ($manager) {
            $driver = $manager->get($this->getServiceName());
            $driver->setRawData($data);
            return $driver;
        };
        return new RecordCollectionFactory($callback);
    }
}
