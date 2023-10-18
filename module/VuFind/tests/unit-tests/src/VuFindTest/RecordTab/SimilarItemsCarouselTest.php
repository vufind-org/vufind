<?php

/**
 * SimilarItemsCarousel Test Class
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
 * @package  Tests
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\RecordTab;

use VuFind\RecordTab\SimilarItemsCarousel;

/**
 * SimilarItemsCarousel Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class SimilarItemsCarouselTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test getting Description.
     *
     * @return void
     */
    public function testGetDescription(): void
    {
        $search = $this->getMockBuilder(\VuFindSearch\Service::class)
            ->disableOriginalConstructor()
            ->getMock();
        $config = $this->getMockBuilder(\Laminas\Config\Config::class)
            ->disableOriginalConstructor()
            ->getMock();
        $obj = new SimilarItemsCarousel($search, $config);
        $expected = 'Similar Items';
        $this->assertSame($expected, $obj->getDescription());
    }

    /**
     * Test getting results.
     *
     * @return void
     */
    public function testGetResults(): void
    {
        $service = $this->getMockBuilder(\VuFindSearch\Service::class)
            ->disableOriginalConstructor()
            ->getMock();
        $config = $this->getMockBuilder(\Laminas\Config\Config::class)
            ->disableOriginalConstructor()
            ->getMock();
        $rci = $this->getMockBuilder(
            \VuFindSearch\Response\RecordCollectionInterface::class
        )->getMock();
        $obj = new SimilarItemsCarousel($service, $config);
        $recordDriver = $this->getMockBuilder(\VuFind\RecordDriver\AbstractBase::class)
            ->disableOriginalConstructor()
            ->getMock();
        $recordDriver->expects($this->once())->method('getSourceIdentifier')
            ->will($this->returnValue('foo'));
        $recordDriver->expects($this->once())->method('getUniqueId')
            ->will($this->returnValue('bar'));
        $obj->setRecordDriver($recordDriver);

        $commandObj = $this->getMockBuilder(\VuFindSearch\Command\AbstractBase::class)
            ->disableOriginalConstructor()
            ->getMock();
        $commandObj->expects($this->once())->method('getResult')
            ->will($this->returnValue($rci));

        $checkCommand = function ($command) {
            return $command::class === \VuFindSearch\Command\SimilarCommand::class
                && $command->getTargetIdentifier() === 'foo'
                && $command->getArguments()[0] === 'bar'
                && $command->getArguments()[1]->getArrayCopy() === ['rows' => [40]];
        };
        $service->expects($this->once())->method('invoke')
            ->with($this->callback($checkCommand))
            ->will($this->returnValue($commandObj));
        $this->assertSame($rci, $obj->getResults());
    }
}
