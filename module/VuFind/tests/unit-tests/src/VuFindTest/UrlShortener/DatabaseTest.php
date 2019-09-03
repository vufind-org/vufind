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

use VuFind\UrlShortener\Database;

/**
 * "Database" URL shortener test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class DatabaseTest extends \PHPUnit\Framework\TestCase
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
        return new Database('http://foo', $table);
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
        return $this->getMockBuilder(\VuFind\Db\Table\Shortlinks::class)
            ->disableOriginalConstructor()
            ->setMethods($methods)
            ->getMock();
    }

    /**
     * Test that the shortener works correctly under "happy path."
     *
     * @return void
     */
    public function testShortener()
    {
        $table = $this->getMockTable(['insert', 'getLastInsertValue']);
        $table->expects($this->once())->method('insert')
            ->with($this->equalTo(['path' => '/bar']));
        $table->expects($this->once())->method('getLastInsertValue')
            ->will($this->returnValue('10'));
        $db = $this->getShortener($table);
        $this->assertEquals('http://foo/short/A', $db->shorten('http://foo/bar'));
    }

    /**
     * Test that resolve is supported.
     *
     * @return void
     */
    public function testResolution()
    {
        $table = $this->getMockTable(['select']);
        $mockResults = $this->getMockBuilder(\Zend\Db\ResultSet::class)
            ->setMethods(['count', 'current'])
            ->disableOriginalConstructor()
            ->getMock();
        $mockResults->expects($this->once())->method('count')
            ->will($this->returnValue(1));
        $mockResults->expects($this->once())->method('current')
            ->will($this->returnValue(['path' => '/bar']));
        $table->expects($this->once())->method('select')
            ->with($this->equalTo(['id' => 10]))
            ->will($this->returnValue($mockResults));
        $db = $this->getShortener($table);
        $this->assertEquals('http://foo/bar', $db->resolve('A'));
    }

    /**
     * Test that resolve errors correctly when given bad input
     *
     * @return void
     *
     * @expectedException        Exception
     * @expectedExceptionMessage Shortlink could not be resolved: B
     */
    public function testResolutionOfBadInput()
    {
        $table = $this->getMockTable(['select']);
        $mockResults = $this->getMockBuilder(\Zend\Db\ResultSet::class)
            ->setMethods(['count'])
            ->disableOriginalConstructor()
            ->getMock();
        $mockResults->expects($this->once())->method('count')
            ->will($this->returnValue(0));
        $table->expects($this->once())->method('select')
            ->with($this->equalTo(['id' => 11]))
            ->will($this->returnValue($mockResults));
        $db = $this->getShortener($table);
        $db->resolve('B');
    }
}
