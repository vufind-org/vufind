<?php

/**
 * Memcache Session Handler Test Class
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

use VuFind\Config\Config;
use VuFind\Session\Memcache;

/**
 * Memcache Session Handler Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class MemcacheTest extends \VuFindTest\Unit\SessionHandlerTestCase
{
    /**
     * Test reading a session from the database with Memcache.
     *
     * @return void
     */
    public function testRead(): void
    {
        if (!class_exists(\Memcached::class)) {
            $this->markTestSkipped();
        }
        $memcache = $this->getMockBuilder(\Memcached::class)
            ->onlyMethods(['addServer', 'get', 'setOption'])
            ->getMock();
        $memcache->expects($this->once())->method('setOption')
            ->with(
                $this->equalTo(\Memcached::OPT_CONNECT_TIMEOUT),
                $this->equalTo(1)
            );
        $memcache->expects($this->once())->method('addServer')
            ->willReturn(true);
        $memcache->expects($this->once())->method('get')
            ->with($this->equalTo('vufind_sessions/foo'))
            ->willReturn('bar');
        $config = [
            'memcache_client' => \Memcached::class,
        ];
        $handler = $this->getHandler($config, $memcache);
        $this->assertEquals('bar', $handler->read('foo'));
    }

    /**
     * Test writing a session with default configs (Memcached version).
     *
     * @return void
     */
    public function testWriteWithDefaults(): void
    {
        if (!class_exists(\Memcached::class)) {
            $this->markTestSkipped();
        }
        $memcache = $this->getMockBuilder(\Memcached::class)
            ->onlyMethods(['addServer', 'set', 'setOption'])
            ->getMock();
        $memcache->expects($this->once())->method('setOption')
            ->with(
                $this->equalTo(\Memcached::OPT_CONNECT_TIMEOUT),
                $this->equalTo(1)
            );
        $memcache->expects($this->once())->method('addServer')
            ->with(
                $this->equalTo('localhost'),
                $this->equalTo(11211)
            )->willReturn(true);
        $memcache->expects($this->once())->method('set')
            ->with(
                $this->equalTo('vufind_sessions/foo'),
                $this->equalTo('stuff'),
                $this->equalTo(3600)
            )->willReturn(true);
        $config = [
            'memcache_client' => \Memcached::class,
        ];
        $handler = $this->getHandler($config, $memcache);
        $this->assertTrue($handler->write('foo', 'stuff'));
    }

    /**
     * Test writing a session with non-default configs (Memcached version).
     *
     * @return void
     */
    public function testWriteWithNonDefaults(): void
    {
        if (!class_exists(\Memcached::class)) {
            $this->markTestSkipped();
        }
        $memcache = $this->getMockBuilder(\Memcached::class)
            ->onlyMethods(['addServer', 'set', 'setOption'])
            ->getMock();
        $memcache->expects($this->once())->method('setOption')
            ->with(
                $this->equalTo(\Memcached::OPT_CONNECT_TIMEOUT),
                $this->equalTo(2)
            );
        $memcache->expects($this->once())->method('addServer')
            ->with(
                $this->equalTo('myhost'),
                $this->equalTo(1234)
            )->willReturn(true);
        $memcache->expects($this->once())->method('set')
            ->with(
                $this->equalTo('vufind_sessions/foo'),
                $this->equalTo('stuff'),
                $this->equalTo(1000)
            )->willReturn(true);
        $config = [
            'lifetime' => 1000,
            'memcache_host' => 'myhost',
            'memcache_port' => 1234,
            'memcache_connection_timeout' => 2,
            'memcache_client' => \Memcached::class,
        ];
        $handler = $this->getHandler($config, $memcache);
        $this->assertTrue($handler->write('foo', 'stuff'));
    }

    /**
     * Test destroying a session (Memcached version).
     *
     * @return void
     */
    public function testDestroy(): void
    {
        if (!class_exists(\Memcached::class)) {
            $this->markTestSkipped();
        }
        $memcache = $this->getMockBuilder(\Memcached::class)
            ->onlyMethods(['addServer', 'delete', 'setOption'])
            ->getMock();
        $memcache->expects($this->once())->method('setOption')
            ->with(
                $this->equalTo(\Memcached::OPT_CONNECT_TIMEOUT),
                $this->equalTo(1)
            );
        $memcache->expects($this->once())->method('addServer')
            ->willReturn(true);
        $memcache->expects($this->once())->method('delete')
            ->with($this->equalTo('vufind_sessions/foo'))
            ->willReturn(true);
        $config = [
            'memcache_client' => \Memcached::class,
        ];
        $handler = $this->getHandler($config, $memcache);
        $this->setUpDestroyExpectations('foo');

        $this->assertTrue($handler->destroy('foo'));
    }

    /**
     * Get the session handler to test.
     *
     * @param array       $config Optional configuration
     * @param ?\Memcached $client Optional client object
     *
     * @return Memcache
     */
    protected function getHandler(array $config = [], ?\Memcached $client = null): Memcache
    {
        $handler = new Memcache(new Config($config), $client);
        $this->injectMockDatabaseDependencies($handler);
        return $handler;
    }
}
