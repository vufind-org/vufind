<?php
/**
 * TOC Test Class
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

use VuFind\RecordTab\TOC;

/**
 * TOC Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class TOCTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test getting Description.
     *
     * @return void
     */
    public function testGetDescription(): void
    {
        $obj = new TOC();
        $expected = 'Table of Contents';

        $this->assertSame($expected, $obj->getDescription());
    }

    /**
     * Test if the tab is active.
     *
     * @return void
     */
    public function testIsActive(): void
    {
        $recordDriver = $this->getMockBuilder(\VuFind\RecordDriver\SolrDefault::class)
            ->disableOriginalConstructor()
            ->getMock();
        $recordDriver->expects($this->any())->method('tryMethod')
            ->with($this->equalTo('getTOC'))
            ->will($this->returnValue(true));
        // considering the complexity to create mock objects
        // for parent class, only one test case was tested
        $obj=new TOC();
        $obj->setRecordDriver($recordDriver);
        $this->assertTrue($obj->isActive());
    }
}
