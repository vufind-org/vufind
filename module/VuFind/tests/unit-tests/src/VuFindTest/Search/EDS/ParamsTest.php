<?php

/**
 * EDS Search Object Parameters Test
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2022.
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

namespace VuFindTest\Search\EDS;

use VuFind\Config\PluginManager;
use VuFind\Search\EDS\Options;
use VuFind\Search\EDS\Params;

/**
 * EDS Search Object Parameters Test
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ParamsTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test that checkbox filters are always visible (or not) as appropriate.
     *
     * @return void
     */
    public function testDynamicCheckboxes()
    {
        $options = $this->getMockBuilder(Options::class)
            ->disableOriginalConstructor()
            ->getMock();
        $limiters = [
            ['selectedvalue' => 'limitervalue', 'description' => 'limiter'],
        ];
        $options->expects($this->once())->method('getSearchScreenLimiters')
            ->will($this->returnValue($limiters));
        $expanders = [
            ['selectedvalue' => 'expandervalue', 'description' => 'expander'],
        ];
        $options->expects($this->once())->method('getSearchScreenExpanders')
            ->will($this->returnValue($expanders));
        $params = $this->getParams($options);
        // We expect "normal" filters to NOT be always visible, and inverted
        // filters to be always visible.
        $this->assertEquals(
            [
                [
                    'desc' => 'limiter',
                    'filter' => 'limitervalue',
                    'selected' => false,
                    'alwaysVisible' => false,
                    'dynamic' => true,
                ],
                [
                    'desc' => 'expander',
                    'filter' => 'expandervalue',
                    'selected' => false,
                    'alwaysVisible' => false,
                    'dynamic' => true,
                ],
            ],
            $params->getCheckboxFacets()
        );
    }

    /**
     * Get Params object
     *
     * @param Options       $options    Options object (null to create)
     * @param PluginManager $mockConfig Mock config plugin manager (null to create)
     *
     * @return Params
     */
    protected function getParams(
        Options $options = null,
        PluginManager $mockConfig = null
    ): Params {
        $mockConfig ??= $this->createMock(PluginManager::class);
        return new Params(
            $options ?? new Options($mockConfig),
            $mockConfig
        );
    }
}
