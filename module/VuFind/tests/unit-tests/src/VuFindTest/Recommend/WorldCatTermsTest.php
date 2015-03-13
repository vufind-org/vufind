<?php
/**
 * WorldCatTerms recommendation module Test Class
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
use VuFind\Recommend\WorldCatTerms;

/**
 * WorldCatTerms recommendation module Test Class
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class WorldCatTermsTest extends \VuFindTest\Unit\TestCase
{
    /**
     * Test normal operation of the module.
     *
     * @return void
     */
    public function testNormalOperation()
    {
        $terms = [
            'exact' => 'exact', 'junk' => 'junk'
        ];
        $wcu = $this->getMockWorldCatUtils();
        $wcu->expects($this->once())->method('getRelatedTerms')
            ->with($this->equalTo('foo'), $this->equalTo('lcsh'))
            ->will($this->returnValue($terms));
        $results = $this->getMockResults();
        $request = new \Zend\StdLib\Parameters([]);
        $module = new WorldCatTerms($wcu);
        $module->setConfig('');
        $module->init($results->getParams(), $request);
        $module->process($results);
        $this->assertEquals(['exact' => 'exact'], $module->getTerms());
    }

    /**
     * Get a mock WorldCatUtils object.
     *
     * @return \VuFind\Connection\WorldCatUtils
     */
    protected function getMockWorldCatUtils()
    {
        return $this->getMockBuilder('VuFind\Connection\WorldCatUtils')
            ->disableOriginalConstructor()->getMock();
    }

    /**
     * Get a mock results object.
     *
     * @return \VuFind\Search\Solr\Results
     */
    protected function getMockResults()
    {
        $query = new \VuFindSearch\Query\Query('foo', 'bar');
        $params = $this->getMockBuilder('VuFind\Search\Solr\Params')
            ->disableOriginalConstructor()->getMock();
        $params->expects($this->any())->method('getQuery')
            ->will($this->returnValue($query));
        $results = $this->getMockBuilder('VuFind\Search\Solr\Results')
            ->disableOriginalConstructor()->getMock();
        $results->expects($this->any())->method('getParams')
            ->will($this->returnValue($params));
        return $results;
    }
}