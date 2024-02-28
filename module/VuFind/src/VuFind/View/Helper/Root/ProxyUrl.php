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

use Exception;
use Laminas\Cache\Storage\StorageInterface as CacheAdapter;

use function intval;

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
    \Laminas\Log\LoggerAwareInterface,
    \VuFindHttp\HttpServiceAwareInterface
{
    use \VuFind\Cache\CacheTrait;
    use \VuFind\Log\LoggerAwareTrait;
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
     * @param \Laminas\Config\Config $config VuFind configuration
     * @param CacheAdapter           $cache  Cache for web service responses
     */
    public function __construct($config = null, CacheAdapter $cache = null)
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
            $usePrefix = $this->checkUrl($url) ?? $this->checkConfig();
        } else {
            $usePrefix = $this->checkConfig();
        }

        return ($usePrefix && isset($this->config->EZproxy->host))
            ? $this->config->EZproxy->host . '/login?qurl=' . urlencode($url)
            : $url;
    }

    /**
     * Return the configured prefixLinks setting.
     *
     * @return bool The configured setting, or the default
     */
    protected function checkConfig()
    {
        return $this->config->EZproxy->prefixLinks ?? true;
    }

    /**
     * Check whether the given URL requires the proxy prefix.  Cache the response.
     *
     * @param string $url The raw URL to check
     *
     * @return mixed Whether the URL should be prefixed, or null if it can't be determined
     */
    protected function checkUrl($url)
    {
        $domain = parse_url($url, PHP_URL_SCHEME)
         . '://'
         . parse_url($url, PHP_URL_HOST);
        $cacheKey = "proxyUrl-domainToUsePrefix-$domain";
        $usePrefix = $this->getCachedData($cacheKey);
        if (null === $usePrefix) {
            $usePrefix = $this->queryWebService($domain);
            $this->putCachedData($cacheKey, $usePrefix);
        }
        return $usePrefix;
    }

    /**
     * Query the web service on whether to prefix URLs to a given domain.
     *
     * @param $domain The domain
     *
     * @return mixed Whether the URL should be prefixed, or null if it can't be determined
     */
    protected function queryWebService($domain)
    {
        $prefixLinksWebServiceUrl = $this->config->EZproxy->prefixLinksWebServiceUrl;
        try {
            $response = $this->httpService->get($prefixLinksWebServiceUrl, ['url' => $domain]);
            $responseData = trim($response->getContent());
        } catch (Exception $ex) {
            $this->logError('Exception during EZproxy web service request: ' . $ex->getMessage());
            return null;
        }
        return '1' === $responseData;
    }
}
