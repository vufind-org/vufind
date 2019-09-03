<?php

/**
 * Factory for BrowZine backend.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2017.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace VuFind\Search\Factory;

use Interop\Container\ContainerInterface;

use VuFindSearch\Backend\BrowZine\Backend;
use VuFindSearch\Backend\BrowZine\Connector;
use VuFindSearch\Backend\BrowZine\QueryBuilder;
use VuFindSearch\Backend\BrowZine\Response\RecordCollectionFactory;

use Zend\ServiceManager\Factory\FactoryInterface;

/**
 * Factory for BrowZine backend.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class BrowZineBackendFactory implements FactoryInterface
{
    /**
     * Logger.
     *
     * @var \Zend\Log\LoggerInterface
     */
    protected $logger;

    /**
     * Superior service manager.
     *
     * @var ContainerInterface
     */
    protected $serviceLocator;

    /**
     * BrowZine configuration
     *
     * @var \Zend\Config\Config
     */
    protected $browzineConfig;

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
    public function __invoke(ContainerInterface $sm, $name, array $options = null)
    {
        $this->serviceLocator = $sm;
        $configReader = $this->serviceLocator
            ->get(\VuFind\Config\PluginManager::class);
        $this->browzineConfig = $configReader->get('BrowZine');
        if ($this->serviceLocator->has(\VuFind\Log\Logger::class)) {
            $this->logger = $this->serviceLocator->get(\VuFind\Log\Logger::class);
        }

        $connector = $this->createConnector();
        $backend   = $this->createBackend($connector);

        return $backend;
    }

    /**
     * Create the Primo Central backend.
     *
     * @param Connector $connector Connector
     *
     * @return Backend
     */
    protected function createBackend(Connector $connector)
    {
        $backend = new Backend($connector, $this->createRecordCollectionFactory());
        $backend->setLogger($this->logger);
        $backend->setQueryBuilder($this->createQueryBuilder());
        return $backend;
    }

    /**
     * Create the Primo Central connector.
     *
     * @return Connector
     */
    protected function createConnector()
    {
        // Validate configuration:
        if (empty($this->browzineConfig->General->access_token)) {
            throw new \Exception("Missing access token in BrowZine.ini");
        }
        if (empty($this->browzineConfig->General->library_id)) {
            throw new \Exception("Missing library ID in BrowZine.ini");
        }
        // Build HTTP client:
        $client = $this->serviceLocator->get(\VuFindHttp\HttpService::class)
            ->createClient();
        $timeout = isset($this->browzineConfig->General->timeout)
            ? $this->browzineConfig->General->timeout : 30;
        $client->setOptions(['timeout' => $timeout]);

        $connector = new Connector(
            $client,
            $this->browzineConfig->General->access_token,
            $this->browzineConfig->General->library_id
        );
        $connector->setLogger($this->logger);
        return $connector;
    }

    /**
     * Create the Primo query builder.
     *
     * @return QueryBuilder
     */
    protected function createQueryBuilder()
    {
        $builder = new QueryBuilder();
        return $builder;
    }

    /**
     * Create the record collection factory
     *
     * @return RecordCollectionFactory
     */
    protected function createRecordCollectionFactory()
    {
        $manager = $this->serviceLocator
            ->get(\VuFind\RecordDriver\PluginManager::class);
        $callback = function ($data) use ($manager) {
            $driver = $manager->get('BrowZine');
            $driver->setRawData($data);
            return $driver;
        };
        return new RecordCollectionFactory($callback);
    }
}
