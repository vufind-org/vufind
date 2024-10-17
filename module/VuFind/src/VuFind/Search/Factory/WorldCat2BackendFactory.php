<?php

/**
 * Factory for WorldCat v2 backends.
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Search\Factory;

use Laminas\Session\Container;
use League\OAuth2\Client\OptionProvider\HttpBasicAuthOptionProvider;
use League\OAuth2\Client\Provider\GenericProvider;
use Psr\Container\ContainerInterface;
use VuFind\Http\GuzzleService;
use VuFindSearch\Backend\WorldCat2\Backend;
use VuFindSearch\Backend\WorldCat2\Connector;
use VuFindSearch\Backend\WorldCat2\QueryBuilder;
use VuFindSearch\Backend\WorldCat2\Response\RecordCollectionFactory;

/**
 * Factory for WorldCat v2 backends.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class WorldCat2BackendFactory extends AbstractBackendFactory
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
     * @var \Laminas\Config\Config
     */
    protected $config;

    /**
     * WorldCat v2 configuration
     *
     * @var \Laminas\Config\Config
     */
    protected $wcConfig;

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
        $configManager = $this->getService(\VuFind\Config\PluginManager::class);
        $this->config = $configManager->get('config');
        $this->wcConfig = $configManager->get('WorldCat2');
        if ($this->serviceLocator->has(\VuFind\Log\Logger::class)) {
            $this->logger = $this->getService(\VuFind\Log\Logger::class);
        }
        $connector = $this->createConnector();
        $backend   = $this->createBackend($connector);
        return $backend;
    }

    /**
     * Create the WorldCat backend.
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
     * Create the OAuth2 provider for the connector.
     *
     * @param array $options Configuration options
     *
     * @return GenericProvider
     */
    protected function createAuthProvider(array $options): GenericProvider
    {
        foreach (['wskey', 'secret'] as $param) {
            if (empty($options[$param])) {
                throw new \Exception($param . ' setting is required in WorldCat2.ini [Connector] section');
            }
        }
        $authOptions = [
            'clientId' => $options['wskey'],
            'clientSecret' => $options['secret'],
            'urlAuthorize' => $options['auth_url'] ?? 'https://oauth.oclc.org/auth',
            'urlAccessToken' => $options['token_url'] ?? 'https://oauth.oclc.org/token',
            'urlResourceOwnerDetails' => '',
        ];
        $optionProvider = new HttpBasicAuthOptionProvider();
        $provider = new GenericProvider($authOptions, compact('optionProvider'));
        $provider->setHttpClient(
            $this->getService(GuzzleService::class)->createClient()
        );
        return $provider;
    }

    /**
     * Create the WorldCat connector.
     *
     * @return Connector
     */
    protected function createConnector()
    {
        $connectorOptions = $this?->wcConfig?->Connector?->toArray() ?? [];
        $connector = new Connector(
            $this->createHttpClient(),
            $this->createAuthProvider($connectorOptions),
            new Container('WorldCat2', $this->getService(\Laminas\Session\SessionManager::class)),
            $connectorOptions
        );
        $connector->setLogger($this->logger);
        return $connector;
    }

    /**
     * Create the WorldCat query builder.
     *
     * @return QueryBuilder
     */
    protected function createQueryBuilder()
    {
        $exclude = $this->wcConfig->General->exclude_code ?? null;
        return new QueryBuilder($exclude);
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
            $driver = $manager->get('WorldCat2');
            $driver->setRawData($data);
            return $driver;
        };
        return new RecordCollectionFactory($callback);
    }
}
