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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
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
    use \VuFindTest\Feature\ConfigRelatedServicesTrait;

    /**
     * Test configuration data.
     *
     * @var array
     */
    protected array $sampleLinks = [
        'foo' => 'http://foo',
        'bar' => 'http://bar',
    ];

    /**
     * Run a test scenario
     *
     * @param \VuFind\Config\ConfigManagerInterface $configManager Configuration manager
     * @param string                                $config        Recommendation config
     *
     * @return void
     */
    protected function runTestProcedure(\VuFind\Config\ConfigManagerInterface $configManager, string $config): void
    {
        $rec = new RecommendLinks($configManager);
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
    public function testRecommendLinksWithDefaultConfiguration(): void
    {
        $cm = $this->getMockConfigManager(
            ['searches' => ['RecommendLinks' => $this->sampleLinks]]
        );
        $this->runTestProcedure($cm, '');
    }

    /**
     * Test with custom configuration.
     *
     * @return void
     */
    public function testRecommendLinksWithCustomConfiguration(): void
    {
        $cm = $this->getMockConfigManager(
            ['foo' => ['bar' => $this->sampleLinks]]
        );
        $this->runTestProcedure($cm, 'bar:foo');
    }
}
