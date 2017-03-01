<?php

/**
 * Factory for Primo Central backends.
 *
 * PHP version 5
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace VuFind\Search\Factory;

use VuFindSearch\Backend\Primo\Connector;
use VuFindSearch\Backend\BackendInterface;
use VuFindSearch\Backend\Primo\Response\RecordCollectionFactory;
use VuFindSearch\Backend\Primo\QueryBuilder;
use VuFindSearch\Backend\Primo\Backend;

use VuFind\Search\Primo\InjectOnCampusListener;
use VuFind\Search\Primo\PrimoPermissionHandler;

use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\ServiceManager\FactoryInterface;

/**
 * Factory for Primo Central backends.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class PrimoBackendFactory implements FactoryInterface
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
     * Primo configuration
     *
     * @var \Zend\Config\Config
     */
    protected $primoConfig;

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
        $configReader = $this->serviceLocator->get('VuFind\Config');
        $this->primoConfig = $configReader->get('Primo');
        if ($this->serviceLocator->has('VuFind\Logger')) {
            $this->logger = $this->serviceLocator->get('VuFind\Logger');
        }

        $connector = $this->createConnector();
        $backend   = $this->createBackend($connector);

        $this->createListeners($backend);

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
     * Create listeners.
     *
     * @param Backend $backend Backend
     *
     * @return void
     */
    protected function createListeners(Backend $backend)
    {
        $events = $this->serviceLocator->get('SharedEventManager');

        $this->getInjectOnCampusListener()->attach($events);
    }

    /**
     * Create the Primo Central connector.
     *
     * @return Connector
     */
    protected function createConnector()
    {
        // Get the PermissionHandler
        $permHandler = $this->getPermissionHandler();

        // Load url and credentials:
        if (!isset($this->primoConfig->General->url)) {
            throw new \Exception('Missing url in Primo.ini');
        }
        $instCode = isset($permHandler)
            ? $permHandler->getInstCode()
            : null;

        // Build HTTP client:
        $client = $this->serviceLocator->get('VuFind\Http')->createClient();
        $timeout = isset($this->primoConfig->General->timeout)
            ? $this->primoConfig->General->timeout : 30;
        $client->setOptions(['timeout' => $timeout]);

        $connector = new Connector(
            $this->primoConfig->General->url, $instCode, $client
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
        $manager = $this->serviceLocator->get('VuFind\RecordDriverPluginManager');
        $callback = function ($data) use ($manager) {
            $driver = $manager->get('Primo');
            $driver->setRawData($data);
            return $driver;
        };
        return new RecordCollectionFactory($callback);
    }

    /**
     * Get a OnCampus Listener
     *
     * @return InjectOnCampusListener
     */
    protected function getInjectOnCampusListener()
    {
        $listener = new InjectOnCampusListener($this->getPermissionHandler());
        return $listener;
    }

    /**
     * Get a PrimoPermissionHandler
     *
     * @return PrimoPermissionHandler
     */
    protected function getPermissionHandler()
    {
        if (isset($this->primoConfig->Institutions)) {
            $permHandler = new PrimoPermissionHandler(
                $this->primoConfig->Institutions
            );
            $permHandler->setAuthorizationService(
                $this->serviceLocator->get('ZfcRbac\Service\AuthorizationService')
            );
            return $permHandler;
        }

        // If no PermissionHandler can be set, return null
        return null;
    }
}
