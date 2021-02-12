<?php
/**
 * SOLR connector.
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
 * @link     https://vufind.org
 */
namespace FinnaSearch\Backend\Solr;

use Laminas\Cache\Storage\StorageInterface;
use Laminas\Http\Client as HttpClient;

use VuFindSearch\Backend\Exception\RemoteErrorException;
use VuFindSearch\Backend\Exception\RequestErrorException;

/**
 * SOLR connector.
 *
 * @category VuFind
 * @package  Search
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class Connector extends \VuFindSearch\Backend\Solr\Connector
{
    /**
     * Request cache
     *
     * @var StorageInterface
     */
    protected $cache = null;

    /**
     * Set the cache storage
     *
     * @param StorageInterface $cache Cache
     *
     * @return void
     */
    public function setCache(StorageInterface $cache)
    {
        $this->cache = $cache;
    }

    /**
     * Send request the SOLR and return the response.
     *
     * @param HttpClient $client Prepared HTTP client
     *
     * @return string Response body
     *
     * @throws RemoteErrorException  SOLR signaled a server error (HTTP 5xx)
     * @throws RequestErrorException SOLR signaled a client error (HTTP 4xx)
     */
    protected function send(HttpClient $client)
    {
        $cacheKey = md5($client->getRequest()->toString());
        if ($this->cache) {
            try {
                if ($result = $this->cache->getItem($cacheKey)) {
                    $this->debug('Returning cached results');
                    return $result;
                }
            } catch (\Exception $e) {
                $this->logWarning('Cache getItem failed: ' . $e->getMessage());
            }
        }

        $result = parent::send($client);

        if ($this->cache && $this->isCacheable($client->getRequest())) {
            try {
                $this->cache->setItem($cacheKey, $result);
            } catch (\Exception $e) {
                if ($e->getMessage() === 'ITEM TOO BIG') {
                    $this->debug('Cache setItem failed: ' . $e->getMessage());
                } else {
                    $this->logWarning('Cache setItem failed: ' . $e->getMessage());
                }
            }
        }

        return $result;
    }

    /**
     * Check if a request can be cached
     *
     * @param \Laminas\Http\Request $request Request
     *
     * @return bool
     */
    protected function isCacheable(\Laminas\Http\Request $request)
    {
        return $request->getMethod() === \Laminas\Http\Request::METHOD_GET
            || substr($request->getUri()->getPath(), -7) === '/select';
    }
}
