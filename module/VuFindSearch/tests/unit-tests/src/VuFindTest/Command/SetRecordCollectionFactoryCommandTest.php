<?php

/**
 * Unit tests for SetRecordCollectionFactoryCommand.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindTest\Command;

use PHPUnit\Framework\TestCase;
use VuFindSearch\Command\SetRecordCollectionFactoryCommand;
use VuFindSearch\Response\RecordCollectionFactoryInterface;

/**
 * Unit tests for SetRecordCollectionFactoryCommand.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class SetRecordCollectionFactoryCommandTest extends TestCase
{
    /**
     * Test that the command works as expected
     *
     * @return void
     */
    public function testCommand(): void
    {
        $factory = $this
            ->getMockBuilder(RecordCollectionFactoryInterface::class)
            ->getMock();
        $backendId = 'bar';
        $backend = $this
            ->getMockBuilder(\VuFindSearch\Backend\Solr\Backend::class)
            ->disableOriginalConstructor()->getMock();
        $backend->expects($this->once())->method('getIdentifier')
            ->will($this->returnValue($backendId));
        $backend->expects($this->once())->method('setRecordCollectionFactory')
            ->with($this->equalTo($factory));
        $command = new SetRecordCollectionFactoryCommand($backendId, $factory);
        $this->assertEmpty($command->execute($backend)->getResult());  // void method
    }

    /**
     * Test getArguments method.
     *
     * @return void
     */
    public function testgetArguments(): void
    {
        $factory = $this
            ->getMockBuilder(RecordCollectionFactoryInterface::class)
            ->getMock();
        $backendId = 'bar';
        $command = new SetRecordCollectionFactoryCommand($backendId, $factory);
        $this->assertSame([$factory], $command->getArguments());
    }
}
