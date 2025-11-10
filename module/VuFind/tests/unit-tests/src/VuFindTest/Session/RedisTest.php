<?php

/**
 * Redis Session Handler Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2019.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Session;

use VuFind\Session\Redis;

/**
 * Redis Session Handler Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class RedisTest extends \VuFindTest\Unit\SessionHandlerTestCase
{
    /**
     * Test reading a session from the database.
     *
     * @return void
     */
    public function testRead()
    {
        $client = $this->createMock(\Credis_Client::class);
        $client->expects($this->once())->method('__call')
            ->willReturnCallback(
                function ($method, $args) {
                    if ($method === 'get') {
                        $this->assertEquals('vufind_sessions/foo', $args[0]);
                        return 'bar';
                    }
                    return null;
                }
            );
        $handler = $this->getHandler($client);
        $this->assertEquals('bar', $handler->read('foo'));
    }

    /**
     * Test writing a session with default configs.
     *
     * @return void
     */
    public function testWrite()
    {
        $client = $this->createMock(\Credis_Client::class);
        $client->expects($this->once())->method('__call')
            ->willReturnCallback(
                function ($method, $args) {
                    if ($method === 'setex') {
                        $this->assertEquals('vufind_sessions/foo', $args[0]);
                        $this->assertEquals(3600, $args[1]);
                        $this->assertEquals('stuff', $args[2]);
                        return true;
                    }
                    return null;
                }
            );
        $handler = $this->getHandler($client);
        $this->assertTrue($handler->write('foo', 'stuff'));
    }

    /**
     * Test destroying a session with default (Redis version 3) support.
     *
     * @return void
     */
    public function testDestroyDefault()
    {
        $client = $this->createMock(\Credis_Client::class);
        $client->expects($this->once())->method('__call')
            ->willReturnCallback(
                function ($method, $args) {
                    if ($method === 'del') {
                        $this->assertEquals('vufind_sessions/foo', $args[0]);
                        return 1;
                    }
                    return null;
                }
            );
        $handler = $this->getHandler($client);
        $this->setUpDestroyExpectations('foo');

        $this->assertTrue($handler->destroy('foo'));
    }

    /**
     * Test destroying a session with newer (Redis version 4+) support.
     *
     * @return void
     */
    public function testDestroyNewRedis()
    {
        $client = $this->createMock(\Credis_Client::class);
        $client->expects($this->once())->method('__call')
            ->willReturnCallback(
                function ($method, $args) {
                    if ($method === 'unlink') {
                        $this->assertEquals('vufind_sessions/foo', $args[0]);
                        return 1;
                    }
                    return null;
                }
            );
        $config = new \VuFind\Config\Config(
            ['redis_version' => 4]
        );
        $handler = $this->getHandler($client, $config);
        $this->setUpDestroyExpectations('foo');

        $this->assertTrue($handler->destroy('foo'));
    }

    /**
     * Get the session handler to test.
     *
     * @param \Credis_Client        $client Client object
     * @param \VuFind\Config\Config $config Optional configuration
     *
     * @return Database
     */
    protected function getHandler($client, $config = null)
    {
        $handler = new Redis($client, $config);
        $this->injectMockDatabaseDependencies($handler);
        return $handler;
    }
}
