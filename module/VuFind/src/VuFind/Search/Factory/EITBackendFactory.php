<?php
/**
 * Factory for EBSCO Integration Toolkit (EIT) backends.
 *
 * PHP version 7
 *
 * Copyright (C) Julia Bauder 2013.
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
 * @author   Julia Bauder <bauderj@grinnell.edu>
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace VuFind\Search\Factory;

use Interop\Container\ContainerInterface;

use VuFindSearch\Backend\EIT\Backend;
use VuFindSearch\Backend\EIT\Connector;
use VuFindSearch\Backend\EIT\QueryBuilder;
use VuFindSearch\Backend\EIT\Response\XML\RecordCollectionFactory;

use Zend\ServiceManager\Factory\FactoryInterface;

/**
 * Factory for EIT backends.
 *
 * @category VuFind
 * @package  Search
 * @author   Julia Bauder <bauderj@grinnell.edu>
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class EITBackendFactory implements FactoryInterface
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
     * VuFind configuration
     *
     * @var \Zend\Config\Config
     */
    protected $config;

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
        $this->config = $this->serviceLocator
            ->get(\VuFind\Config\PluginManager::class)
            ->get('EIT');
        if ($this->serviceLocator->has(\VuFind\Log\Logger::class)) {
            $this->logger = $this->serviceLocator->get(\VuFind\Log\Logger::class);
        }
        $connector = $this->createConnector();
        $backend   = $this->createBackend($connector);
        return $backend;
    }

    /**
     * Create the EIT backend.
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
     * Create the EIT connector.
     *
     * @return Connector
     */
    protected function createConnector()
    {
        $prof = isset($this->config->General->prof)
            ? $this->config->General->prof : null;
        $pwd = isset($this->config->General->pwd)
            ? $this->config->General->pwd : null;
        $base = "http://eit.ebscohost.com/Services/SearchService.asmx/Search";
        $dbs =  isset($this->config->General->dbs)
            ? $this->config->General->dbs : null;
        $client = $this->serviceLocator->get(\VuFindHttp\HttpService::class)
            ->createClient();
        $connector = new Connector($base, $client, $prof, $pwd, $dbs);
        $connector->setLogger($this->logger);
        return $connector;
    }

    /**
     * Create the EIT query builder.
     *
     * @return QueryBuilder
     */
    protected function createQueryBuilder()
    {
        return new QueryBuilder();
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
            $driver = $manager->get('EIT');
            $driver->setRawData($data);
            return $driver;
        };
        return new RecordCollectionFactory($callback);
    }
}
