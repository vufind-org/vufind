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
     * Constructor
     */
    public function __construct($config)
    {
        $this->apiUrl = $config->base_url1 . $config->books_endpoint;
        $this->cacheLifetime = 1800;
    }

    /**
     * Get an HTTP client
     *
     * @param string $url URL for client to use
     *
     * @return \Laminas\Http\Client
     */
    protected function getHttpClient($url = null)
    {
        if (null === $this->httpService) {
            throw new \Exception('HTTP service missing.');
        }
        return $this->httpService->createClient($url);
    }

    protected function createCacheKey($ids) {
        array_walk($ids, function(&$value, $key) {
            if (gettype($value) === 'object') {
                $value = $value->get13();
            }
            $value = "$key::$value";
        });
        return implode("%%", $ids);
    }

    public function getData($ids): ?\stdClass
    {
        $cacheKey = $this->createCacheKey($ids);
        $cachedData = $this->getCachedData($cacheKey);
        if ($cachedData === null) {
            $cachedData = $this->getFromService($ids);
            $this->putCachedData($cacheKey, $cachedData);
        }
        return $cachedData;
    }

    protected function getFromService($ids): ?\stdClass {
        $param = "multi";
        $query = [];
        $isbn = $ids['isbn'] ? $ids['isbn']->get13() : null;
        $isbn = $isbn ?? $ids['upc'] ?? $ids['issn'] ?? null;
        $oclc = $ids['oclc'] ?? null;
        $isbn = $isbn ?? ($ids['ismn'] ? $ids['ismn']->get13() : null);
        $ismn = $ids['ismn'] ? $ids['ismn']->get10() : null;
        $nbn = $ids['nbn'] ?? null;

        foreach(['isbn', 'oclc', 'ismn', 'nbn' ] as $identifier) {
            if (isset($$identifier)) {
                $query[$identifier] = $$identifier;
            }
        }
        $url = $this->apiUrl . "?";
        $url .= http_build_query([$param => json_encode([$query])]);
        $response = $this->getHttpClient($url)->send();
        return $response->isSuccess() ? json_decode($response->getBody())[0]: null;
    }
}
