<?php

/**
 * Solr Search Object Options Test
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2021.
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

namespace VuFindTest\Search\Solr;

use VuFind\Config\PluginManager;
use VuFind\Search\Solr\Options;

/**
 * Solr Search Object Options Test
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class OptionsTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ConfigPluginManagerTrait;

    /**
     * Get Options object
     *
     * @param PluginManager $configManager Config manager for Options object (null
     * for new mock)
     *
     * @return Options
     */
    protected function getOptions(PluginManager $configManager = null): Options
    {
        return new Options($configManager ?? $this->getMockConfigPluginManager([]));
    }

    /**
     * Test that correct search class ID is reported
     *
     * @return void
     */
    public function testGetSearchClassId(): void
    {
        $this->assertEquals('Solr', $this->getOptions()->getSearchClassId());
    }

    /**
     * Test default sort tie-breaker behavior.
     *
     * @return void
     */
    public function testDefaultSortTieBreaker(): void
    {
        $this->assertNull($this->getOptions()->getSortTieBreaker());
    }

    /**
     * Test configuration of sort tie-breaker setting.
     *
     * @return void
     */
    public function testSortTieBreakerConfiguration(): void
    {
        $configs = ['searches' => ['General' => ['tie_breaker_sort' => 'foo']]];
        $options = $this->getOptions($this->getMockConfigPluginManager($configs));
        $this->assertEquals('foo', $options->getSortTieBreaker());
    }
}
