<?php

/**
 * SummonTopics Test Class
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

namespace VuFindTest\Recommend;

use VuFind\Recommend\SummonTopics;

/**
 * SummonTopics Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class SummonTopicsTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test getting topic results.
     *
     * @return void
     */
    public function testGetResults(): void
    {
        $pm = $this->getMockBuilder(\VuFind\Search\Results\PluginManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $obj = new SummonTopics($pm);
        $results = $this->getMockBuilder(\VuFind\Search\Summon\Results::class)
            ->disableOriginalConstructor()
            ->getMock();
        $parms = $this->getMockBuilder(\VuFind\Search\Base\Params::class)
            ->disableOriginalConstructor()
            ->getMock();
        $results->expects($this->once())->method('getParams')
            ->will($this->returnValue($parms));
        $parms->expects($this->once())->method('getSearchClassId')
            ->will($this->returnValue('Summon'));
        $obj->process($results);
        $results->expects($this->once())->method('getTopicRecommendations')
            ->will($this->returnValue(false));
        $this->assertFalse($obj->getResults());
    }

    /**
     * Test init.
     *
     * @return void
     */
    public function testInit(): void
    {
        $parms = $this->getMockBuilder(\VuFind\Search\Base\Params::class)
            ->disableOriginalConstructor()
            ->getMock();
        $request = $this->getMockBuilder(\Laminas\Stdlib\Parameters::class)
            ->disableOriginalConstructor()
            ->getMock();
        $options = $this->getMockBuilder(\VuFind\Search\Base\Options::class)
            ->disableOriginalConstructor()
            ->addMethods(['setMaxTopicRecommendations'])
            ->getMockForAbstractClass();
        $parms->expects($this->once())->method('getSearchClassId')
            ->will($this->returnValue('Summon'));
        $parms->expects($this->once())->method('getOptions')
            ->will($this->returnValue($options));
        $options->expects($this->once())->method('setMaxTopicRecommendations')
            ->with($this->equalTo(1));
        $pm = $this->getMockBuilder(\VuFind\Search\Results\PluginManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $obj = new SummonTopics($pm);
        $this->assertNull($obj->init($parms, $request));
    }

    /**
     * Test to configure SummonResults.
     *
     * @return void
     */
    public function testconfigureSummonResults(): void
    {
        $class = new \ReflectionClass(SummonTopics::class);
        $method = $class->getMethod('configureSummonResults');
        $method->setAccessible(true);
        $pm = $this->getMockBuilder(\VuFind\Search\Results\PluginManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $parms = $this->getMockBuilder(\VuFind\Search\Base\Params::class)
            ->disableOriginalConstructor()
            ->getMock();
        $obj = new SummonTopics($pm);
        $results = $this->getMockBuilder(\VuFind\Search\Summon\Results::class)
            ->disableOriginalConstructor()
            ->getMock();
        $results->expects($this->once())->method('getParams')
            ->will($this->returnValue($parms));
        $parms->expects($this->once())->method('setBasicSearch')
            ->with($this->anything(), $this->equalTo('AllFields'));
        $options = $this->getMockBuilder(\VuFind\Search\Base\Options::class)
            ->disableOriginalConstructor()
            ->addMethods(['setMaxTopicRecommendations'])
            ->getMockForAbstractClass();
        $results->expects($this->once())->method('getOptions')
            ->will($this->returnValue($options));
        $options->expects($this->once())->method('setMaxTopicRecommendations')
            ->with($this->equalTo(1));
        $this->assertNull($method->invokeArgs($obj, [$results]));
    }
}
