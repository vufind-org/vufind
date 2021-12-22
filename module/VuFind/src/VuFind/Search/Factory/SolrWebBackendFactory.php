<?php

/**
 * Factory for the website SOLR backend.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace VuFind\Search\Factory;

/**
 * Factory for the website SOLR backend.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class SolrWebBackendFactory extends AbstractSolrBackendFactory
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->searchConfig = 'website';
        $this->searchYaml = 'websearchspecs.yaml';
        $this->facetConfig = 'website';
    }

    /**
     * Get the Solr core.
     *
     * @return string
     */
    protected function getSolrCore()
    {
        $config = $this->config->get($this->searchConfig);
        return $config->Index->default_core ?? 'website';
    }

    /**
     * Get the Solr URL.
     *
     * @param string $config name of configuration file (null for default)
     *
     * @return string
     */
    protected function getSolrUrl($config = null)
    {
        // Only override parent default if valid value present in config:
        $configToCheck = $config ?? $this->searchConfig;
        $webConfig = $this->config->get($configToCheck);
        $finalConfig = isset($webConfig->Index->url) ? $configToCheck : null;
        return parent::getSolrUrl($finalConfig);
    }

    /**
     * Get the callback for creating a record.
     *
     * Returns a callable or null to use RecordCollectionFactory's default method.
     *
     * @return callable|null
     */
    protected function getCreateRecordCallback(): ?callable
    {
        $manager = $this->serviceLocator
            ->get(\VuFind\RecordDriver\PluginManager::class);
        return function ($data) use ($manager) {
            $driver = $manager->get('SolrWeb');
            $driver->setRawData($data);
            return $driver;
        };
    }
}
