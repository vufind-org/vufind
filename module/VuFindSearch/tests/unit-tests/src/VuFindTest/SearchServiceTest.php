<?php

/**
 * Unit tests for search service.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010, 2022.
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
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindTest;

use PHPUnit\Framework\TestCase;
use VuFindSearch\Backend\BackendInterface;
use VuFindSearch\Backend\Exception\BackendException;
use VuFindSearch\Service;

/**
 * Unit tests for search service.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class SearchServiceTest extends TestCase
{
    use \VuFindTest\Feature\WithConsecutiveTrait;

    /**
     * Mock backend
     *
     * @var BackendInterface
     */
    protected $backend = false;

    /**
     * Test invoke action.
     *
     * @return void
     */
    public function testInvoke()
    {
        $service = $this->getService();
        $backend = $this->getBackend();
        $command = $this->createMock(\VuFindSearch\Command\RetrieveCommand::class);
        $command->expects($this->once())->method('execute')
            ->with($this->equalTo($backend));
        $em = $service->getEventManager();
        $this->expectConsecutiveCalls(
            $em,
            'trigger',
            [['pre', $service], ['post', $service]]
        );
        $this->assertEquals($command, $service->invoke($command));
    }

    /**
     * Test exception-throwing invoke action.
     *
     * @return void
     */
    public function testInvokeException()
    {
        $this->expectException(BackendException::class);
        $this->expectExceptionMessage('test');

        $service = $this->getService();
        $backend = $this->getBackend();
        $command = $this->createMock(\VuFindSearch\Command\RetrieveCommand::class);
        $command->expects($this->once())->method('execute')
            ->with($this->equalTo($backend))
            ->will($this->throwException(new BackendException('test')));
        $em = $service->getEventManager();
        $this->expectConsecutiveCalls(
            $em,
            'trigger',
            [['pre', $service], ['error', $service]]
        );
        $this->assertEquals($command, $service->invoke($command));
    }

    /**
     * Test a failure to resolve using a command object.
     *
     * @return void
     */
    public function testFailedResolveWithCommand()
    {
        $this->expectException(\VuFindSearch\Exception\RuntimeException::class);
        $this->expectExceptionMessage('Unable to resolve backend: getInfo, EDS');

        $mockResponse = $this->createMock(\Laminas\EventManager\ResponseCollection::class);
        $mockResponse->expects($this->any())->method('stopped')->will($this->returnValue(false));
        $em = $this->createMock(\Laminas\EventManager\EventManagerInterface::class);
        $service = new Service();
        $em->expects($this->any())->method('triggerUntil')
            ->with(
                $this->anything(),
                $this->equalTo('resolve'),
                $this->equalTo($service)
            )->will($this->returnValue($mockResponse));
        $service->setEventManager($em);
        $service->invoke(new \VuFindSearch\Backend\EDS\Command\GetInfoCommand());
    }

    // Internal API

    /**
     * Create a mock backend.
     *
     * @param string $class      Class to build
     * @param string $identifier Backend ID to use
     *
     * @return object
     */
    protected function createMockBackend(
        $class = \VuFindSearch\Backend\BackendInterface::class,
        $identifier = 'foo'
    ) {
        $backend = $this->createMock($class);
        $backend->method('getIdentifier')->will($this->returnValue($identifier));
        return $backend;
    }

    /**
     * Get a mock backend.
     *
     * @return BackendInterface
     */
    protected function getBackend()
    {
        if (!$this->backend) {
            $this->backend = $this->createMockBackend();
        }
        return $this->backend;
    }

    /**
     * Generate a fake service.
     *
     * @return Service
     */
    protected function getService()
    {
        $em = $this->createMock(\Laminas\EventManager\EventManagerInterface::class);
        $service = $this->getMockBuilder(Service::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['resolve'])
            ->getMock();
        $service->expects($this->any())->method('resolve')
            ->will($this->returnValue($this->getBackend()));
        $service->setEventManager($em);
        return $service;
    }
}
