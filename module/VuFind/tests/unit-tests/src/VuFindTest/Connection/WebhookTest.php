<?php

/**
 * Unit tests for Webhook connector.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2026.
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
 * @package  Connection
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindTest\Connection;

use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use VuFind\Connection\Webhook;
use VuFind\Http\GuzzleService;

/**
 * Unit tests for Webhook connector.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class WebhookTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test a successful webhook call.
     *
     * @return void
     */
    public function testSuccessfulCall(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $guzzle = $this->createMock(GuzzleService::class);
        $guzzle->method('post')->with('http://foo', null, '', 5)->willReturn($response);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('log')
            ->with('debug', 'VuFind\Connection\Webhook: Webhook posted successfully');
        $connector = new Webhook();
        $connector->setGuzzleService($guzzle);
        $connector->setLogger($logger);
        $connector->post('http://foo', 5);
    }

    /**
     * Test an unsuccessful webhook call.
     *
     * @return void
     */
    public function testUnsuccessfulCall(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(401);
        $response->expects($this->once())->method('getBody');
        $guzzle = $this->createMock(GuzzleService::class);
        $guzzle->method('post')->with('http://foo', null, '', 5)->willReturn($response);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('log')
            ->with('error', 'VuFind\Connection\Webhook: Failed to post to webhook. Code: 401, body: ');
        $connector = new Webhook();
        $connector->setGuzzleService($guzzle);
        $connector->setLogger($logger);
        $connector->post('http://foo', 5);
    }

    /**
     * Test an exception during a webhook call.
     *
     * @return void
     */
    public function testExceptionDuringCall(): void
    {
        $guzzle = $this->createMock(GuzzleService::class);
        $guzzle->method('post')->with('http://foo', null, '', 5)->willThrowException(new Exception('fail!'));
        $logger = $this->createMock(LoggerInterface::class);
        $expected = 'VuFind\Connection\Webhook: Failed to post webhook. Unexpected Exception: Exception: fail!';
        $logger->expects($this->once())->method('log')->with('error', $this->stringStartsWith($expected));
        $connector = new Webhook();
        $connector->setGuzzleService($guzzle);
        $connector->setLogger($logger);
        $connector->post('http://foo', 5);
    }
}
