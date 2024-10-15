<?php

/**
 * Database Session Handler Test Class
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

use Laminas\Config\Config;
use PHPUnit\Framework\MockObject\MockObject;
use VuFind\Db\Service\SessionServiceInterface;
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
    public function testRead(): void
    {
        $handler = $this->getHandler();
        $session = $this->getMockSessionService();
        $session->expects($this->once())->method('readSession')
            ->with($this->equalTo('foo'), $this->equalTo(3600))
            ->willReturn('bar');
        $this->assertEquals('bar', $handler->read('foo'));
    }

    /**
     * Test reading a session from the database with a non-default lifetime config.
     *
     * @return void
     */
    public function testReadWithNonDefaultLifetime(): void
    {
        $handler = $this->getHandler(new Config(['lifetime' => 1000]));
        $session = $this->getMockSessionService();
        $session->expects($this->once())->method('readSession')
            ->with($this->equalTo('foo'), $this->equalTo(1000))
            ->willReturn('bar');
        $this->assertEquals('bar', $handler->read('foo'));
    }

    /**
     * Test garbage collection.
     *
     * @return void
     */
    public function testGc(): void
    {
        $handler = $this->getHandler();
        $session = $this->getMockSessionService();
        $session->expects($this->once())->method('garbageCollect')
            ->with($this->equalTo(3600))
            ->willReturn(150);
        $this->assertEquals(150, $handler->gc(3600));
    }

    /**
     * Test writing a session.
     *
     * @return void
     */
    public function testWrite(): void
    {
        $handler = $this->getHandler();
        $session = $this->getMockSessionService();
        $session->expects($this->once())->method('writeSession')
            ->with($this->equalTo('foo'), $this->equalTo('stuff'))
            ->willReturn(true);
        $this->assertTrue($handler->write('foo', 'stuff'));
    }

    /**
     * Test destroying a session.
     *
     * @return void
     */
    public function testDestroy(): void
    {
        $handler = $this->getHandler();
        $this->setUpDestroyExpectations('foo');
        $session = $this->getMockSessionService();
        $session->expects($this->once())->method('destroySession')
            ->with($this->equalTo('foo'));
        $this->assertTrue($handler->destroy('foo'));
    }

    /**
     * Get the session handler to test.
     *
     * @param Config $config Optional configuration
     *
     * @return Database
     */
    protected function getHandler(Config $config = null): Database
    {
        $handler = new Database($config);
        $this->injectMockDatabaseDependencies($handler);
        return $handler;
    }

    /**
     * Get a mock session service.
     *
     * @return MockObject&SessionServiceInterface
     */
    protected function getMockSessionService(): MockObject&SessionServiceInterface
    {
        return $this->services->get(SessionServiceInterface::class);
    }
}
