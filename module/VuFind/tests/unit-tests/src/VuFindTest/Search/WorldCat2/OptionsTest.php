<?php

/**
 * WorldCat2 Search Object Options Test
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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

namespace VuFindTest\Search\WorldCat2;

use Laminas\Config\Config;
use VuFind\Config\PluginManager;
use VuFind\Search\WorldCat2\Options;

/**
 * WorldCat2 Search Object Options Test
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class OptionsTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test configured terms limit setting.
     *
     * @return void
     */
    public function testGetTermsLimitWithConfiguration(): void
    {
        $config = ['General' => ['terms_limit' => 5]];
        $this->assertEquals(5, $this->getOptions($config)->getQueryTermsLimit());
    }

    /**
     * Test default terms limit setting.
     *
     * @return void
     */
    public function testGetTermsLimitWithDefault(): void
    {
        $this->assertEquals(30, $this->getOptions()->getQueryTermsLimit());
    }

    /**
     * Test getting search action.
     *
     * @return void
     */
    public function testGetSearchAction(): void
    {
        $this->assertEquals('worldcat2-search', $this->getOptions()->getSearchAction());
    }

    /**
     * Test getting advanced search action.
     *
     * @return void
     */
    public function testGetAdvancedSearchAction(): void
    {
        $this->assertEquals('worldcat2-advanced', $this->getOptions()->getAdvancedSearchAction());
    }

    /**
     * Get Params object
     *
     * @param array $config Configuration to get from config manager
     *
     * @return Options
     */
    protected function getOptions(array $config = []): Options
    {
        $mockConfig = $this->createMock(PluginManager::class);
        $configObj = new Config($config);
        $mockConfig->method('get')->willReturn($configObj);
        return new Options($mockConfig);
    }
}
