<?php

/**
 * Factory for a second Solr backend
 *
 * PHP version 7
 *
 * Copyright (C) Staats- und Universitätsbibliothek Hamburg 2018.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Search_Factory
 * @author   Hajo Seng <hajo.seng@sub.uni-hamburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace VuFind\Search\Factory;

use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Factory for a second Solr backend
 *
 * @category VuFind
 * @package  Search_Factory
 * @author   Hajo Seng <hajo.seng@sub.uni-hamburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class SolrAlternativeBackendFactory extends SolrDefaultBackendFactory
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->searchConfig = 'SolrAlternative';
    }

    /**
     * Create the backend.
     *
     * @param ServiceLocatorInterface $serviceLocator Superior service manager
     *
     * @return BackendInterface
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $backend = parent::createService($serviceLocator);
        $this->searchConfig = 'SolrAlternative';
        return $backend;
    }

    /**
     * Get the Solr core.
     *
     * @return string
     */
    protected function getSolrCore()
    {
        $core = $this->config->get($this->searchConfig)->General->default_core;
        return isset($core)
            ? $core : 'biblio';
    }

    /**
     * Get the Solr URL.
     *
     * @return string|array
     */
    protected function getSolrUrl()
    {
        $url = $this->config->get($this->searchConfig)->General->url;
        $core = $this->getSolrCore();
        if (is_object($url)) {
            return array_map(
                function ($value) use ($core) {
                    return "$value/$core";
                },
                $url->toArray()
            );
        }
        return "$url/$core";
    }
}
