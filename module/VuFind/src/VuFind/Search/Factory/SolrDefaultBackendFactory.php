<?php

/**
 * Factory for the default SOLR backend.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

namespace VuFind\Search\Factory;

use VuFind\Config\Reader;

use VuFindSearch\Backend\BackendInterface;
use VuFindSearch\Backend\Solr\QueryBuilder;
use VuFindSearch\Backend\Solr\Connector;
use VuFindSearch\Backend\Solr\Backend;

use VuFind\Search\Listener\NormalizeSolrSort;

use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\ServiceManager\FactoryInterface;

use Zend\Config\Reader\Yaml as YamlReader;
use VuFind\Config\Reader\CacheDecorator as YamlCacheReader;

/**
 * Factory for the default SOLR backend.
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class SolrDefaultBackendFactory implements FactoryInterface
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
     * Create the backend.
     *
     * @param ServiceLocatorInterface $serviceLocator Superior service manager
     *
     * @return BackendInterface
     */
    public function createService (ServiceLocatorInterface $serviceLocator)
    {
        $this->serviceLocator = $serviceLocator;
        if ($this->serviceLocator->has('VuFind\Logger')) {
            $this->logger = $this->serviceLocator->get('VuFind\Logger');
        }
        $connector = $this->createConnector();
        $backend   = $this->createBackend('biblio', $connector);
        $this->createListeners($backend);
        return $backend;
    }

    /**
     * Create the SOLR backend.
     *
     * @param string    $identifier Backend identifier
     * @param Connector $connector  Connector
     *
     * @return Backend
     */
    protected function createBackend ($identifier, Connector $connector)
    {
        $backend = new Backend($identifier, $connector);
        $specs   = $this->loadSpecs();
        $builder = new QueryBuilder($specs);
        $backend->setQueryBuilder($builder);
        if ($this->logger) {
            $backend->setLogger($this->logger);
        }
        return $backend;
    }

    /**
     * Create listeners.
     *
     * @param Backend $backend Backend
     *
     * @return void
     */
    protected function createListeners (Backend $backend)
    {
        $events = $this->serviceLocator->get('SharedEventManager');
        // Normalize sort directive
        $listener = new NormalizeSolrSort($backend);
        $listener->attach($events);
    }

    /**
     * Create the SOLR connector.
     *
     * @return Connector
     */
    protected function createConnector ()
    {
        $config  = Reader::getConfig();
        $url     = $config->Index->url . '/';
        $url    .= isset($config->Index->default_core) ? $config->Index->default_core : 'biblio';

        $connector = new Connector($url);
        $connector->setTimeout($config->Index->timeout);
        if ($this->logger) {
            $connector->setLogger($this->logger);
        }
        if ($this->serviceLocator->has('VuFind\Http')) {
            $connector->setProxy($this->serviceLocator->get('VuFind\Http'));
        }
        return $connector;
    }

    /**
     * Load the search specs.
     *
     * @return array
     */
    protected function loadSpecs ()
    {
        $global = Reader::getBaseConfigPath('searchspecs.yaml');
        $local  = Reader::getLocalConfigPath('searchspecs.yaml');
        if ($this->serviceLocator->has('VuFind\CacheManager')) {
            $cache  = $this->serviceLocator->get('VuFind\CacheManager')->getCache('searchspecs');
            $reader = new YamlCacheReader(new YamlReader('Symfony\Component\Yaml\Yaml::parse'), $cache);
        } else {
            $reader = new YamlReader(new YamlReader('Symfony\Component\Yaml\Yaml::parse'));
        }
        $specs = $reader->fromFile($global);
        if ($local) {
            foreach ($reader->fromFile($local) as $key => $value) {
                $specs[$key] = $value;
            }
        }
        return $specs;
    }
}