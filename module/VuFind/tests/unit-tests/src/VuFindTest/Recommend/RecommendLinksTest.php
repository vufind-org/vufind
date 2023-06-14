<?php

/**
 * RecommendLinks recommendation module Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2019.
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

use VuFind\Recommend\RecommendLinks;

/**
 * RecommendLinks recommendation module Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class RecommendLinksTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ConfigPluginManagerTrait;

    /**
     * Test configuration data.
     *
     * @var array
     */
    protected $sampleLinks = [
        'foo' => 'http://foo',
        'bar' => 'http://bar',
    ];

    /**
     * Run a test scenario
     *
     * @param \VuFind\Config\PluginManager $cm     Configuration manager
     * @param string                       $config Recommendation config
     *
     * @return void
     */
    protected function runTestProcedure($cm, $config)
    {
        $rec = new RecommendLinks($cm);
        $rec->setConfig($config);
        $rec->init(
            $this->createMock(\VuFind\Search\Solr\Params::class),
            new \Laminas\Stdlib\Parameters()
        );
        $rec->process(
            $this->createMock(\VuFind\Search\Solr\Results::class)
        );
        $this->assertEquals($this->sampleLinks, $rec->getLinks());
    }

    /**
     * Test with default configuration.
     *
     * @return void
     */
    public function testRecommendLinksWithDefaultConfiguration()
    {
        $cm = $this->getMockConfigPluginManager(
            ['searches' => ['RecommendLinks' => $this->sampleLinks]]
        );
        $this->runTestProcedure($cm, '');
    }

    /**
     * Test with custom configuration.
     *
     * @return void
     */
    public function testRecommendLinksWithCustomConfiguration()
    {
        $cm = $this->getMockConfigPluginManager(
            ['foo' => ['bar' => $this->sampleLinks]]
        );
        $this->runTestProcedure($cm, 'bar:foo');
    }
}
