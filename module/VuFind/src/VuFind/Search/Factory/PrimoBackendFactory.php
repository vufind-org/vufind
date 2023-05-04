<?php

/**
 * Factory for Primo Central backends.
 *
 * PHP version 7
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

use LmcRbacMvc\Service\AuthorizationService;
use Psr\Container\ContainerInterface;
use VuFind\Search\Primo\InjectOnCampusListener;
use VuFind\Search\Primo\PrimoPermissionHandler;
use VuFindSearch\Backend\Primo\Backend;
use VuFindSearch\Backend\Primo\Connector;
use VuFindSearch\Backend\Primo\QueryBuilder;
use VuFindSearch\Backend\Primo\Response\RecordCollectionFactory;

/**
 * Factory for Primo Central backends.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class PrimoBackendFactory extends AbstractBackendFactory
{
    use SharedListenersTrait;

    /**
     * Logger.
     *
     * @var \Laminas\Log\LoggerInterface
     */
    protected $logger;

    /**
     * Primo configuration
     *
     * @var \Laminas\Config\Config
     */
    protected $primoConfig;

    /**
     * Primo backend class
     *
     * @var string
     */
    protected $backendClass = Backend::class;

    /**
     * Primo connector class
     *
     * @var string
     */
    protected $connectorClass = Connector::class;

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
        $this->setup($sm);
        $configReader = $this->serviceLocator
            ->get(\VuFind\Config\PluginManager::class);
        $this->primoConfig = $configReader->get('Primo');
        if ($this->serviceLocator->has(\VuFind\Log\Logger::class)) {
            $this->logger = $this->serviceLocator->get(\VuFind\Log\Logger::class);
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
        $backend = new $this->backendClass(
            $connector,
            $this->createRecordCollectionFactory()
        );
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

        // Attach hide facet value listener:
        $hfvListener = $this
            ->getHideFacetValueListener($backend, $this->primoConfig);
        if ($hfvListener) {
            $hfvListener->attach($events);
        }
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

        // Create connector:
        $connector = new $this->connectorClass(
            $this->primoConfig->General->url,
            $instCode,
            $this->createHttpClient($this->primoConfig->General->timeout ?? 30)
        );
        $connector->setLogger($this->logger);
        if ($cache = $this->createConnectorCache($this->primoConfig)) {
            $connector->setCache($cache);
        }
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
     * @return ?PrimoPermissionHandler
     */
    protected function getPermissionHandler(): ?PrimoPermissionHandler
    {
        if (isset($this->primoConfig->Institutions)) {
            $permHandler = new PrimoPermissionHandler(
                $this->primoConfig->Institutions
            );
            $permHandler->setAuthorizationService(
                $this->serviceLocator->get(AuthorizationService::class)
            );
            return $permHandler;
        }

        // If no PermissionHandler can be set, return null
        return null;
    }
}
