<?php

/**
 * Factory for Primo Central backends.
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
use Lmc\Rbac\Mvc\Service\AuthorizationService;
use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use VuFind\Config\Config;
use VuFind\Search\Primo\InjectOnCampusListener;
use VuFind\Search\Primo\PrimoPermissionHandler;
use VuFindSearch\Backend\Primo\Backend;
use VuFindSearch\Backend\Primo\ConnectorInterface;
use VuFindSearch\Backend\Primo\QueryBuilder;
use VuFindSearch\Backend\Primo\Response\RecordCollectionFactory;
use VuFindSearch\Backend\Primo\RestConnector;

use function in_array;

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
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

    /**
     * Primo configuration
     *
     * @var Config
     */
    protected Config $primoConfig;

    /**
     * Primo backend class
     *
     * @var string
     */
    protected string $backendClass = Backend::class;

    /**
     * Primo REST API connector class
     *
     * @var string
     */
    protected string $restConnectorClass = RestConnector::class;

    /**
     * CDI attribute mappings
     *
     * @var array
     */
    protected array $attributeLabelTypeMappings = [
        'review_article' => [
            'display' => 'RecordAttribute::Review Article',
            'type' => 'notice',
        ],
        'primary_source' => [
            'display' => 'RecordAttribute::Primary Source',
            'type' => 'notice',
        ],
        'preprint' => [
            'display' => 'RecordAttribute::Preprint',
            'type' => 'notice',
        ],
        'retracted_publication' => [
            'display' => 'RecordAttribute::Retracted Publication',
            'type' => 'warning',
        ],
        'retraction_notice' => [
            'display' => 'RecordAttribute::Retraction Notice',
            'type' => 'warning',
        ],
        'publication_with_addendum' => [
            'display' => 'RecordAttribute::Publication with Addendum',
            'type' => 'warning',
        ],
        'publication_with_corrigendum' => [
            'display' => 'RecordAttribute::Publication with Corrigendum',
            'type' => 'warning',
        ],
    ];

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
        $this->primoConfig = $this->getService(\VuFind\Config\ConfigManagerInterface::class)->getConfigObject('Primo');
        if ($this->serviceLocator->has(\VuFind\Log\Logger::class)) {
            $this->logger = $this->getService(\VuFind\Log\Logger::class);
        }

        $connector = $this->createRestConnector();
        $backend   = $this->createBackend($connector);

        $this->createListeners($backend);

        return $backend;
    }

    /**
     * Create the Primo Central backend.
     *
     * @param ConnectorInterface $connector Connector
     *
     * @return Backend
     */
    protected function createBackend(ConnectorInterface $connector): Backend
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
    protected function createListeners(Backend $backend): void
    {
        $events = $this->getService('SharedEventManager');

        $this->getInjectOnCampusListener()->attach($events);

        // Attach hide facet value listener:
        $hfvListener = $this
            ->getHideFacetValueListener($backend, $this->primoConfig);
        if ($hfvListener) {
            $hfvListener->attach($events);
        }
    }

    /**
     * Create the Primo Central REST connector.
     *
     * @return RestConnector
     */
    protected function createRestConnector(): RestConnector
    {
        // Get the PermissionHandler
        $permHandler = $this->getPermissionHandler();

        // Load URLs and credentials:
        if (empty($this->primoConfig->General->search_url)) {
            throw new \Exception('Missing search_url in Primo.ini');
        }
        $instCode = isset($permHandler)
            ? $permHandler->getInstCode()
            : null;

        $session = new \Laminas\Session\Container(
            'Primo',
            $this->getService(\Laminas\Session\SessionManager::class)
        );

        // Create connector:
        $timeout = $this->primoConfig->General->timeout ?? 30;
        $connector = new $this->restConnectorClass(
            $this->primoConfig->General->jwt_url ?? '',
            $this->primoConfig->General->search_url,
            $instCode,
            function (string $url) use ($timeout) {
                return $this->createHttpClient(
                    $timeout,
                    $this->getHttpOptions($url),
                    $url
                );
            },
            $session
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
            $driver = $manager->get('Primo');
            $driver->setRawData($data);
            if ($this->primoConfig->display_cdi_attributes ?? true) {
                foreach ($this->attributeLabelTypeMappings as $key => $config) {
                    if (in_array($key, $data['attributes'] ?? [])) {
                        $driver->addLabel($config['display'], $config['type']);
                    }
                }
            }
            return $driver;
        };
        return new RecordCollectionFactory($callback);
    }

    /**
     * Get a OnCampus Listener
     *
     * @return InjectOnCampusListener
     */
    protected function getInjectOnCampusListener(): InjectOnCampusListener
    {
        return new InjectOnCampusListener($this->getPermissionHandler());
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
                $this->getService(AuthorizationService::class)
            );
            return $permHandler;
        }

        // If no PermissionHandler can be set, return null
        return null;
    }

    /**
     * Get HTTP options for the client
     *
     * @param string $url URL being requested
     *
     * @return array
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function getHttpOptions(string $url): array
    {
        return [];
    }
}
