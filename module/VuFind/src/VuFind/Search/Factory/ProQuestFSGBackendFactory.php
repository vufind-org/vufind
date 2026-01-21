<?php

/**
 * Factory for ProQuest Federated Search Gateway backends.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @author   Maccabee Levine <msl321@lehigh.edu>
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
use VuFindSearch\Backend\ProQuestFSG\Backend;
use VuFindSearch\Backend\ProQuestFSG\Connector;
use VuFindSearch\Backend\ProQuestFSG\Response\XML\RecordCollectionFactory;

/**
 * Factory for ProQuest Federated Search Gateway backends.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ProQuestFSGBackendFactory extends AbstractBackendFactory
{
    /**
     * Logger.
     *
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

    /**
     * VuFind configuration
     *
     * @var Config
     */
    protected Config $config;

    /**
     * ProQuestFSG configuration
     *
     * @var Config
     */
    protected Config $proQuestFSGConfig;

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
        $this->setup($container);
        $configManager = $this->getService(\VuFind\Config\ConfigManagerInterface::class);
        $this->config = $configManager->getConfigObject('config');
        $this->proQuestFSGConfig = $configManager->getConfigObject('ProQuestFSG');
        if ($this->serviceLocator->has(\VuFind\Log\Logger::class)) {
            $this->logger = $this->getService(\VuFind\Log\Logger::class);
        }
        $connector = $this->createConnector();
        $backend   = $this->createBackend($connector);
        return $backend;
    }

    /**
     * Create the ProQuestFSG backend.
     *
     * @param Connector $connector Connector
     *
     * @return Backend
     */
    protected function createBackend(Connector $connector): Backend
    {
        $backend = new Backend($connector, $this->createRecordCollectionFactory());
        $backend->setLogger($this->logger);
        return $backend;
    }

    /**
     * Create the ProQuestFSG connector.
     *
     * @return Connector
     */
    protected function createConnector(): Connector
    {
        $connector = new Connector($this->createHttpClient(), $this->proQuestFSGConfig->toArray());
        $connector->setLogger($this->logger);
        if ($cache = $this->createConnectorCache($this->proQuestFSGConfig)) {
            $connector->setCache($cache);
        }
        return $connector;
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
            $driver = $manager->get('ProQuestFSG');
            $driver->setRawData($data);
            return $driver;
        };
        return new RecordCollectionFactory($callback);
    }
}
