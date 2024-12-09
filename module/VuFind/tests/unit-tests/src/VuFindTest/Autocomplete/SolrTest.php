<?php

/**
 * Solr autocomplete test class.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2018.
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
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Autocomplete;

use VuFind\Autocomplete\Solr;

/**
 * Solr autocomplete test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class SolrTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ReflectionTrait;

    /**
     * Get mock search options.
     *
     * @return \VuFind\Search\Solr\Options
     */
    protected function getMockOptions()
    {
        return $this->getMockBuilder(\VuFind\Search\Solr\Options::class)
            ->disableOriginalConstructor()->getMock();
    }

    /**
     * Get mock results plugin manager.
     *
     * @return \VuFind\Search\Results\PluginManager
     */
    protected function getMockResults()
    {
        $results = $this->getMockBuilder(\VuFind\Search\Solr\Results::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getOptions'])
            ->getMock();
        $results->expects($this->any())->method('getOptions')
            ->will($this->returnValue($this->getMockOptions()));
        return $results;
    }

    /**
     * Get mock results plugin manager.
     *
     * @return \VuFind\Search\Results\PluginManager
     */
    protected function getMockResultsPluginManager()
    {
        $rpm = $this->getMockBuilder(\VuFind\Search\Results\PluginManager::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get'])
            ->getMock();
        $rpm->expects($this->any())->method('get')
            ->will($this->returnValue($this->getMockResults()));
        return $rpm;
    }

    /**
     * Test that configuration is parsed correctly.
     *
     * @return void
     */
    public function testSetConfigDefaults()
    {
        $solr = new Solr($this->getMockResultsPluginManager());
        $solr->setConfig('');
        $this->assertEquals(null, $this->getProperty($solr, 'handler'));
        $this->assertEquals(['title'], $this->getProperty($solr, 'displayField'));
        $this->assertEquals(null, $this->getProperty($solr, 'sortField'));
        $this->assertEquals([], $this->getProperty($solr, 'filters'));
    }

    /**
     * Test that configuration is parsed correctly.
     *
     * @return void
     */
    public function testSetConfig()
    {
        $solr = new Solr($this->getMockResultsPluginManager());
        $solr->setConfig('Handler:Display:Sort:FF1:FV1:FF2:FV2');
        $this->assertEquals('Handler', $this->getProperty($solr, 'handler'));
        $this->assertEquals(['Display'], $this->getProperty($solr, 'displayField'));
        $this->assertEquals('Sort', $this->getProperty($solr, 'sortField'));
        $expected = ['FF1:FV1', 'FF2:FV2'];
        $this->assertEquals($expected, $this->getProperty($solr, 'filters'));
    }
}
