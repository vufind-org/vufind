<?php

/**
 * Unit tests for GuzzleService.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Tests
 * @author   Sambhav Pokharel <sambhavpokharel@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindTest\Http;

use VuFind\Http\GuzzleService;

/**
 * Unit tests for GuzzleService.
 *
 * @category VuFind
 * @package  Tests
 * @author   Sambhav Pokharel <sambhavpokharel@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class GuzzleServiceTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ReflectionTrait;

    /**
     * Test GET request with associative array parameters
     *
     * @return void
     */
    public function testGetWithAssociativeParams(): void
    {
        $service = new GuzzleService([]);

        $params = ['foo' => 'bar', 'baz' => 'qux'];
        $result = $this->callMethod($service, 'createQueryString', [$params]);

        $this->assertEquals('foo=bar&baz=qux', $result);
    }

    /**
     * Test GET request with pre-formatted parameter strings
     *
     * @return void
     */
    public function testGetWithPreformattedParams(): void
    {
        $service = new GuzzleService([]);

        $params = ['foo=bar', 'baz=qux'];
        $result = $this->callMethod($service, 'createQueryString', [$params]);

        $this->assertEquals('foo=bar&baz=qux', $result);
    }

    /**
     * Test GET request with special characters in associative array
     *
     * @return void
     */
    public function testGetWithSpecialCharacters(): void
    {
        $service = new GuzzleService([]);

        $params = ['query' => 'hello world', 'filter' => 'a&b'];
        $result = $this->callMethod($service, 'createQueryString', [$params]);

        $this->assertEquals('query=hello+world&filter=a%26b', $result);
    }

    /**
     * Test GET request with empty params
     *
     * @return void
     */
    public function testGetWithEmptyParams(): void
    {
        $service = new GuzzleService([]);
        $result = $this->callMethod($service, 'createQueryString', []);
        $this->assertEquals('', $result);
    }

    /**
     * Test client creation
     *
     * @return void
     */
    public function testCreateClient(): void
    {
        $service = new GuzzleService([]);
        $client = $service->createClient();

        $this->assertInstanceOf(\Psr\Http\Client\ClientInterface::class, $client);
    }

    /**
     * Test Guzzle client creation
     *
     * @return void
     */
    public function testCreateGuzzleClient(): void
    {
        $service = new GuzzleService([]);
        $client = $service->createGuzzleClient();

        $this->assertInstanceOf(\GuzzleHttp\ClientInterface::class, $client);
    }
}
