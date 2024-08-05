<?php

/**
 * Service class for ObalkyKnih
 *
 * PHP version 8
 *
 * Copyright (C) Moravian Library 2019.
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
 * @package  Content
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Content;

use function count;

/**
 * Service class for ObalkyKnih
 *
 * @category VuFind
 * @package  Content
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ObalkyKnihService implements
    \VuFindHttp\HttpServiceAwareInterface,
    \Laminas\Log\LoggerAwareInterface
{
    use \VuFindHttp\HttpServiceAwareTrait;
    use \VuFind\Cache\CacheTrait;
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * Available base URLs
     *
     * @var array
     */
    protected $baseUrls = [];

    /**
     * Http referrer
     *
     * @var string
     */
    protected $referrer;

    /**
     * Sigla - library identifier
     *
     * @var string
     */
    protected $sigla;

    /**
     * Array with endpoints, possible endpoints(array keys) are: books, cover, toc,
     * authority, citation, recommend, alive
     *
     * @var array
     */
    protected $endpoints;

    /**
     * Whether to check servers availability before API calls
     *
     * @var bool
     */
    protected $checkServersAvailability = false;

    /**
     * Constructor
     *
     * @param \Laminas\Config\Config $config Configuration for service
     */
    public function __construct(\Laminas\Config\Config $config)
    {
        if (
            !isset($config->base_url) || count($config->base_url) < 1
            || !isset($config->books_endpoint)
        ) {
            throw new \Exception(
                'Configuration for ObalkyKnih.cz service is not valid'
            );
        }
        $this->baseUrls = $config->base_url;
        $this->cacheLifetime = 1800;
        $this->referrer = $config->referrer ?? null;
        $this->sigla = $config->sigla ?? null;
        foreach ($config->toArray() as $configItem => $configValue) {
            $parts = explode('_', $configItem);
            if ($parts[1] ?? '' === 'endpoint') {
                $this->endpoints[$parts[0]] = $configValue;
            }
        }
        $this->checkServersAvailability
            = $config->checkServersAvailability ?? false;
    }

    /**
     * Get an HTTP client
     *
     * @param string $url URL for client to use
     *
     * @return \Laminas\Http\Client
     */
    protected function getHttpClient(string $url = null)
    {
        if (null === $this->httpService) {
            throw new \Exception('HTTP service missing.');
        }
        $client = $this->httpService->createClient($url);
        if (isset($this->referrer)) {
            $client->getRequest()->getHeaders()
                ->addHeaderLine('Referer', $this->referrer);
        }
        return $client;
    }

    /**
     * Creates cache key based on ids
     *
     * @param array $ids Record identifiers
     *
     * @return string
     */
    protected function createCacheKey(array $ids)
    {
        $key = $ids['recordid'] ?? '';
        $key = !empty($key) ? $key
            : (isset($ids['isbn']) ? $ids['isbn']->get13() : null);
        $key = !empty($key) ? $key : sha1(json_encode($ids));
        return $key;
    }

    /**
     * Get data from cache, or from service
     *
     * @param array $ids Record identifiers
     *
     * @return \stdClass|null
     */
    public function getData(array $ids): ?\stdClass
    {
        $cacheKey = $this->createCacheKey($ids);
        $cachedData = $this->getCachedData($cacheKey);
        if ($cachedData === null) {
            $cachedData = $this->getFromService($ids);
            $this->putCachedData($cacheKey, $cachedData);
        }
        return $cachedData;
    }

    /**
     * Get data from service
     *
     * @param array $ids Record identifiers
     *
     * @return \stdClass|null
     * @throws \Exception
     *
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     */
    protected function getFromService(array $ids): ?\stdClass
    {
        $param = 'multi';
        $query = [];
        $isbn = null;
        if (!empty($ids['isbns'])) {
            $isbn = array_map(
                function ($isbn) {
                    return $isbn->get13();
                },
                $ids['isbns']
            );
        } elseif (!empty($ids['isbn'])) {
            $isbn = $ids['isbn']->get13();
        }
        $isbn ??= $ids['upc'] ?? $ids['issn'] ?? null;
        $oclc = $ids['oclc'] ?? null;
        $isbn = $isbn ?? (isset($ids['ismn']) ? $ids['ismn']->get13() : null);
        $ismn = isset($ids['ismn']) ? $ids['ismn']->get10() : null;
        $nbn = $ids['nbn'] ?? $this->createLocalIdentifier($ids['recordid'] ?? '');
        $uuid = null;
        if (isset($ids['uuid'])) {
            $uuid = str_starts_with($ids['uuid'], 'uuid:')
                ? $ids['uuid']
                : ('uuid:' . $ids['uuid']);
        }
        foreach (['isbn', 'oclc', 'ismn', 'nbn', 'uuid'] as $identifier) {
            if (isset($$identifier)) {
                $query[$identifier] = $$identifier;
            }
        }

        $url = $this->getBaseUrl();
        if ($url === '') {
            $this->logWarning('All ObalkyKnih servers are down.');
            return null;
        }
        $url .= $this->endpoints['books'] . '?';
        $url .= http_build_query([$param => json_encode([$query])]);
        $client = $this->getHttpClient($url);
        try {
            $response = $client->send();
        } catch (\Exception $e) {
            $this->logError('Unexpected ' . $e::class . ': ' . $e->getMessage());
            return null;
        }
        if ($response->isSuccess()) {
            $json = json_decode($response->getBody());
            return empty($json) ? null : $json[0];
        }
        return null;
    }

    /**
     * Create identifier of local record
     *
     * @param string $recordid Record identifier
     *
     * @return string|null
     */
    protected function createLocalIdentifier(string $recordid): ?string
    {
        if (str_contains($recordid, '.')) {
            [, $recordid] = explode('.', $recordid, 2);
        }
        return (empty($this->sigla) || empty($recordid)) ? null :
            $this->sigla . '-' . str_replace('-', '', $recordid);
    }

    /**
     * Get currently available base URL
     *
     * @return string
     */
    protected function getBaseUrl(): string
    {
        return $this->checkServersAvailability
            ? $this->getAliveUrl() : $this->baseUrls[0];
    }

    /**
     * Check base URLs and return the first available
     *
     * @return string
     */
    protected function getAliveUrl(): string
    {
        $aliveUrl = $this->getCachedData('baseUrl');
        if ($aliveUrl !== null) {
            return $aliveUrl;
        }
        foreach ($this->baseUrls as $baseUrl) {
            $url = $baseUrl . $this->endpoints['alive'];
            $client = $this->getHttpClient($url);
            $client->setOptions(['timeout' => 2]);
            $response = $client->send();
            if ($response->isSuccess()) {
                $this->putCachedData('baseUrl', $baseUrl, 60);
                return $baseUrl;
            }
        }
        return '';
    }
}
