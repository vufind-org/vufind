<?php

/**
 * Factory for LibGuides A-Z Databases backends.
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
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Search\Factory;

use Psr\Container\ContainerInterface;

/**
 * Abstract factory for LibGuides backends.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
abstract class AbstractLibGuidesBackendFactory extends AbstractBackendFactory
{
    /**
     * Logger.
     *
     * @var \Laminas\Log\LoggerInterface
     */
    protected $logger;

    /**
     * LibGuides configuration
     *
     * @var \Laminas\Config\Config
     */
    protected $libGuidesConfig;

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
        $this->libGuidesConfig = $configReader->get('LibGuides');
        if ($this->serviceLocator->has(\VuFind\Log\Logger::class)) {
            $this->logger = $this->serviceLocator->get(\VuFind\Log\Logger::class);
        }
        $connector = $this->createConnector();
        $backend   = $this->createBackend($connector);
        return $backend;
    }

    /**
     * Create the LibGuides backend.
     *
     * @param Connector $connector Connector
     *
     * @return Backend
     */
    protected function createBackend($connector)
    {
        $defaultSearch = $this->libGuidesConfig->General->defaultSearch ?? null;
        $backend = $this->createBackendInstance(
            $connector,
            $this->createRecordCollectionFactory(),
            $defaultSearch
        );
        $backend->setLogger($this->logger);
        $backend->setQueryBuilder($this->createQueryBuilderInstance());
        return $backend;
    }

    /**
     * Instantiate the LibGuidesAZ backend.
     *
     * @param Connector                        $connector     LibGuides connector
     * @param RecordCollectionFactoryInterface $factory       Record collection
     * factory (null for default)
     * @param string                           $defaultSearch Default search query
     *
     * @return Backend
     */
    abstract protected function createBackendInstance($connector, $factory, $defaultSearch);

    /**
     * Create the LibGuides connector.
     *
     * @return Connector
     */
    protected function createConnector()
    {
        // Load credentials:
        $iid = $this->libGuidesConfig->General->iid ?? null;

        // Pick version:
        $ver = $this->libGuidesConfig->General->version ?? 1;

        // Get base URI, if available:
        $baseUrl = $this->libGuidesConfig->General->baseUrl ?? null;

        // Create connector:
        $connector = $this->createConnectorInstance(
            $iid,
            $this->createHttpClient($this->libGuidesConfig->General->timeout ?? 30),
            $ver,
            $baseUrl
        );
        $connector->setLogger($this->logger);
        return $connector;
    }

    /**
     * Instantiate the LibGuidesAZ connector.
     *
     * @param string     $iid     Institution ID
     * @param HttpClient $client  HTTP client
     * @param float      $ver     API version number
     * @param string     $baseUrl API base URL (optional)
     *
     * @return Connector
     */
    abstract protected function createConnectorInstance($iid, $client, $ver, $baseUrl);

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
            $driver = $manager->get($this->getServiceName());
            $driver->setRawData($data);
            return $driver;
        };
        return $this->createRecordCollectionFactoryInstance($callback);
    }

    /**
     * Return the service name.
     *
     * @return string
     */
    abstract protected function getServiceName();

    /**
     * Instantiate the LibGuidesAZ record collection factory.
     *
     * @param callback $callback Record factory callback (null for default)
     *
     * @return RecordCollectionFactory
     */
    abstract protected function createRecordCollectionFactoryInstance($callback);
}
