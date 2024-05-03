<?php

/**
 * HTTP request helper methods for integration tests.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Feature;

use VuFindHttp\HttpService;

/**
 * HTTP request helper methods for integration tests.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
trait HttpRequestTrait
{
    use RemoteCoverageTrait;

    /**
     * HTTP service
     *
     * @var ?HttpService
     */
    protected $httpService = null;

    /**
     * Get extra HTTP headers to support testing.
     *
     * @return array
     */
    protected function getExtraVuFindHttpRequestHeaders(): array
    {
        return ($coverageDir = $this->getRemoteCoverageDirectory())
            ? [
                'X-VuFind-Remote-Coverage' => json_encode(
                    [
                        'action' => 'record',
                        'testName' => $this->getTestName(),
                        'outputDir' => $coverageDir,
                    ]
                ),
            ] : [];
    }

    /**
     * Get HTTP service.
     *
     * @return HttpService
     */
    protected function getHttpService(): HttpService
    {
        if (!$this->httpService) {
            $this->httpService = new HttpService();
        }
        return $this->httpService;
    }

    /**
     * Perform an HTTP GET operation with coverage awareness.
     *
     * @param string $url     Request URL
     * @param array  $params  Request parameters
     * @param float  $timeout Request timeout in seconds
     * @param array  $headers Request headers
     *
     * @return \Laminas\Http\Response
     */
    protected function httpGet(
        $url,
        array $params = [],
        $timeout = null,
        array $headers = []
    ): \Laminas\Http\Response {
        return $this->getHttpService()->get(
            $url,
            $params,
            $timeout,
            array_merge($headers, $this->getExtraVuFindHttpRequestHeaders())
        );
    }

    /**
     * Perform an HTTP POST operation with coverage awareness.
     *
     * @param string $url     Request URL
     * @param mixed  $body    Request body document
     * @param string $type    Request body content type
     * @param float  $timeout Request timeout in seconds
     * @param array  $headers Request http-headers
     *
     * @return \Laminas\Http\Response
     */
    protected function httpPost(
        $url,
        $body = null,
        $type = 'application/octet-stream',
        $timeout = null,
        array $headers = []
    ): \Laminas\Http\Response {
        return $this->getHttpService()->post(
            $url,
            $body,
            $type,
            $timeout,
            array_merge($headers, $this->getExtraVuFindHttpRequestHeaders())
        );
    }
}
