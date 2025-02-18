<?php

/**
 * Similar Related Items Test Class
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
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Related;

use VuFind\Related\Similar;

/**
 * Similar Related Items Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class SimilarTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test results.
     *
     * @return void
     */
    public function testGetResults()
    {
        // Similar is really just a thin wrapper around the search service; make
        // sure it does its job properly with the help of some mocks.
        $driver = $this->getMockBuilder(\VuFind\RecordDriver\SolrDefault::class)
            ->onlyMethods(['getUniqueId'])
            ->getMock();
        $driver->expects($this->once())->method('getUniqueId')->willReturn('fakeid');

        $commandObj = $this->getMockBuilder(\VuFindSearch\Command\AbstractBase::class)
            ->disableOriginalConstructor()
            ->getMock();
        $commandObj->expects($this->once())->method('getResult')
            ->willReturn(['fakeresponse']);
        $checkCommand = function ($command) {
            $this->assertEquals(\VuFindSearch\Command\SimilarCommand::class, $command::class);
            $this->assertEquals('Solr', $command->getTargetIdentifier());
            $this->assertEquals('fakeid', $command->getArguments()[0]);
            return true;
        };
        $service = $this->createMock(\VuFindSearch\Service::class);
        $service->expects($this->once())->method('invoke')
            ->with($this->callback($checkCommand))
            ->willReturn($commandObj);
        $similar = new Similar($service);
        $similar->init('', $driver);
        $this->assertEquals(['fakeresponse'], $similar->getResults());
    }
}
