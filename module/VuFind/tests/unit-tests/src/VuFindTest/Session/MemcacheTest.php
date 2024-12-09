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
    public function testRead()
    {
        if (!class_exists(\Memcache::class)) {
            $this->markTestSkipped();
        }
        // TODO: remove this check after raising minimum PHP version to 8;
        // for some reason, this test does not work under PHP 7 with PHPUnit 9.6.
        if (PHP_MAJOR_VERSION < 8) {
            $this->markTestSkipped('Not supported in PHP 7');
        }
        $memcache = $this->getMockBuilder(\Memcache::class)
            ->onlyMethods(['connect', 'get'])
            ->getMock();
        $memcache->expects($this->once())->method('connect')
            ->will($this->returnValue(true));
        $memcache->expects($this->once())->method('get')
            ->with($this->equalTo('vufind_sessions/foo'))
            ->will($this->returnValue('bar'));
        $handler = $this->getHandler(null, $memcache);
        $this->assertEquals('bar', $handler->read('foo'));
    }

    /**
     * Test reading a session from the database with Memcached.
     *
     * @return void
     */
    public function testReadWithMemcached()
    {
        if (!class_exists(\Memcached::class)) {
            $this->markTestSkipped();
        }
        $memcache = $this->getMockBuilder(\Memcached::class)
            ->onlyMethods(['setOption', 'addServer', 'get'])
            ->getMock();
        $memcache->expects($this->once())->method('setOption')
            ->with(
                $this->equalTo(\Memcached::OPT_CONNECT_TIMEOUT),
                $this->equalTo(1)
            );
        $memcache->expects($this->once())->method('addServer')
            ->with($this->equalTo('localhost'), $this->equalTo(11211))
            ->will($this->returnValue(true));
        $memcache->expects($this->once())->method('get')
            ->with($this->equalTo('vufind_sessions/foo'))
            ->will($this->returnValue('bar'));
        $config = new \Laminas\Config\Config(['memcache_client' => 'Memcached']);
        $handler = $this->getHandler($config, $memcache);
        $this->assertEquals('bar', $handler->read('foo'));
    }

    /**
     * Test writing a session with default configs.
     *
     * @return void
     */
    public function testWriteWithDefaults()
    {
        if (!class_exists(\Memcache::class)) {
            $this->markTestSkipped();
        }
        $memcache = $this->getMockBuilder(\Memcache::class)
            ->onlyMethods(['connect', 'set'])
            ->getMock();
        $memcache->expects($this->once())->method('connect')
            ->with(
                $this->equalTo('localhost'),
                $this->equalTo(11211),
                $this->equalTo(1)
            )->will($this->returnValue(true));
        $memcache->expects($this->once())->method('set')
            ->with(
                $this->equalTo('vufind_sessions/foo'),
                $this->equalTo('stuff'),
                $this->equalTo(0),
                $this->equalTo(3600)
            )->will($this->returnValue(true));
        $handler = $this->getHandler(null, $memcache);
        $this->assertTrue($handler->write('foo', 'stuff'));
    }

    /**
     * Test writing a session with non-default configs.
     *
     * @return void
     */
    public function testWriteWithNonDefaults()
    {
        if (!class_exists(\Memcache::class)) {
            $this->markTestSkipped();
        }
        $memcache = $this->getMockBuilder(\Memcache::class)
            ->onlyMethods(['connect', 'set'])
            ->getMock();
        $memcache->expects($this->once())->method('connect')
            ->with(
                $this->equalTo('myhost'),
                $this->equalTo(1234),
                $this->equalTo(2)
            )->will($this->returnValue(true));
        $memcache->expects($this->once())->method('set')
            ->with(
                $this->equalTo('vufind_sessions/foo'),
                $this->equalTo('stuff'),
                $this->equalTo(0),
                $this->equalTo(1000)
            )->will($this->returnValue(true));
        $config = new \Laminas\Config\Config(
            [
                'lifetime' => 1000,
                'memcache_host' => 'myhost',
                'memcache_port' => 1234,
                'memcache_connection_timeout' => 2,
            ]
        );
        $handler = $this->getHandler($config, $memcache);
        $this->assertTrue($handler->write('foo', 'stuff'));
    }

    /**
     * Test destroying a session.
     *
     * @return void
     */
    public function testDestroy()
    {
        if (!class_exists(\Memcache::class)) {
            $this->markTestSkipped();
        }
        $memcache = $this->getMockBuilder(\Memcache::class)
            ->onlyMethods(['connect', 'delete'])
            ->getMock();
        $memcache->expects($this->once())->method('connect')
            ->will($this->returnValue(true));
        $memcache->expects($this->once())->method('delete')
            ->with($this->equalTo('vufind_sessions/foo'))
            ->will($this->returnValue(true));
        $handler = $this->getHandler(null, $memcache);
        $this->setUpDestroyExpectations('foo');

        $this->assertTrue($handler->destroy('foo'));
    }

    /**
     * Test reading a session from the database (Memcached version).
     *
     * @return void
     */
    public function testReadMemcached()
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
            ->will($this->returnValue(true));
        $memcache->expects($this->once())->method('get')
            ->with($this->equalTo('vufind_sessions/foo'))
            ->will($this->returnValue('bar'));
        $config = new \Laminas\Config\Config(
            [
                'memcache_client' => 'Memcached',
            ]
        );
        $handler = $this->getHandler($config, $memcache);
        $this->assertEquals('bar', $handler->read('foo'));
    }

    /**
     * Test writing a session with default configs (Memcached version).
     *
     * @return void
     */
    public function testWriteWithDefaultsMemcached()
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
            )->will($this->returnValue(true));
        $memcache->expects($this->once())->method('set')
            ->with(
                $this->equalTo('vufind_sessions/foo'),
                $this->equalTo('stuff'),
                $this->equalTo(3600)
            )->will($this->returnValue(true));
        $config = new \Laminas\Config\Config(
            [
                'memcache_client' => 'Memcached',
            ]
        );
        $handler = $this->getHandler($config, $memcache);
        $this->assertTrue($handler->write('foo', 'stuff'));
    }

    /**
     * Test writing a session with non-default configs (Memcached version).
     *
     * @return void
     */
    public function testWriteWithNonDefaultsMemcached()
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
            )->will($this->returnValue(true));
        $memcache->expects($this->once())->method('set')
            ->with(
                $this->equalTo('vufind_sessions/foo'),
                $this->equalTo('stuff'),
                $this->equalTo(1000)
            )->will($this->returnValue(true));
        $config = new \Laminas\Config\Config(
            [
                'lifetime' => 1000,
                'memcache_host' => 'myhost',
                'memcache_port' => 1234,
                'memcache_connection_timeout' => 2,
                'memcache_client' => 'Memcached',
            ]
        );
        $handler = $this->getHandler($config, $memcache);
        $this->assertTrue($handler->write('foo', 'stuff'));
    }

    /**
     * Test destroying a session (Memcached version).
     *
     * @return void
     */
    public function testDestroyMemcached()
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
            ->will($this->returnValue(true));
        $memcache->expects($this->once())->method('delete')
            ->with($this->equalTo('vufind_sessions/foo'))
            ->will($this->returnValue(true));
        $config = new \Laminas\Config\Config(
            [
                'memcache_client' => 'Memcached',
            ]
        );
        $handler = $this->getHandler($config, $memcache);
        $this->setUpDestroyExpectations('foo');

        $this->assertTrue($handler->destroy('foo'));
    }

    /**
     * Get the session handler to test.
     *
     * @param \Laminas\Config\Config $config Optional configuration
     * @param \Memcache              $client Optional client object
     *
     * @return Database
     */
    protected function getHandler($config = null, $client = null)
    {
        $handler = new Memcache($config, $client);
        $this->injectMockDatabaseDependencies($handler);
        return $handler;
    }
}
