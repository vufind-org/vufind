<?php
/**
 * Caching connector feature trait for SOLR backend factories.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2021.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\Search\Factory;

use VuFindSearch\Backend\Solr\Connector;

/**
 * Caching connector feature trait for SOLR backend factories.
 *
 * @category VuFind
 * @package  Search
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
trait   SolrCachingConnectorFactoryTrait
{
    /**
     * Create the SOLR connector.
     *
     * @return Connector
     */
    protected function createConnector()
    {
        $connector = parent::createConnector();
        $vufindConfig = $this->config->get('config');
        if (!empty($vufindConfig->Http->adapter)) {
            $connector->setAdapter($vufindConfig->Http->adapter);
        }

        if (!empty($vufindConfig->IndexCache->adapter)) {
            $options = isset($vufindConfig->IndexCache->options)
                ? $vufindConfig->IndexCache->options->toArray() : [];
            if (empty($options['namespace'])) {
                $options['namespace'] = 'Index';
            }
            if (empty($options['ttl'])) {
                $options['ttl'] = 300;
            }
            if ('Memcached' === $vufindConfig->IndexCache->adapter) {
                $servers = isset($vufindConfig->IndexCache->servers)
                    ? $vufindConfig->IndexCache->servers->toArray()
                    : ['localhost:11211'];
                foreach ($servers as $server) {
                    $options['servers'][] = explode(':', $server, 2);
                }
            }
            $settings = [
                'adapter' => [
                    'name' => $vufindConfig->IndexCache->adapter,
                    'options' => $options,
                ]
            ];
            $cache = \Laminas\Cache\StorageFactory::factory($settings);
            if (!is_callable([$connector, 'setCache'])) {
                throw new \Exception(
                    'SolrCachingConnectorFactoryTrait requires a Connector class'
                    . ' that supports the setCache method.'
                );
            }
            $connector->setCache($cache);
        }

        return $connector;
    }
}
