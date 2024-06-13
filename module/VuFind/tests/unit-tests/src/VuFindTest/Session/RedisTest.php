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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
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
        $client = $this->getMockBuilder(\Credis_Client::class)
            ->addMethods(['get'])   // mocking __call
            ->getMock();
        $client->expects($this->once())->method('get')
            ->with($this->equalTo('vufind_sessions/foo'))
            ->will($this->returnValue('bar'));
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
        $client = $this->getMockBuilder(\Credis_Client::class)
            ->addMethods(['setex']) // mocking __call
            ->getMock();
        $client->expects($this->once())->method('setex')
            ->with(
                $this->equalTo('vufind_sessions/foo'),
                $this->equalTo(3600),
                $this->equalTo('stuff')
            )
            ->will($this->returnValue(true));
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
        $client = $this->getMockBuilder(\Credis_Client::class)
            ->addMethods(['del'])   // mocking __call
            ->getMock();
        $client->expects($this->once())->method('del')
            ->with($this->equalTo('vufind_sessions/foo'))
            ->will($this->returnValue(1));
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
        $client = $this->getMockBuilder(\Credis_Client::class)
            ->addMethods(['unlink']) // mocking __call
            ->getMock();
        $client->expects($this->once())->method('unlink')
            ->with($this->equalTo('vufind_sessions/foo'))
            ->will($this->returnValue(1));
        $config = new \Laminas\Config\Config(
            ['redis_version' => 4]
        );
        $handler = $this->getHandler($client, $config);
        $this->setUpDestroyExpectations('foo');

        $this->assertTrue($handler->destroy('foo'));
    }

    /**
     * Get the session handler to test.
     *
     * @param \Credis_Client         $client Client object
     * @param \Laminas\Config\Config $config Optional configuration
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
