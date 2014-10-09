<?php

/**
 * ResultScroller controller plugin tests.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
namespace VuFindTest\Controller\Plugin;

use VuFind\Controller\Plugin\ResultScroller;
use VuFindTest\Unit\TestCase as TestCase;

/**
 * ResultScroller controller plugin tests.
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class ResultScrollerTest extends TestCase
{
    /**
     * Test disabled behavior
     *
     * @return void
     */
    public function testDisabled()
    {
        $plugin = new ResultScroller(false);
        $this->assertFalse($plugin->init($this->getMockResults()));
        $expected = array(
            'previousRecord'=>null, 'nextRecord'=>null,
            'currentPosition'=>null, 'resultTotal'=>null
        );
        $this->assertEquals($expected, $plugin->getScrollData($this->getMockRecordDriver('foo')));
    }

    /**
     * Get mock record driver
     *
     * @param string $id     ID
     * @param string $source Backend
     *
     * @return \VuFind\RecordDriver\AbstractBase
     */
    protected function getMockRecordDriver($id, $source = 'VuFind')
    {
        $driver = $this->getMockBuilder('VuFind\RecordDriver\AbstractBase')
            ->disableOriginalConstructor()
            ->getMock();
        $driver->expects($this->any())->method('getUniqueId')->will($this->returnValue($id));
        $driver->expects($this->any())->method('getResourceSource')->will($this->returnValue($source));
        return $driver;
    }

    /**
     * Get mock search results
     *
     * @return \VuFind\Search\Base\Results
     */
    protected function getMockResults()
    {
        $results = $this->getMockBuilder('VuFind\Search\Solr\Results')
            ->disableOriginalConstructor()
            ->getMock();
        return $results;
    }
}