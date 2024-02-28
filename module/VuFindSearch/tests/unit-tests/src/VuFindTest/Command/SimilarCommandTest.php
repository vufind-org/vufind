<?php

/**
 * Unit tests for SimilarCommand.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2022.
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
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindTest\Command;

use PHPUnit\Framework\TestCase;
use VuFindSearch\Backend\Solr\Backend;
use VuFindSearch\Command\SimilarCommand;
use VuFindSearch\ParamBag;

/**
 * Unit tests for SimilarCommand.
 *
 * @category VuFind
 * @package  Search
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class SimilarCommandTest extends TestCase
{
    /**
     * Test that the command works as expected
     *
     * @return void
     */
    public function testCommand(): void
    {
        $backendId = 'bar';
        $params = new ParamBag(['foo' => 'bar']);
        $backend = $this->getBackend();
        $backend->expects($this->once())->method('getIdentifier')
            ->willReturn($backendId);
        $backend->expects($this->once())->method('similar')
            ->with(
                $this->equalTo('id'),
                $this->equalTo($params)
            )->willReturn('result');
        $command = $this->getCommand();
        $this->assertEquals('result', $command->execute($backend)->getResult());
    }

    /**
     * Test setter and getter of Search Parameters.
     *
     * @return void
     */
    public function testSearchParameters()
    {
        $command = $this->getCommand();
        $this->assertEquals(
            new ParamBag(['foo' => 'bar']),
            $command->getSearchParameters()
        );
        $params2 = new ParamBag(['foo' => 'baz']);
        $command->setSearchParameters($params2);
        $this->assertEquals($params2, $command->getSearchParameters());
    }

    /**
     * Test setter and getter of target backend identifier.
     *
     * @return void
     */
    public function testTargetBackendIdentifier()
    {
        $backendId = 'bar';
        $command = $this->getCommand();
        $this->assertEquals($backendId, $command->getTargetIdentifier());
        $command->setTargetIdentifier($backendId . '2');
        $this->assertEquals($backendId . '2', $command->getTargetIdentifier());
    }

    /**
     * Test setter and getter of command context.
     *
     * @return void
     */
    public function testCommandContext()
    {
        $command = $this->getCommand();
        $this->assertEquals('similar', $command->getContext());
        $command->setContext('similar2');
        $this->assertEquals('similar2', $command->getContext());
    }

    /**
     * Test that the command throws an exception results are requested before execute
     *
     * @return void
     */
    public function testTooEarlyResults(): void
    {
        $command = $this->getCommand();
        $this->expectExceptionMessage('Command was not yet executed');
        $command->getResult();
    }

    /**
     * Test for getArguments method
     *
     * @return void
     */
    public function testgetArguments(): void
    {
        $command = $this->getCommand();
        $this->assertEquals(
            ['id', new ParamBag(['foo' => 'bar'])],
            $command->getArguments()
        );
    }

    /**
     * Get test SimilarCommand Object
     *
     * @return SimilarCommand
     */
    public function getCommand()
    {
        $params = new ParamBag(['foo' => 'bar']);
        $backendId = 'bar';

        $command = new SimilarCommand($backendId, 'id', $params);
        return $command;
    }

    /**
     * Get test backend Object
     *
     * @return Backend
     */
    public function getBackend()
    {
        $backend = $this->getMockBuilder(Backend::class)
            ->disableOriginalConstructor()->getMock();
        return $backend;
    }
}
