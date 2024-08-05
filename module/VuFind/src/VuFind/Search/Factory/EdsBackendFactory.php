<?php

/**
 * Factory for EDS backends.
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

use Psr\Container\ContainerInterface;
use VuFindSearch\Backend\EDS\Backend;
use VuFindSearch\Backend\EDS\Connector;
use VuFindSearch\Backend\EDS\QueryBuilder;
use VuFindSearch\Backend\EDS\Response\RecordCollectionFactory;

/**
 * Factory for EDS backends.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class EdsBackendFactory extends AbstractBackendFactory
{
    use SharedListenersTrait;

    /**
     * Logger.
     *
     * @var \Laminas\Log\LoggerInterface
     */
    protected $logger = null;

    /**
     * EDS configuration
     *
     * @var \Laminas\Config\Config
     */
    protected $edsConfig;

    /**
     * EDS Account data
     *
     * @var array
     */
    protected $accountData;

    /**
     * Default URL for the EDS Backend.  Set here for the EDS API.
     *
     * @var str
     */
    protected $defaultApiUrl = 'https://eds-api.ebscohost.com/edsapi/rest';

    /**
     * Get the service name. This is used for both configuration
     * and record driver retrieval.
     *
     * @return str
     */
    protected function getServiceName()
    {
        return 'EDS';
    }

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
        $this->edsConfig = $this->getService(\VuFind\Config\PluginManager::class)
            ->get($this->getServiceName());
        if ($this->serviceLocator->has(\VuFind\Log\Logger::class)) {
            $this->logger = $this->getService(\VuFind\Log\Logger::class);
        }
        $connector = $this->createConnector();
        $backend = $this->createBackend($connector);
        $this->createListeners($backend);
        return $backend;
    }

    /**
     * Create the EDS backend.
     *
     * @param Connector $connector Connector
     *
     * @return Backend
     */
    protected function createBackend(Connector $connector)
    {
        $auth = $this->getService(\LmcRbacMvc\Service\AuthorizationService::class);
        $isGuest = !$auth->isGranted('access.EDSExtendedResults');
        $session = new \Laminas\Session\Container(
            'EBSCO',
            $this->getService(\Laminas\Session\SessionManager::class)
        );
        $backend = new Backend(
            $connector,
            $this->createRecordCollectionFactory(),
            $this->getService(\VuFind\Cache\Manager::class)
                ->getCache('object'),
            $session,
            $this->edsConfig,
            $isGuest
        );
        $backend->setAuthManager(
            $this->getService(\VuFind\Auth\Manager::class)
        );
        $backend->setLogger($this->logger);
        $backend->setQueryBuilder($this->createQueryBuilder());
        $backend->setBackendType($this->getServiceName());
        return $backend;
    }

    /**
     * Create the EDS connector.
     *
     * @return Connector
     */
    protected function createConnector()
    {
        $options = $this->createConnectorOptions();
        $httpOptions = [
            'sslverifypeer'
                => (bool)($this->edsConfig->General->sslverifypeer ?? true),
        ];
        $connector = new Connector(
            $options,
            $this->createHttpClient(
                $this->edsConfig->General->timeout ?? 120,
                $httpOptions
            )
        );
        $connector->setLogger($this->logger);
        if ($cache = $this->createConnectorCache($this->edsConfig)) {
            $connector->setCache($cache);
        }
        return $connector;
    }

    /**
     * Create the options array for the EDS connector.
     *
     * @return array
     */
    protected function createConnectorOptions()
    {
        $options = [
            'search_http_method' => $this->edsConfig->General->search_http_method
                ?? 'POST',
            'api_url' => $this->edsConfig->General->api_url
                ?? $this->defaultApiUrl,
        ];
        if (isset($this->edsConfig->General->auth_url)) {
            $options['auth_url'] = $this->edsConfig->General->auth_url;
        }
        if (isset($this->edsConfig->General->session_url)) {
            $options['session_url'] = $this->edsConfig->General->session_url;
        }
        return $options;
    }

    /**
     * Create the EDS query builder.
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
        $manager = $this->getService(\VuFind\RecordDriver\PluginManager::class);
        $callback = function ($data) use ($manager) {
            $driver = $manager->get($this->getServiceName());
            $driver->setRawData($data);
            return $driver;
        };
        return new RecordCollectionFactory($callback);
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
        $events = $this->getService('SharedEventManager');

        // Attach hide facet value listener:
        $hfvListener = $this->getHideFacetValueListener($backend, $this->edsConfig);
        if ($hfvListener) {
            $hfvListener->attach($events);
        }
    }
}
