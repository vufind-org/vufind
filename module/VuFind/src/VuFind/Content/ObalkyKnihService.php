<?php

/**
 * Service class for ObalkyKnih
 *
 * PHP version 7
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

/**
 * Service class for ObalkyKnih
 *
 * @category VuFind
 * @package  Content
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ObalkyKnihService implements \VuFindHttp\HttpServiceAwareInterface,
    \Laminas\Log\LoggerAwareInterface
{
    use \VuFindHttp\HttpServiceAwareTrait;
    use \VuFind\ILS\Driver\CacheTrait;
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * API URL
     *
     * @var string
     */
    protected $apiUrl;

    /**
     * Http referrer
     *
     * @var string
     */
    protected $referrer;

    /**
     * Constructor
     *
     * @param \Laminas\Config\Config $config Configuration for service
     */
    public function __construct(\Laminas\Config\Config $config)
    {
        if (!isset($config->base_url) || count($config->base_url) < 1
            || !isset($config->books_endpoint)
        ) {
            throw new \Exception(
                "Configuration for ObalkyKnih.cz service is not valid"
            );
        }
        $this->apiUrl = $config->base_url[0] . $config->books_endpoint;
        $this->cacheLifetime = 1800;
        $this->referrer = $config->referrer ?? null;
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
        array_walk(
            $ids, function (&$value, $key) {
                if (gettype($value) === 'object') {
                    $value = $value->get13();
                }
                $value = "$key::$value";
            }
        );
        return implode("%%", $ids);
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
        $param = "multi";
        $query = [];
        $isbn = isset($ids['isbn']) ? $ids['isbn']->get13() : null;
        $isbn = $isbn ?? $ids['upc'] ?? $ids['issn'] ?? null;
        $oclc = $ids['oclc'] ?? null;
        $isbn = $isbn ?? (isset($ids['ismn']) ? $ids['ismn']->get13() : null);
        $ismn = isset($ids['ismn']) ? $ids['ismn']->get10() : null;
        $nbn = $ids['nbn'] ?? null;
        foreach (['isbn', 'oclc', 'ismn', 'nbn' ] as $identifier) {
            if (isset($$identifier)) {
                $query[$identifier] = $$identifier;
            }
        }
        $url = $this->apiUrl . "?";
        $url .= http_build_query([$param => json_encode([$query])]);
        $client = $this->getHttpClient($url);
        try {
            $response = $client->send();
        } catch (\Exception $e) {
            $this->logError('Unexpected ' . get_class($e) . ': ' . $e->getMessage());
            return null;
        }
        return $response->isSuccess() ? json_decode($response->getBody())[0] : null;
    }
}
