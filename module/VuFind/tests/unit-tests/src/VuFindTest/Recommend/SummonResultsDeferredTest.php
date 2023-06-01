<?php

/**
 * SummonResultsDeferred recommendation module Test Class
 *
 * PHP version 8
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Recommend;

/**
 * SummonResultsDeferred recommendation module Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class SummonResultsDeferredTest extends \VuFindTest\Unit\RecommendDeferredTestCase
{
    /**
     * Test standard operation
     *
     * @return void
     */
    public function testStandardOperation()
    {
        $results = $this->getMockResults();
        $params = $results->getParams();
        $options = $this->getMockBuilder(\VuFind\Search\Solr\Options::class)
            ->disableOriginalConstructor()->getMock();
        $options->expects($this->once())->method('getLabelForBasicHandler')
            ->with($this->equalTo('bar'))->will($this->returnValue('baz'));
        $params->expects($this->once())->method('getOptions')->will($this->returnValue($options));
        $params->expects($this->once())->method('getSearchHandler')->will($this->returnValue('bar'));
        $mod = $this->getRecommend(\VuFind\Recommend\SummonResultsDeferred::class, '', null, $results);
        $this->assertEquals(
            'mod=SummonResults&params=lookfor%3A&lookfor=foo&typeLabel=baz',
            $mod->getUrlParams()
        );
    }
}
