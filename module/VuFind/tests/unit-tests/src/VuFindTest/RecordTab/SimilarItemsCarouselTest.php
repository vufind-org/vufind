<?php
/**
 * SimilarItemsCarousel Test Class
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
        $obj = new SimilarItemsCarousel($search);
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
        $search = $this->getMockBuilder(\VuFindSearch\Service::class)
            ->disableOriginalConstructor()
            ->getMock();
        $rci = $this->getMockBuilder(
            \VuFindSearch\Response\RecordCollectionInterface::class
        )->getMock();
        $obj = new SimilarItemsCarousel($search);
        $recordDriver = $this->getMockBuilder(\VuFind\RecordDriver\AbstractBase::class)
            ->disableOriginalConstructor()
            ->getMock();
        $recordDriver->expects($this->once())->method('getSourceIdentifier')
            ->will($this->returnValue("foo"));
        $recordDriver->expects($this->once())->method('getUniqueId')
            ->will($this->returnValue("bar"));
        $obj->setRecordDriver($recordDriver);
        $search->expects($this->once())->method('similar')
            ->with(
                $this->equalTo("foo"),
                $this->equalTo("bar"),
                $this->equalTo(new \VuFindSearch\ParamBag(['rows' => 40])),
            )->will($this->returnValue($rci));
        $this->assertSame($rci, $obj->getResults());
    }
}
