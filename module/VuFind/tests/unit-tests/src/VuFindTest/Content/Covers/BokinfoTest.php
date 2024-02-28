<?php

/**
 * Unit tests for Bokinfo cover loader.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2022.
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
 * @link     https://vufind.org
 */

namespace VuFindTest\Content\Covers;

use Laminas\Http\Client;
use Laminas\Http\Headers;
use Laminas\Http\Request;
use Laminas\Http\Response;
use VuFind\Content\Covers\Bokinfo;
use VuFindCode\ISBN;
use VuFindHttp\HttpService;

/**
 * Unit tests for Bokinfo cover loader.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class BokinfoTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;
    use \VuFindTest\Feature\WithConsecutiveTrait;

    /**
     * Get mock request/headers to expect key setting.
     *
     * @return Request
     */
    protected function getMockRequest(): Request
    {
        $request = $this->getMockBuilder(Request::class)
            ->disableOriginalConstructor()
            ->getMock();
        $headers = $this->getMockBuilder(Headers::class)
            ->disableOriginalConstructor()
            ->getMock();
        $request->expects($this->once())->method('getHeaders')
            ->will($this->returnValue($headers));
        $headers->expects($this->once())->method('addHeaderLine')
            ->with(
                $this->equalTo('Ocp-Apim-Subscription-Key'),
                $this->equalTo('mykey')
            );
        return $request;
    }

    /**
     * Get mock response object
     *
     * @return Response
     */
    protected function getMockResponse(): Response
    {
        return $this->getMockBuilder(Response::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * Get a mock HTTP client.
     *
     * @return Client
     */
    protected function getMockClient(): Client
    {
        $client = $this->getMockBuilder(Client::class)
            ->disableOriginalConstructor()
            ->getMock();
        $client->expects($this->once())->method('setOptions')
            ->with($this->equalTo(['useragent' => 'VuFind', 'keepalive' => true]));
        return $client;
    }

    /**
     * Get a mock HTTP service to support testValidCoverLoading().
     *
     * @return HttpService
     */
    protected function getMockService(): HttpService
    {
        $service = $this->getMockBuilder(HttpService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $url1 = 'https://api.bokinfo.se/book/get/9789129697285';
        $url2 = 'https://fake-url';
        $client1 = $this->getMockClient();
        $client1->expects($this->once())->method('getRequest')
            ->will($this->returnValue($this->getMockRequest()));
        $response1 = $this->getMockResponse();
        $response1->expects($this->once())->method('getBody')
            ->will(
                $this->returnValue($this->getFixture('content/covers/bokinfo.xml'))
            );
        $client1->expects($this->once())->method('send')
            ->will($this->returnValue($response1));
        $client2 = $this->getMockClient();
        $response2 = $this->getMockResponse();
        $response2->expects($this->once())->method('getHeaders')
            ->will($this->returnValue(['foo: bar']));
        $client2->expects($this->once())->method('send')
            ->will($this->returnValue($response2));
        $this->expectConsecutiveCalls(
            $service,
            'createClient',
            [[$url1], [$url2]],
            [$client1, $client2]
        );
        return $service;
    }

    /**
     * Test cover loading
     *
     * @return void
     */
    public function testValidCoverLoading(): void
    {
        $bokinfo = new Bokinfo();
        $bokinfo->setHttpService($this->getMockService());
        $this->assertEquals(
            'https://fake-url',
            $bokinfo->getUrl(
                'mykey',
                'small',
                ['isbn' => new ISBN('9789129697285')]
            )
        );
    }

    /**
     * Test missing ISBN
     *
     * @return void
     */
    public function testMissingIsbn(): void
    {
        $bokinfo = new Bokinfo();
        $this->assertFalse($bokinfo->getUrl('mykey', 'small', []));
    }

    /**
     * Test missing API key
     *
     * @return void
     */
    public function testMissingKey(): void
    {
        $bokinfo = new Bokinfo();
        $this->assertFalse(
            $bokinfo->getUrl(null, 'small', ['isbn' => new ISBN('9789129697285')])
        );
    }
}
