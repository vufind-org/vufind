<?php

/**
 * Rate Limiter test class.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2024.
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
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

declare(strict_types=1);

namespace VuFindTest\Mink;

use Laminas\Http\Request;
use VuFindHttp\HttpService;
use VuFindTest\Feature\CacheManagementTrait;

/**
 * Rate Limiter test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class RateLimiterTest extends \VuFindTest\Integration\MinkTestCase
{
    use CacheManagementTrait;

    /**
     * Standard setup method.
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->changeConfigs($this->getCacheClearPermissionConfig());
        $this->clearObjectCache();
    }

    /**
     * Data provider for testRateLimiter
     *
     * @return array
     */
    public static function rateLimiterDataProvider(): array
    {
        $searchPath = '/Search/Results';
        $searchQuery = ['lookfor' => 'foobar'];
        return [
            'search by bot' => [
                true,
                true,
                2,
                $searchPath,
                $searchQuery,
            ],
            'search by user' => [
                false,
                false,
                5,
                $searchPath,
                $searchQuery,
            ],
            'front page (unlimited)' => [
                false,
                false,
                null,
                '',
                [],
            ],
        ];
    }

    /**
     * Test rate limiter.
     *
     * @param bool   $crawler Request as crawler?
     * @param bool   $headers Expect headers?
     * @param ?int   $limit   Expected request limit or null for no limit
     * @param string $path    Request URL path
     * @param array  $query   Request URL query params
     *
     * @return void
     *
     * @dataProvider rateLimiterDataProvider
     */
    public function testRateLimiter(bool $crawler, bool $headers, ?int $limit, string $path, array $query): void
    {
        $this->changeYamlConfigs(
            ['RateLimiter' => $this->getRateLimiterConfigOverrides($headers)],
            ['RateLimiter']
        );

        $useragent = 'Mozilla/5.0 (compatible; ' . ($crawler ? 'ResponseCodeTest/1.1' : 'VuFind') . ';)';
        $http = new HttpService(defaults: compact('useragent'));
        for ($current = 1; $current <= ($limit ?? 10); $current++) {
            $this->getAndCheckResponse($http, $path, $query, 200, $headers, $limit, $current);
        }
        if (null !== $limit) {
            $this->getAndCheckResponse($http, $path, $query, 429, $headers);
            // Wait for new tokens in bucket:
            sleep(4);
            $this->getAndCheckResponse($http, $path, $query, 200, $headers);
        }
    }

    /**
     * Get RateLimiter.yaml overrides
     *
     * @param bool $addHeaders Add X-RateLimit-* headers?
     *
     * @return array
     */
    protected function getRateLimiterConfigOverrides(bool $addHeaders): array
    {
        return [
            'General' => [
                'enabled' => true,
            ],
            'Storage' => [
                'adapter' => 'vufind',
            ],
            'Policies' => [
                'search' => [
                    'crawler' => false,
                    'addHeaders' => $addHeaders,
                    'filters' => [
                        [
                            'controller' => 'Search',
                            'action' => 'Results',
                        ],
                    ],
                    'rateLimiterSettings' => [
                        'policy' => 'token_bucket',
                        'limit' => 5,
                        'rate' => [
                            'interval' => '3 seconds',
                            'amount' => 5,
                        ],
                    ],
                ],
                'searchBot' => [
                    'crawler' => true,
                    'addHeaders' => $addHeaders,
                    'filters' => [
                        [
                            'controller' => 'Search',
                            'action' => 'Results',
                        ],
                    ],
                    'rateLimiterSettings' => [
                        'policy' => 'token_bucket',
                        'limit' => 2,
                        'rate' => [
                            'interval' => '3 seconds',
                            'amount' => 2,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Make a request and check the result
     *
     * @param HttpService $http       HTTP Service
     * @param string      $path       Request URL path
     * @param array       $query      Request URL query params
     * @param int         $statusCode Expected status code
     * @param bool        $hasHeaders Whether the response should include rate limit headers
     * @param ?int        $limit      Expected successful request limit or null for no limit
     * @param ?int        $current    Current request
     *
     * @return void
     */
    protected function getAndCheckResponse(
        HttpService $http,
        string $path,
        array $query,
        int $statusCode,
        bool $hasHeaders,
        ?int $limit = null,
        ?int $current = null,
    ): void {
        $response = $http->get($this->getVuFindUrl($path), $query);
        $this->assertEquals($statusCode, $response->getStatusCode());
        $headers = $response->getHeaders();
        $this->assertEquals($hasHeaders, $headers->has('X-RateLimit-Remaining'));
        $this->assertEquals($hasHeaders, $headers->has('X-RateLimit-Retry-After'));
        $this->assertEquals($hasHeaders, $headers->has('X-RateLimit-Limit'));
        if ($hasHeaders && null !== $limit) {
            $headerRemaining = $headers->get('X-RateLimit-Remaining')->getFieldValue();
            $headerLimit = $headers->get('X-RateLimit-Limit')->getFieldValue();
            $this->assertEquals($limit, $headerLimit);
            if (null !== $current) {
                $this->assertEquals($current, $limit - $headerRemaining);
            }
        }
    }
}
