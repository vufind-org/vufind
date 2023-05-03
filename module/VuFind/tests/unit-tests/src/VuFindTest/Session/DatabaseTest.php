<?php

/**
 * Database Session Handler Test Class
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

use VuFind\Session\Database;

/**
 * Database Session Handler Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class DatabaseTest extends \VuFindTest\Unit\SessionHandlerTestCase
{
    /**
     * Test reading a session from the database.
     *
     * @return void
     */
    public function testRead()
    {
        $handler = $this->getHandler();
        $session = $this->getMockSessionTable();
        $session->expects($this->once())->method('readSession')
            ->with($this->equalTo('foo'), $this->equalTo(3600))
            ->will($this->returnValue('bar'));
        $this->getTables()->set('Session', $session);
        $this->assertEquals('bar', $handler->read('foo'));
    }

    /**
     * Test reading a session from the database with a non-default lifetime config.
     *
     * @return void
     */
    public function testReadWithNonDefaultLifetime()
    {
        $handler = $this->getHandler(
            new \Laminas\Config\Config(['lifetime' => 1000])
        );
        $session = $this->getMockSessionTable();
        $session->expects($this->once())->method('readSession')
            ->with($this->equalTo('foo'), $this->equalTo(1000))
            ->will($this->returnValue('bar'));
        $this->getTables()->set('Session', $session);
        $this->assertEquals('bar', $handler->read('foo'));
    }

    /**
     * Test garbage collection.
     *
     * @return void
     */
    public function testGc()
    {
        $handler = $this->getHandler();
        $session = $this->getMockSessionTable();
        $session->expects($this->once())->method('garbageCollect')
            ->with($this->equalTo(3600));
        $this->getTables()->set('Session', $session);
        $this->assertTrue($handler->gc(3600));
    }

    /**
     * Test writing a session.
     *
     * @return void
     */
    public function testWrite()
    {
        $handler = $this->getHandler();
        $session = $this->getMockSessionTable();
        $session->expects($this->once())->method('writeSession')
            ->with($this->equalTo('foo'), $this->equalTo('stuff'));
        $this->getTables()->set('Session', $session);
        $this->assertTrue($handler->write('foo', 'stuff'));
    }

    /**
     * Test destroying a session.
     *
     * @return void
     */
    public function testDestroy()
    {
        $handler = $this->getHandler();
        $this->setUpDestroyExpectations('foo');
        $session = $this->getMockSessionTable();
        $session->expects($this->once())->method('destroySession')
            ->with($this->equalTo('foo'));
        $this->tables->set('Session', $session);
        $this->assertTrue($handler->destroy('foo'));
    }

    /**
     * Get the session handler to test.
     *
     * @param \Laminas\Config\Config $config Optional configuration
     *
     * @return Database
     */
    protected function getHandler($config = null)
    {
        $handler = new Database($config);
        $this->injectMockDatabaseTables($handler);
        return $handler;
    }

    /**
     * Get a mock session table.
     *
     * @return \VuFind\Db\Table\Session
     */
    protected function getMockSessionTable()
    {
        return $this->getMockBuilder(\VuFind\Db\Table\Session::class)
            ->disableOriginalConstructor()
            ->getMock();
    }
}
