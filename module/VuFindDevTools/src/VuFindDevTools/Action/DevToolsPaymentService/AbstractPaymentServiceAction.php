<?php

/**
 * Abstract base class for payment service actions.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025-2026.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Action
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFindDevTools\Action\DevToolsPaymentService;

use GuzzleHttp\Psr7\Uri;
use Laminas\Cache\Storage\StorageInterface;
use VuFind\Action\AbstractTemplateRenderingAction;
use VuFind\Cache\CacheTrait;
use VuFind\Cache\Manager as CacheManager;
use VuFind\ServiceManager\Factory\Autowire;
use VuFindHttp\HttpService;

/**
 * Abstract base class for payment service actions.
 *
 * @category VuFind
 * @package  Action
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
abstract class AbstractPaymentServiceAction extends AbstractTemplateRenderingAction
{
    use CacheTrait;

    /**
     * Constructor.
     *
     * @param StorageInterface $cache       Cache
     * @param HttpService      $httpService HTTP service
     */
    public function __construct(
        #[Autowire(container: CacheManager::class, service: 'object')]
        StorageInterface $cache,
        protected HttpService $httpService,
    ) {
        parent::__construct();
        $this->setCacheStorage($cache);
        $this->cacheLifetime = 300;
    }

    /**
     * Get a signature for the request params.
     *
     * Not secure, just for testing.
     *
     * @param array $params Request parameters
     *
     * @return string
     */
    protected function calculateSignature(array $params): string
    {
        unset($params['signature']);
        return md5('secret' . json_encode($params));
    }

    /**
     * Add signature to a URL.
     *
     * @param string $url URL
     *
     * @return string
     */
    protected function addSignature(string $url): string
    {
        $uri = new Uri($url);
        parse_str($uri->getQuery(), $params);
        $params['signature'] = $this->calculateSignature($params);
        $uri = $uri->withQuery(http_build_query($params));
        return (string)$uri;
    }

    /**
     * Method to ensure uniform cache keys for cached VuFind objects.
     *
     * @param ?string           $suffix Optional suffix that will get appended to the
     * object class name calling getCacheKey()
     * @param ?StorageInterface $cache  Optional non-default cache
     *
     * @return string
     */
    protected function getCacheKey(?string $suffix = null, ?StorageInterface $cache = null): string
    {
        // Make sure all actions generate cache keys with same prefix:
        return 'VuFindDevToolsPaymentService_' . ($suffix ?? '');
    }
}
