<?php

/**
 * WorldCat2 Search Object Parameters Test
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

use VuFind\Config\PluginManager;
use VuFind\Search\WorldCat2\Options;
use VuFind\Search\WorldCat2\Params;

/**
 * WorldCat2 Search Object Parameters Test
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ParamsTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ConfigPluginManagerTrait;

    /**
     * Test that appropriate default backend parameters are created.
     *
     * @return void
     */
    public function testDefaultGetBackendParameters(): void
    {
        $config = [];
        $configManager = $this->getMockConfigPluginManager($config);
        $params = $this->getParams(null, $configManager);
        $expected = [
            'orderBy' => ['bestMatch'],
            'facets' => [],
        ];
        $this->assertEquals($expected, $params->getBackendParameters()->getArrayCopy());
    }

    /**
     * Test that configured backend parameters are passed through as expected.
     *
     * @return void
     */
    public function testNonDefaultGetBackendParameters(): void
    {
        $config = [];
        $configManager = $this->getMockConfigPluginManager($config);
        $params = $this->getParams(null, $configManager);
        $params->setSort('foo', true);
        $params->addFacet('bar');
        $params->addFacet('baz');
        $params->addFilter('bar:zoop');
        $params->addHiddenFilter('baz:zap');
        $expected = [
            'orderBy' => ['foo'],
            'facets' => ['bar', 'baz'],
            'bar' => ['zoop'],
            'baz' => ['zap'],
        ];
        $this->assertEquals($expected, $params->getBackendParameters()->getArrayCopy());
    }

    /**
     * Get Params object
     *
     * @param ?Options       $options    Options object (null to create)
     * @param ?PluginManager $mockConfig Mock config plugin manager (null to create)
     *
     * @return Params
     */
    protected function getParams(
        ?Options $options = null,
        ?PluginManager $mockConfig = null
    ): Params {
        $mockConfig ??= $this->createMock(PluginManager::class);
        return new Params(
            $options ?? new Options($mockConfig),
            $mockConfig
        );
    }
}
