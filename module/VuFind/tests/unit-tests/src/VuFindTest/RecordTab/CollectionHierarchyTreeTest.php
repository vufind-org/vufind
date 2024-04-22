<?php

/**
 * CollectionHierarchyTree Test Class
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

use VuFind\RecordTab\CollectionHierarchyTree;

/**
 * CollectionHierarchyTree Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class CollectionHierarchyTreeTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test that getActiveRecord loads the correct record ID.
     *
     * @return void
     */
    public function testGetActiveRecord(): void
    {
        $conf = $this->getMockBuilder(\Laminas\Config\Config::class)
            ->disableOriginalConstructor()
            ->getMock();
        $load = $this->getMockBuilder(\VuFind\Record\Loader::class)
            ->disableOriginalConstructor()
            ->getMock();
        $request = $this->getMockBuilder(\Laminas\Http\Request::class)
            ->disableOriginalConstructor()
            ->getMock();
        $request->expects($this->once())->method('getQuery')
            ->with($this->equalTo('recordID'), $this->equalTo(false))
            ->will($this->returnValue('foo'));
        $recordDriver = $this->getMockBuilder(\VuFind\RecordDriver\AbstractBase::class)
            ->disableOriginalConstructor()
            ->getMock();
        $load->expects($this->once())->method('load')
            ->with($this->equalTo('foo'))
            ->will($this->returnValue($recordDriver));
        $obj = new CollectionHierarchyTree($conf, $load);
        $obj->setRequest($request);
        $this->assertSame($recordDriver, $obj->getActiveRecord());
    }

    /**
     * Test that getActiveRecord returns the main record when no ID parameter is provided.
     *
     * @return void
     */
    public function testGetActiveRecordWithEmptyId(): void
    {
        $conf = $this->getMockBuilder(\Laminas\Config\Config::class)
            ->disableOriginalConstructor()
            ->getMock();
        $load = $this->getMockBuilder(\VuFind\Record\Loader::class)
            ->disableOriginalConstructor()
            ->getMock();
        $request = $this->getMockBuilder(\Laminas\Http\Request::class)
            ->disableOriginalConstructor()
            ->getMock();
        $request->expects($this->once())->method('getQuery')
            ->with($this->equalTo('recordID'), $this->equalTo(false))
            ->will($this->returnValue(null));
        $load->expects($this->never())->method('load');
        $obj = new CollectionHierarchyTree($conf, $load);
        $obj->setRequest($request);
        $driver = new \VuFind\RecordDriver\DefaultRecord();
        $obj->setRecordDriver($driver);
        $this->assertEquals(
            $driver,
            $obj->getActiveRecord()
        );
    }
}
