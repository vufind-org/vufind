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

use Psr\Container\ContainerInterface;
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
     * @var \Laminas\Log\LoggerInterface
     */
    protected $logger;

    /**
     * VuFind configuration
     *
     * @var \VuFind\Config\Config
     */
    protected $config;

    /**
     * ProQuestFSG configuration
     *
     * @var \VuFind\Config\Config
     */
    protected $proQuestFSGConfig;

    /**
     * Create service
     *
     * @param ContainerInterface $sm      Service manager
     * @param string             $name    Requested service name (unused)
     * @param array              $options Extra options (unused)
     *
     * @return Backend
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $sm, $name, ?array $options = null)
    {
        $this->setup($sm);
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
    protected function createBackend(Connector $connector)
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
    protected function createConnector()
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
    protected function createRecordCollectionFactory()
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
