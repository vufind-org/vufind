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
     * Perform an HTTP get operation with coverage awareness.
     *
     * @param string $url URL to retrieve
     *
     * @return \Laminas\Http\Response
     */
    protected function httpGet(string $url): \Laminas\Http\Response
    {
        $http = new \VuFindHttp\HttpService();
        $headers = ($coverageDir = $this->getRemoteCoverageDirectory())
            ? [
                'X-VuFind-Remote-Coverage' => json_encode(
                    [
                        'action' => 'record',
                        'testName' => $this->getTestName(),
                        'outputDir' => $coverageDir,
                    ]
                ),
            ] : [];
        return $http->get($url, headers: $headers);
    }
}
