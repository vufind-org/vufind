<?php
/**
 * SummonResultsDeferred recommendation module Test Class
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
namespace VuFindTest\Recommend;

/**
 * SummonResultsDeferred recommendation module Test Class
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
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
        $options = $this->getMockBuilder('VuFind\Search\Solr\Options')
            ->disableOriginalConstructor()->getMock();
        $options->expects($this->once())->method('getLabelForBasicHandler')->with($this->equalTo('bar'))->will($this->returnValue('baz'));
        $params->expects($this->once())->method('getOptions')->will($this->returnValue($options));
        $params->expects($this->once())->method('getSearchHandler')->will($this->returnValue('bar'));
        $mod = $this->getRecommend('VuFind\Recommend\SummonResultsDeferred', '', null, $results);
        $this->assertEquals(
            'mod=SummonResults&params=lookfor%3A&lookfor=foo&typeLabel=baz',
            $mod->getUrlParams()
        );
    }
}