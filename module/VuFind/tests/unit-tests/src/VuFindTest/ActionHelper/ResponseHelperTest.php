<?php

/**
 * ResponseHelper test class.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022-2026.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\ActionHelper;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use VuFind\ActionHelper\ResponseHelper;

/**
 * ResponseHelper test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class ResponseHelperTest extends TestCase
{
    /**
     * Data provider for testGetJsonResponse.
     *
     * @return \Generator
     */
    public static function getJsonResponseProvider(): \Iterator
    {
        yield 'success without caching' => [
            null,
            ['foo' => 'bar'],
            false,
            0,
            [
                'Content-Type' => ['application/json'],
                'Cache-Control' => ['no-cache, must-revalidate'],
                'Expires' => ['Mon, 26 Jul 1997 05:00:00 GMT'],
            ],
            '{"foo":"bar"}',
        ];

        yield 'success with caching' => [
            null,
            ['foo' => 'bar'],
            true,
            0,
            [
                'Content-Type' => ['application/json'],
            ],
            '{"foo":"bar"}',
        ];

        yield 'pretty print' => [
            null,
            ['foo' => 'bar'],
            true,
            JSON_PRETTY_PRINT,
            [
                'Content-Type' => ['application/json'],
            ],
            "{\n    \"foo\": \"bar\"\n}",
        ];

        yield '500 error' => [
            500,
            ['error' => 'failed'],
            false,
            0,
            [
                'Content-Type' => ['application/json'],
                'Cache-Control' => ['no-cache, must-revalidate'],
                'Expires' => ['Mon, 26 Jul 1997 05:00:00 GMT'],
            ],
            '{"error":"failed"}',
        ];
    }

    /**
     * Test the getJsonResponse method.
     *
     * @param ?int   $status          Response HTTP status
     * @param array  $contents        Response contents
     * @param bool   $allowCaching    Allow caching?
     * @param int    $jsonFlags       JSON encoding flags
     * @param array  $expectedHeaders Expected headers
     * @param string $expectedBody    Expected response body
     *
     * @return void
     */
    #[DataProvider('getJsonResponseProvider')]
    public function testGetJsonResponse(
        ?int $status,
        array $contents,
        bool $allowCaching,
        int $jsonFlags,
        array $expectedHeaders,
        string $expectedBody
    ): void {
        $helper = new ResponseHelper();
        $response = $helper->getJsonResponse(new Response(), $contents, $status, $allowCaching, $jsonFlags);
        $body = $response->getBody();
        $body->rewind();
        $this->assertSame($expectedBody, $body->getContents());
        $this->assertSame($status ?? 200, $response->getStatusCode());
        $this->assertEquals($expectedHeaders, $response->getHeaders());
    }

    /**
     * Test the addCorsHeaders method.
     *
     * @return void
     */
    public function testAddCorsHeaders(): void
    {
        $helper = new ResponseHelper();
        $response = $helper->addCorsHeaders(
            $helper->getJsonResponse(new Response(), ['ok']),
            ['GET'],
            ['X-Forwarded-For', 'Upgrade-Insecure-Requests'],
            'localhost',
            false,
            1234
        );
        $this->assertEquals(
            [
                'Content-Type' => ['application/json'],
                'Vary' => ['Origin'],
                'Access-Control-Allow-Methods' => ['GET'],
                'Access-Control-Allow-Headers' => ['X-Forwarded-For, Upgrade-Insecure-Requests'],
                'Access-Control-Allow-Origin' => ['localhost'],
                'Access-Control-Max-Age' => ['1234'],
                'Cache-Control' => ['no-cache, must-revalidate'],
                'Expires' => ['Mon, 26 Jul 1997 05:00:00 GMT'],
            ],
            $response->getHeaders()
        );

        $response = $helper->addCorsHeaders(
            $helper->getJsonResponse(new Response(), ['ok']),
            ['GET', 'POST', 'OPTIONS'],
            [],
            '*',
            true
        );
        $this->assertEquals(
            [
                'Content-Type' => ['application/json'],
                'Access-Control-Allow-Methods' => ['GET, POST, OPTIONS'],
                'Access-Control-Allow-Origin' => ['*'],
                'Access-Control-Allow-Credentials' => ['true'],
                'Access-Control-Max-Age' => ['86400'],
                'Cache-Control' => ['no-cache, must-revalidate'],
                'Expires' => ['Mon, 26 Jul 1997 05:00:00 GMT'],
            ],
            $response->getHeaders()
        );
    }
}
