<?php

/**
 * "Database" URL shortener test.
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

namespace VuFindTest\UrlShortener;

use Exception;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Adapter\Driver\ConnectionInterface;
use Laminas\Db\Adapter\Driver\DriverInterface;
use Laminas\Db\ResultSet\ResultSet;
use PHPUnit\Framework\TestCase;
use VuFind\Db\Table\Shortlinks;
use VuFind\UrlShortener\Database;

/**
 * "Database" URL shortener test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Cornelius Amzar <cornelius.amzar@bsz-bw.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class DatabaseTest extends TestCase
{
    /**
     * Get the object to test.
     *
     * @param object $table Database table object/mock
     *
     * @return Database
     */
    public function getShortener($table)
    {
        return new Database('http://foo', $table, 'RAnD0mVuFindSa!t');
    }

    /**
     * Get the mock table object.
     *
     * @param array $methods Methods to mock.
     *
     * @return object
     */
    public function getMockTable($methods)
    {
        return $this->getMockBuilder(Shortlinks::class)
            ->disableOriginalConstructor()
            ->onlyMethods($methods)
            ->getMock();
    }

    /**
     * Test that the shortener works correctly under "happy path."
     *
     * @return void
     *
     * @throws Exception
     */
    public function testShortener()
    {
        $connection = $this->getMockBuilder(ConnectionInterface::class)
            ->onlyMethods(
                [
                    'beginTransaction', 'commit', 'connect', 'getResource',
                    'isConnected', 'getCurrentSchema', 'disconnect', 'rollback',
                    'execute', 'getLastGeneratedValue'
                ]
            )->disableOriginalConstructor()
            ->getMock();
        $connection->expects($this->once())->method('beginTransaction');
        $connection->expects($this->once())->method('commit');
        $driver = $this->getMockBuilder(DriverInterface::class)
            ->onlyMethods(
                [
                    'getConnection', 'getDatabasePlatformName', 'checkEnvironment',
                    'createStatement', 'createResult', 'getPrepareType',
                    'formatParameterName', 'getLastGeneratedValue'
                ]
            )->disableOriginalConstructor()
            ->getMock();
        $driver->expects($this->once())->method('getConnection')
            ->will($this->returnValue($connection));
        $adapter = $this->getMockBuilder(Adapter::class)
            ->onlyMethods(['getDriver'])
            ->disableOriginalConstructor()
            ->getMock();
        $adapter->expects($this->once())->method('getDriver')
            ->will($this->returnValue($driver));
        $table = $this->getMockTable(['insert', 'select', 'getAdapter']);
        $table->expects($this->once())->method('insert')
            ->with($this->equalTo(['path' => '/bar', 'hash' => 'a1e7812e2']));
        $table->expects($this->once())->method('getAdapter')
            ->will($this->returnValue($adapter));
        $mockResults = $this->getMockBuilder(ResultSet::class)
            ->onlyMethods(['count', 'current'])
            ->disableOriginalConstructor()
            ->getMock();
        $mockResults->expects($this->once())->method('count')
            ->will($this->returnValue(0));
        $table->expects($this->once())->method('select')
            ->with($this->equalTo(['hash' => 'a1e7812e2']))
            ->will($this->returnValue($mockResults));
        $db = $this->getShortener($table);
        $this->assertEquals('http://foo/short/a1e7812e2', $db->shorten('http://foo/bar'));
    }

    /**
     * Test that resolve is supported.
     *
     * @return void
     *
     * @throws Exception
     */
    public function testResolution()
    {
        $table = $this->getMockTable(['select']);
        $mockResults = $this->getMockBuilder(ResultSet::class)
            ->onlyMethods(['count', 'current'])
            ->disableOriginalConstructor()
            ->getMock();
        $mockResults->expects($this->once())->method('count')
            ->will($this->returnValue(1));
        $mockResults->expects($this->once())->method('current')
            ->will($this->returnValue(['path' => '/bar', 'hash' => '8ef580184']));
        $table->expects($this->once())->method('select')
            ->with($this->equalTo(['hash' => '8ef580184']))
            ->will($this->returnValue($mockResults));
        $db = $this->getShortener($table);
        $this->assertEquals('http://foo/bar', $db->resolve('8ef580184'));
    }

    /**
     * Test that resolve errors correctly when given bad input
     *
     * @return void
     *
     * @throws Exception
     */
    public function testResolutionOfBadInput()
    {
        $this->expectExceptionMessage('Shortlink could not be resolved: abcd12?');

        $table = $this->getMockTable(['select']);
        $mockResults = $this->getMockBuilder(ResultSet::class)
            ->onlyMethods(['count'])
            ->disableOriginalConstructor()
            ->getMock();
        $mockResults->expects($this->once())->method('count')
            ->will($this->returnValue(0));
        $table->expects($this->once())->method('select')
            ->with($this->equalTo(['hash' => 'abcd12?']))
            ->will($this->returnValue($mockResults));
        $db = $this->getShortener($table);
        $db->resolve('abcd12?');
    }

    /**
     * Test that resolve errors correctly when given bad input
     *
     * @return void
     *
     * @throws Exception
     */
    public function testResolutionOfOldIds()
    {
        $table = $this->getMockTable(['select']);
        $mockResults = $this->getMockBuilder(ResultSet::class)
            ->onlyMethods(['count', 'current'])
            ->disableOriginalConstructor()
            ->getMock();
        $mockResults->expects($this->once())->method('count')
            ->will($this->returnValue(1));
        $mockResults->expects($this->once())->method('current')
            ->will($this->returnValue(['path' => '/bar', 'hash' => 'A']));
        $table->expects($this->once())->method('select')
            ->with($this->equalTo(['hash' => 'A']))
            ->will($this->returnValue($mockResults));
        $db = $this->getShortener($table);
        $this->assertEquals('http://foo/bar', $db->resolve('A'));
    }
}
