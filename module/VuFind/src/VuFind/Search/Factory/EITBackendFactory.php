<?php
/**
 * Factory for EBSCO Integration Toolkit (EIT) backends.
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Search
 * @author   Julia Bauder <bauderj@grinnell.edu>
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Search\Factory;

use VuFindSearch\Backend\BackendInterface;
use VuFindSearch\Backend\EIT\Response\XML\RecordCollectionFactory;
use VuFindSearch\Backend\EIT\QueryBuilder;
use VuFindSearch\Backend\EIT\Connector;
use VuFindSearch\Backend\EIT\Backend;

use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\ServiceManager\FactoryInterface;

/**
 * Factory for EIT backends.
 *
 * @category VuFind2
 * @package  Search
 * @author   Julia Bauder <bauderj@grinnell.edu>
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class EITBackendFactory implements FactoryInterface
{
    /**
     * Logger.
     *
     * @var Zend\Log\LoggerInterface
     */
    protected $logger;

    /**
     * Superior service manager.
     *
     * @var ServiceLocatorInterface
     */
    protected $serviceLocator;

    /**
     * VuFind configuration
     *
     * @var \Zend\Config\Config
     */
    protected $config;

    /**
     * Create the backend.
     *
     * @param ServiceLocatorInterface $serviceLocator Superior service manager
     *
     * @return BackendInterface
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $this->serviceLocator = $serviceLocator;
        $this->config = $this->serviceLocator->get('VuFind\Config')->get('EIT');
        if ($this->serviceLocator->has('VuFind\Logger')) {
            $this->logger = $this->serviceLocator->get('VuFind\Logger');
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
        $connector = new Connector(
            $base,
            $this->serviceLocator->get('VuFind\Http')->createClient(),
            $prof, $pwd, $dbs
        );
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
        $manager = $this->serviceLocator->get('VuFind\RecordDriverPluginManager');
        $callback = function ($data) use ($manager) {
            $driver = $manager->get('EIT');
            $driver->setRawData($data);
            return $driver;
        };
        return new RecordCollectionFactory($callback);
    }
}
