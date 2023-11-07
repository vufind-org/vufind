<?php

/**
 * Proxy URL view helper
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

use Laminas\Cache\Storage\Adapter\AbstractAdapter as CacheAdapter;

/**
 * Proxy URL view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ProxyUrl extends \Laminas\View\Helper\AbstractHelper implements
    \VuFindHttp\HttpServiceAwareInterface
{
    use \VuFind\Cache\CacheTrait;
    use \VuFindHttp\HttpServiceAwareTrait;

    /**
     * VuFind configuration
     *
     * @var \Laminas\Config\Config
     */
    protected $config;

    /**
     * Constructor
     *
     * @param CacheAdapter           $cache  Cache for web service repsonses
     * @param \Laminas\Config\Config $config VuFind configuration
     */
    public function __construct(CacheAdapter $cache = null, $config = null)
    {
        $this->config = $config;
        $this->setCacheStorage($cache);
        $this->cacheLifetime = intval($config->EZproxy->prefixLinksWebServiceCacheLifetime ?? 600);
    }

    /**
     * Apply proxy prefix to URL (if configured).
     *
     * @param string $url The raw URL to adjust
     *
     * @return string
     */
    public function __invoke($url)
    {
        $useWebService = $this->config->EZproxy->prefixLinksWebServiceUrl ?? false;
        if ($useWebService) {
            $usePrefix = $this->checkUrl($url);
        } else {
            $usePrefix = $this->config->EZproxy->prefixLinks ?? true;
        }

        return ($usePrefix && isset($this->config->EZproxy->host))
            ? $this->config->EZproxy->host . '/login?qurl=' . urlencode($url)
            : $url;
    }

    /**
     * Check whether the given URL requires the proxy prefix.  Cache the repsonse.
     *
     * @param string $url The raw URL to check
     *
     * @return bool Whether the URL should be prefixed
     */
    protected function checkUrl($url)
    {
        $domain = parse_url($url, PHP_URL_HOST);
        $cacheKey = parse_url($url, PHP_URL_SCHEME) . '://' . $domain;
        $usePrefix = $this->getCachedData("proxyUrl-domainToUsePrefix-$cacheKey");
        if (null === $usePrefix) {
            $usePrefix = $this->queryWebService($domain);
            $this->putCachedData("proxyUrl-domainToUsePrefix-$cacheKey", $usePrefix);
        }
        return $usePrefix;
    }

    /**
     * Query the web service on whether to prefix URLs to a given domain.
     *
     * @param $domain The domain
     *
     * @return bool Whether the URL should be prefixed
     */
    protected function queryWebService($domain)
    {
        $prefixLinksWebServiceUrl = $this->config->EZproxy->prefixLinksWebServiceUrl;
        $queryUrl = $prefixLinksWebServiceUrl . '?url=' . $domain;
        $client  = $this->httpService->createClient($queryUrl);
        $response = $client->send();
        $responseData = $response->getContent();
        return ('1' === $responseData);
    }
}
