<?php

/**
 * Class ResponseFormatterTraitTest
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022.
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
 * @package  Controller_Plugins
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://knihovny.cz Main Page
 */

namespace VuFindTest\Controller\Feature;

use VuFind\Controller\Feature\ResponseFormatterTrait;

/**
 * Class ResponseFormatterTraitTest
 *
 * @category VuFind
 * @package  Controller_Plugins
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class ResponseFormatterTraitTest extends \PHPUnit\Framework\TestCase
{
    use ResponseFormatterTrait;

    /**
     * Test the getJsonResponse method
     *
     * @return void
     */
    public function testGetJsonResponse(): void
    {
        $response = $this->getJsonResponse(['foo' => 'bar']);
        $this->assertEquals('{"foo":"bar"}', $response->getContent());
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(
            'application/json',
            $response->getHeaders()->get('Content-Type')->getFieldValue()
        );

        $response = $this->getJsonResponse(['error' => 'failed'], 500);
        $this->assertEquals('{"error":"failed"}', $response->getContent());
        $this->assertEquals(500, $response->getStatusCode());
    }

    /**
     * Test the addCorsHeaders method
     *
     * @return void
     */
    public function testAddCorsHeaders(): void
    {
        $response = $this->getJsonResponse(['ok']);
        $this->addCorsHeaders(
            $response,
            ['GET'],
            ['X-Forwarded-For', 'Upgrade-Insecure-Requests'],
            'localhost',
            false,
            1234
        );
        $this->assertEquals(
            [
                'Content-Type' => 'application/json',
                'Access-Control-Allow-Methods' => 'GET',
                'Access-Control-Allow-Headers'
                    => 'X-Forwarded-For, Upgrade-Insecure-Requests',
                'Access-Control-Allow-Origin' => 'localhost',
                'Vary' => 'Origin',
                'Access-Control-Max-Age' => '1234',
            ],
            $response->getHeaders()->toArray()
        );

        $response = $this->getJsonResponse(['ok']);
        $this->addCorsHeaders(
            $response,
            ['GET', 'POST', 'OPTIONS'],
            [],
            '*',
            true
        );
        $this->assertEquals(
            [
                'Content-Type' => 'application/json',
                'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Credentials' => 'true',
                'Access-Control-Max-Age' => '86400',
            ],
            $response->getHeaders()->toArray()
        );
    }
}
