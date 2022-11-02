<?php

/**
 * Unit tests for SimilarCommand.
 *
 * PHP version 7
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
        $params = new ParamBag(['foo' => 'bar']);
        $backendId = 'bar';
        $backend = $this
            ->getMockBuilder(\VuFindSearch\Backend\Solr\Backend::class)
            ->disableOriginalConstructor()->getMock();
        $backend->expects($this->once())->method('getIdentifier')
            ->will($this->returnValue($backendId));
        $backend->expects($this->once())->method('similar')
            ->with(
                $this->equalTo("id"),
                $this->equalTo($params)
            )->will($this->returnValue('result'));

        $command = new SimilarCommand($backendId, "id", $params);

        $this->assertEquals('result', $command->execute($backend)->getResult());

        $this->assertEquals('similar', $command->getContext());
        $command->setContext('similar2');
        $this->assertEquals('similar2', $command->getContext());

        $this->assertEquals($backendId, $command->getTargetIdentifier());
        $command->setTargetIdentifier($backendId . '2');
        $this->assertEquals($backendId . '2', $command->getTargetIdentifier());

        $this->assertEquals($params, $command->getSearchParameters());
        $params2 = new ParamBag(['foo' => 'baz']);
        $command->setSearchParameters($params2);
        $this->assertEquals($params2, $command->getSearchParameters());
    }

    /**
     * Test that the command throws an exception results are requested before execute
     *
     * @return void
     */
    public function testTooEarlyResults(): void
    {
        $command = new SimilarCommand('bar', 'id', new ParamBag(['foo' => 'bar']));
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
        $command = new SimilarCommand('bar', 'id', new ParamBag(['foo' => 'bar']));

        $this->assertEquals(
            ['id', new ParamBag(['foo' => 'bar'])],
            $command->getArguments()
        );
    }
}
