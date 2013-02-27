<?php

/**
 * Abstract factory for SOLR backends.
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

use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\ServiceManager\FactoryInterface;

use Zend\Config\Reader\Yaml as YamlReader;
use VuFind\Config\Reader\CacheDecorator as YamlCacheReader;

/**
 * Abstract factory for SOLR backends.
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
abstract class AbstractSolrBackendFactory implements FactoryInterface
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
     * Search configuration file identifier.
     *
     * @var string
     */
    protected $searchConfig;

    /**
     * YAML searchspecs filename.
     *
     * @var string
     */
    protected $searchYaml;

    /**
     * Main VuFind configuration
     *
     * @var \Zend\Config\Config
     */
    protected $config;

    /**
     * Solr core name
     *
     * @var string
     */
    protected $solrCore = '';

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->config = Reader::getConfig();
    }

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
        $backend   = $this->createBackend($connector);
        $this->createListeners($backend);
        return $backend;
    }

    /**
     * Create the SOLR backend.
     *
     * @param Connector $connector  Connector
     *
     * @return Backend
     */
    protected function createBackend (Connector $connector)
    {
        $backend = new Backend($connector);
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
    }

    /**
     * Create the SOLR connector.
     *
     * @return Connector
     */
    protected function createConnector ()
    {
        $searchSettings = Reader::getConfig($this->searchConfig);

        $url = $this->config->Index->url . '/' . $this->solrCore;

        $connector = new Connector($url);
        $connector->setTimeout(
            isset($this->config->Index->timeout) ? $this->config->Index->timeout : 30
        );

        $hl = !isset($searchSettings->General->highlighting) ? false : $searchSettings->General->highlighting;
        $sn = !isset($searchSettings->General->snippets)     ? false : $searchSettings->General->snippets;
        if ($hl || $sn) {
            $connector->addQueryInvariant('hl', 'true');
            $connector->addQueryInvariant('hl.fl', '*');
            $connector->addQueryInvariant('hl.simple.pre', '{{{{START_HILITE}}}}');
            $connector->addQueryInvariant('hl.simple.post', '{{{{END_HILITE}}}}');
        }

        // Hidden filters
        if (isset($searchSettings->HiddenFilters)) {
            foreach ($searchSettings->HiddenFilters as $field => $value) {
                $connector->addQueryInvariant('fq', sprintf('%s:"%s"', $field, $value));
            }
        }
        // Raw hidden filters
        if (isset($searchSettings->RawHiddenFilters)) {
            foreach ($searchSettings->RawHiddenFilters as $filter) {
                $connector->addQueryInvariant('fq', $filter);
            }
        }

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
        $global = Reader::getBaseConfigPath($this->searchYaml);
        $local  = Reader::getLocalConfigPath($this->searchYaml);
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