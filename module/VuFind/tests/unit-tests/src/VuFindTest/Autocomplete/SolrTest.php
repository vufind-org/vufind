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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
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
    use \VuFindTest\Feature\SearchObjectsTrait;

    /**
     * Test that configuration is parsed correctly.
     *
     * @return void
     */
    public function testSetConfigDefaults()
    {
        $solr = new Solr($this->getMockResultsPluginManager(allowDefaultFallback: true));
        $solr->setConfig('');
        $this->assertEquals(null, $this->getProperty($solr, 'handler'));
        $this->assertEquals(['title'], $this->getProperty($solr, 'displayField'));
        $this->assertEquals(null, $this->getProperty($solr, 'sortField'));
        $this->assertEquals([], $this->getProperty($solr, 'filters'));
        $this->assertNull($this->getProperty($solr, 'limit'));
    }

    /**
     * Data provider for testSetConfig().
     *
     * @return array[]
     */
    public static function setConfigProvider(): array
    {
        $filters = ['FF1:FV1', 'FF2:FV2'];
        return [
            'default limit, filters' => ['Handler:Display:Sort:FF1:FV1:FF2:FV2', $filters, null],
            'non-default limit, filters' => ['Handler:Display:Sort:20:FF1:FV1:FF2:FV2', $filters, 20],
            'default limit, no filters' => ['Handler:Display:Sort', [], null],
            'non-default limit, no filters' => ['Handler:Display:Sort:20', [], 20],
        ];
    }

    /**
     * Test that configuration is parsed correctly.
     *
     * @param string   $config          Config to test
     * @param string[] $expectedFilters Filters expected to be parsed from the config
     * @param ?int     $expectedLimit   Limit expected to be parsed from the config
     *
     * @return void
     *
     * @dataProvider setConfigProvider
     */
    public function testSetConfig(string $config, array $expectedFilters, ?int $expectedLimit)
    {
        $solr = new Solr($this->getMockResultsPluginManager(allowDefaultFallback: true));
        $solr->setConfig($config);
        $this->assertEquals('Handler', $this->getProperty($solr, 'handler'));
        $this->assertEquals(['Display'], $this->getProperty($solr, 'displayField'));
        $this->assertEquals('Sort', $this->getProperty($solr, 'sortField'));
        $this->assertEquals($expectedFilters, $this->getProperty($solr, 'filters'));
        $this->assertEquals($expectedLimit, $this->getProperty($solr, 'limit'));
    }
}
