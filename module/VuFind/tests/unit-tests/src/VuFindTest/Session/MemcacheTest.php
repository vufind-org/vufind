<?php
/**
 * Memcache Session Handler Test Class
 *
 * PHP version 7
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
     * Test reading a session from the database.
     *
     * @return void
     */
    public function testRead()
    {
        $memcache = $this->getMockBuilder(\Memcache::class)
            ->setMethods(['connect', 'get'])
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
     * Test writing a session with default configs.
     *
     * @return void
     */
    public function testWriteWithDefaults()
    {
        $memcache = $this->getMockBuilder(\Memcache::class)
            ->setMethods(['connect', 'set'])
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
        $memcache = $this->getMockBuilder(\Memcache::class)
            ->setMethods(['connect', 'set'])
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
        $config = new \Zend\Config\Config(
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
        $memcache = $this->getMockBuilder(\Memcache::class)
            ->setMethods(['connect', 'delete'])
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
     * Get the session handler to test.
     *
     * @param \Zend\Config\Config $config Optional configuration
     * @param \Memcache           $client Optional client object
     *
     * @return Database
     */
    protected function getHandler($config = null, $client = null)
    {
        $handler = new Memcache($config, $client);
        $this->injectMockDatabaseTables($handler);
        return $handler;
    }
}
